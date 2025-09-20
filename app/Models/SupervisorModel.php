<?php
/**
 * Supervisor model for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Supervisor model class
 */
class SupervisorModel
{
    /**
     * Database service instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new DatabaseService();
    }

    /**
     * Create a new supervisor
     */
    public function create($data)
    {
        // Validate required fields
        $validation = $this->validate_supervisor_data($data);
        if ($validation !== true) {
            return array('success' => false, 'errors' => $validation);
        }

        // Sanitize data
        $sanitized_data = $this->sanitize_supervisor_data($data);

        // Check for duplicate email
        if ($this->email_exists($sanitized_data['email'])) {
            return array('success' => false, 'error' => 'Email address already exists');
        }

        // Insert into database
        $supervisor_id = $this->db->create_supervisor($sanitized_data);

        if ($supervisor_id) {
            return array('success' => true, 'supervisor_id' => $supervisor_id);
        } else {
            return array('success' => false, 'error' => 'Failed to create supervisor');
        }
    }

    /**
     * Get supervisor by ID
     */
    public function get($supervisor_id)
    {
        return $this->db->get_supervisor_by_id($supervisor_id);
    }

    /**
     * Get supervisor by email
     */
    public function get_by_email($email)
    {
        return $this->db->get_supervisor_by_email($email);
    }

    /**
     * Get all supervisors
     */
    public function get_all($active_only = false)
    {
        return $this->db->get_all_supervisors($active_only);
    }

    /**
     * Update supervisor
     */
    public function update($supervisor_id, $data)
    {
        // Validate data
        $validation = $this->validate_supervisor_data($data, $supervisor_id);
        if ($validation !== true) {
            return array('success' => false, 'errors' => $validation);
        }

        // Sanitize data
        $sanitized_data = $this->sanitize_supervisor_data($data);

        // Update in database
        $result = $this->db->update_supervisor($supervisor_id, $sanitized_data);

        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Failed to update supervisor');
        }
    }

    /**
     * Delete supervisor
     */
    public function delete($supervisor_id)
    {
        // Check if supervisor is assigned to any classes
        if ($this->has_active_assignments($supervisor_id)) {
            return array('success' => false, 'error' => 'Cannot delete supervisor with active class assignments');
        }

        $result = $this->db->delete_supervisor($supervisor_id);

        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Failed to delete supervisor');
        }
    }

    /**
     * Deactivate supervisor (soft delete)
     */
    public function deactivate($supervisor_id)
    {
        return $this->update($supervisor_id, array('active' => false));
    }

    /**
     * Activate supervisor
     */
    public function activate($supervisor_id)
    {
        return $this->update($supervisor_id, array('active' => true));
    }

    /**
     * Assign supervisor to client
     */
    public function assign_to_client($supervisor_id, $client_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        // Get current assignments
        $current_assignments = json_decode($supervisor->client_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        // Add client if not already assigned
        if (!in_array($client_id, $current_assignments)) {
            $current_assignments[] = intval($client_id);
        }

        // Update assignments
        return $this->update($supervisor_id, array(
            'client_assignments' => json_encode($current_assignments)
        ));
    }

    /**
     * Remove supervisor from client
     */
    public function remove_from_client($supervisor_id, $client_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        // Get current assignments
        $current_assignments = json_decode($supervisor->client_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        // Remove client from assignments
        $current_assignments = array_diff($current_assignments, array(intval($client_id)));

        // Update assignments
        return $this->update($supervisor_id, array(
            'client_assignments' => json_encode(array_values($current_assignments))
        ));
    }

    /**
     * Assign supervisor to site
     */
    public function assign_to_site($supervisor_id, $site_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        // Get current assignments
        $current_assignments = json_decode($supervisor->site_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        // Add site if not already assigned
        if (!in_array($site_id, $current_assignments)) {
            $current_assignments[] = intval($site_id);
        }

        // Update assignments
        return $this->update($supervisor_id, array(
            'site_assignments' => json_encode($current_assignments)
        ));
    }

    /**
     * Remove supervisor from site
     */
    public function remove_from_site($supervisor_id, $site_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        // Get current assignments
        $current_assignments = json_decode($supervisor->site_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        // Remove site from assignments
        $current_assignments = array_diff($current_assignments, array(intval($site_id)));

        // Update assignments
        return $this->update($supervisor_id, array(
            'site_assignments' => json_encode(array_values($current_assignments))
        ));
    }

    /**
     * Set as default supervisor
     */
    public function set_as_default($supervisor_id)
    {
        // First, remove default status from all supervisors
        $this->db->clear_default_supervisors();

        // Then set this supervisor as default
        return $this->update($supervisor_id, array('is_default' => true));
    }

    /**
     * Remove default status
     */
    public function remove_default($supervisor_id)
    {
        return $this->update($supervisor_id, array('is_default' => false));
    }

    /**
     * Get default supervisor
     */
    public function get_default()
    {
        return $this->db->get_default_supervisor();
    }

    /**
     * Get supervisors for specific client
     */
    public function get_for_client($client_id)
    {
        return $this->db->get_supervisors_for_client($client_id);
    }

    /**
     * Get supervisors for specific site
     */
    public function get_for_site($site_id)
    {
        return $this->db->get_supervisors_for_site($site_id);
    }

    /**
     * Resolve supervisor for class
     * Returns the most appropriate supervisor based on client/site assignments or default
     */
    public function resolve_supervisor_for_class($class_id, $client_id = null, $site_id = null)
    {
        // Try site-specific supervisor first
        if ($site_id) {
            $site_supervisors = $this->get_for_site($site_id);
            if (!empty($site_supervisors)) {
                return $site_supervisors[0]; // Return first active supervisor for site
            }
        }

        // Try client-specific supervisor
        if ($client_id) {
            $client_supervisors = $this->get_for_client($client_id);
            if (!empty($client_supervisors)) {
                return $client_supervisors[0]; // Return first active supervisor for client
            }
        }

        // Fall back to default supervisor
        $default_supervisor = $this->get_default();
        if ($default_supervisor) {
            return $default_supervisor;
        }

        // If no default, return first active supervisor
        $all_supervisors = $this->get_all(true);
        if (!empty($all_supervisors)) {
            return $all_supervisors[0];
        }

        return null; // No supervisors available
    }

    /**
     * Check if email exists
     */
    public function email_exists($email, $exclude_id = null)
    {
        $existing = $this->get_by_email($email);

        if (!$existing) {
            return false;
        }

        // If excluding an ID (for updates), check if it's the same record
        if ($exclude_id && $existing->id == $exclude_id) {
            return false;
        }

        return true;
    }

    /**
     * Check if supervisor has active assignments
     */
    public function has_active_assignments($supervisor_id)
    {
        // This would check the classes table for any classes assigned to this supervisor
        // For now, we'll implement basic check
        global $wpdb;

        $classes_table = $wpdb->prefix . 'wecoza_classes'; // Assumes classes plugin table

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$classes_table} WHERE project_supervisor_id = %d AND status != 'completed'",
            $supervisor_id
        ));

        return $count > 0;
    }

    /**
     * Validate supervisor data
     */
    private function validate_supervisor_data($data, $exclude_id = null)
    {
        $errors = array();

        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Name is required';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!is_email($data['email'])) {
            $errors[] = 'Invalid email format';
        } elseif ($this->email_exists($data['email'], $exclude_id)) {
            $errors[] = 'Email address already exists';
        }

        // Validate role if provided
        if (isset($data['role']) && !empty($data['role'])) {
            $valid_roles = array('supervisor', 'manager', 'admin', 'coordinator');
            if (!in_array($data['role'], $valid_roles)) {
                $errors[] = 'Invalid role specified';
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Sanitize supervisor data
     */
    private function sanitize_supervisor_data($data)
    {
        $sanitized = array();

        // Sanitize text fields
        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }

        if (isset($data['role'])) {
            $sanitized['role'] = sanitize_text_field($data['role']);
        }

        // Boolean fields
        if (isset($data['is_default'])) {
            $sanitized['is_default'] = (bool) $data['is_default'];
        }

        if (isset($data['active'])) {
            $sanitized['active'] = (bool) $data['active'];
        }

        // JSON fields
        if (isset($data['client_assignments'])) {
            if (is_array($data['client_assignments'])) {
                $sanitized['client_assignments'] = json_encode(array_map('intval', $data['client_assignments']));
            } else {
                $sanitized['client_assignments'] = sanitize_text_field($data['client_assignments']);
            }
        }

        if (isset($data['site_assignments'])) {
            if (is_array($data['site_assignments'])) {
                $sanitized['site_assignments'] = json_encode(array_map('intval', $data['site_assignments']));
            } else {
                $sanitized['site_assignments'] = sanitize_text_field($data['site_assignments']);
            }
        }

        return $sanitized;
    }

    /**
     * Get supervisor statistics
     */
    public function get_statistics()
    {
        $stats = array();

        $stats['total'] = count($this->get_all());
        $stats['active'] = count($this->get_all(true));
        $stats['with_client_assignments'] = $this->db->count_supervisors_with_client_assignments();
        $stats['with_site_assignments'] = $this->db->count_supervisors_with_site_assignments();
        $stats['default_supervisor'] = $this->get_default() ? 1 : 0;

        return $stats;
    }

    /**
     * Log error
     */
    private function log_error($message, $context = array())
    {
        if (function_exists('error_log')) {
            $log_message = "WECOZA Notifications SupervisorModel Error: {$message}";
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            error_log($log_message);
        }
    }
}