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
     * PostgreSQL database service
     */
    private $db;

    /**
     * Fully-qualified supervisors table name
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = PostgreSQLDatabaseService::get_instance();
        $this->table = $this->db->get_table('supervisors');
    }

    /**
     * Create a new supervisor
     */
    public function create($data)
    {
        $validation = $this->validate_supervisor_data($data);
        if ($validation !== true) {
            return array('success' => false, 'errors' => $validation);
        }

        $sanitized_data = $this->sanitize_supervisor_data($data);

        if (!isset($sanitized_data['role'])) {
            $sanitized_data['role'] = 'supervisor';
        }

        if (!isset($sanitized_data['client_assignments'])) {
            $sanitized_data['client_assignments'] = json_encode(array());
        }

        if (!isset($sanitized_data['site_assignments'])) {
            $sanitized_data['site_assignments'] = json_encode(array());
        }

        $sanitized_data['created_at'] = current_time('mysql');
        $sanitized_data['updated_at'] = current_time('mysql');

        $supervisor_id = $this->db->insert('supervisors', $sanitized_data);

        if ($supervisor_id) {
            return array('success' => true, 'supervisor_id' => $supervisor_id);
        }

        return array('success' => false, 'error' => 'Failed to create supervisor');
    }

    /**
     * Get supervisor by ID
     */
    public function get($supervisor_id)
    {
        if (!$this->table) {
            return null;
        }

        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->db->get_row($sql, array(intval($supervisor_id)));
    }

    /**
     * Get supervisor by email
     */
    public function get_by_email($email)
    {
        if (!$this->table) {
            return null;
        }

        $sql = "SELECT * FROM {$this->table} WHERE email = ? LIMIT 1";
        return $this->db->get_row($sql, array(sanitize_email($email)));
    }

    /**
     * Get all supervisors
     */
    public function get_all($active_only = false)
    {
        if (!$this->table) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table}";
        if ($active_only) {
            $sql .= " WHERE is_active = TRUE";
        }
        $sql .= " ORDER BY name ASC";

        $results = $this->db->get_results($sql, array());
        return is_array($results) ? $results : array();
    }

    /**
     * Update supervisor
     */
    public function update($supervisor_id, $data)
    {
        $validation = $this->validate_supervisor_data($data, $supervisor_id);
        if ($validation !== true) {
            return array('success' => false, 'errors' => $validation);
        }

        $sanitized_data = $this->sanitize_supervisor_data($data);
        $sanitized_data['updated_at'] = current_time('mysql');

        $result = $this->db->update('supervisors', $sanitized_data, array('id' => intval($supervisor_id)));

        if ($result) {
            return array('success' => true);
        }

        return array('success' => false, 'error' => 'Failed to update supervisor');
    }

    /**
     * Delete supervisor
     */
    public function delete($supervisor_id)
    {
        if ($this->has_active_assignments($supervisor_id)) {
            return array('success' => false, 'error' => 'Cannot delete supervisor with active class assignments');
        }

        $result = $this->db->delete('supervisors', array('id' => intval($supervisor_id)));

        if ($result) {
            return array('success' => true);
        }

        return array('success' => false, 'error' => 'Failed to delete supervisor');
    }

    public function deactivate($supervisor_id)
    {
        return $this->update($supervisor_id, array('is_active' => false));
    }

    public function activate($supervisor_id)
    {
        return $this->update($supervisor_id, array('is_active' => true));
    }

    public function assign_to_client($supervisor_id, $client_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        $current_assignments = json_decode($supervisor->client_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        if (!in_array($client_id, $current_assignments)) {
            $current_assignments[] = intval($client_id);
        }

        return $this->update($supervisor_id, array(
            'client_assignments' => json_encode(array_values($current_assignments))
        ));
    }

    public function remove_from_client($supervisor_id, $client_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        $current_assignments = json_decode($supervisor->client_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        $current_assignments = array_diff($current_assignments, array(intval($client_id)));

        return $this->update($supervisor_id, array(
            'client_assignments' => json_encode(array_values($current_assignments))
        ));
    }

    public function assign_to_site($supervisor_id, $site_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        $current_assignments = json_decode($supervisor->site_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        if (!in_array($site_id, $current_assignments)) {
            $current_assignments[] = intval($site_id);
        }

        return $this->update($supervisor_id, array(
            'site_assignments' => json_encode(array_values($current_assignments))
        ));
    }

    public function remove_from_site($supervisor_id, $site_id)
    {
        $supervisor = $this->get($supervisor_id);
        if (!$supervisor) {
            return array('success' => false, 'error' => 'Supervisor not found');
        }

        $current_assignments = json_decode($supervisor->site_assignments ?? '[]', true);
        if (!is_array($current_assignments)) {
            $current_assignments = array();
        }

        $current_assignments = array_diff($current_assignments, array(intval($site_id)));

        return $this->update($supervisor_id, array(
            'site_assignments' => json_encode(array_values($current_assignments))
        ));
    }

    public function set_as_default($supervisor_id)
    {
        $this->clear_default_supervisors();
        return $this->update($supervisor_id, array('is_default' => true));
    }

    public function remove_default($supervisor_id)
    {
        return $this->update($supervisor_id, array('is_default' => false));
    }

    public function get_default()
    {
        if (!$this->table) {
            return null;
        }

        $sql = "SELECT * FROM {$this->table} WHERE is_default = TRUE ORDER BY id ASC LIMIT 1";
        return $this->db->get_row($sql);
    }

    public function get_for_client($client_id)
    {
        if (!$this->table) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table} WHERE is_active = TRUE AND client_assignments @> ?::jsonb ORDER BY name ASC";
        $results = $this->db->get_results($sql, array(json_encode(array(intval($client_id)))));
        return is_array($results) ? $results : array();
    }

    public function get_for_site($site_id)
    {
        if (!$this->table) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table} WHERE is_active = TRUE AND site_assignments @> ?::jsonb ORDER BY name ASC";
        $results = $this->db->get_results($sql, array(json_encode(array(intval($site_id)))));
        return is_array($results) ? $results : array();
    }

    public function resolve_supervisor_for_class($class_id, $client_id = null, $site_id = null)
    {
        if ($site_id) {
            $site_supervisors = $this->get_for_site($site_id);
            if (!empty($site_supervisors)) {
                return $site_supervisors[0];
            }
        }

        if ($client_id) {
            $client_supervisors = $this->get_for_client($client_id);
            if (!empty($client_supervisors)) {
                return $client_supervisors[0];
            }
        }

        $default_supervisor = $this->get_default();
        if ($default_supervisor) {
            return $default_supervisor;
        }

        $all_supervisors = $this->get_all(true);
        return !empty($all_supervisors) ? $all_supervisors[0] : null;
    }

    public function get_statistics()
    {
        $all_supervisors = $this->get_all();
        $active_supervisors = $this->get_all(true);

        $stats = array();
        $stats['total'] = is_array($all_supervisors) ? count($all_supervisors) : 0;
        $stats['active'] = is_array($active_supervisors) ? count($active_supervisors) : 0;
        $stats['with_client_assignments'] = $this->count_assignments('client_assignments');
        $stats['with_site_assignments'] = $this->count_assignments('site_assignments');
        $stats['default_supervisor'] = $this->get_default() ? 1 : 0;

        return $stats;
    }

    private function clear_default_supervisors()
    {
        if (!$this->table) {
            return;
        }

        $this->db->query("UPDATE {$this->table} SET is_default = FALSE");
    }

    private function count_assignments($field)
    {
        if (!$this->table) {
            return 0;
        }

        if (!in_array($field, array('client_assignments', 'site_assignments'), true)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$field} IS NOT NULL AND jsonb_array_length({$field}) > 0";
        return intval($this->db->get_var($sql));
    }

    private function email_exists($email, $exclude_id = null)
    {
        if (!$this->table) {
            return false;
        }

        $email = sanitize_email($email);
        if (empty($email)) {
            return false;
        }

        $params = array($email);
        $sql = "SELECT id FROM {$this->table} WHERE email = ?";

        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = intval($exclude_id);
        }

        $sql .= " LIMIT 1";

        $existing = $this->db->get_row($sql, $params);
        return !empty($existing);
    }

    private function has_active_assignments($supervisor_id)
    {
        global $wpdb;

        $classes_table = $wpdb->prefix . 'wecoza_classes';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$classes_table} WHERE project_supervisor_id = %d AND status != 'completed'",
            $supervisor_id
        ));

        return intval($count) > 0;
    }

    private function validate_supervisor_data($data, $exclude_id = null)
    {
        $errors = array();
        $is_new = ($exclude_id === null);

        if ($is_new) {
            if (empty($data['name'])) {
                $errors[] = 'Name is required';
            }

            if (empty($data['email'])) {
                $errors[] = 'Email is required';
            } elseif (!is_email($data['email'])) {
                $errors[] = 'Invalid email format';
            } elseif ($this->email_exists($data['email'])) {
                $errors[] = 'Email address already exists';
            }
        } else {
            if (array_key_exists('name', $data) && empty($data['name'])) {
                $errors[] = 'Name is required';
            }

            if (array_key_exists('email', $data)) {
                if (empty($data['email'])) {
                    $errors[] = 'Email is required';
                } elseif (!is_email($data['email'])) {
                    $errors[] = 'Invalid email format';
                } elseif ($this->email_exists($data['email'], $exclude_id)) {
                    $errors[] = 'Email address already exists';
                }
            }
        }

        if (isset($data['role']) && !empty($data['role'])) {
            $valid_roles = array('supervisor', 'manager', 'admin', 'coordinator');
            if (!in_array($data['role'], $valid_roles)) {
                $errors[] = 'Invalid role specified';
            }
        }

        return empty($errors) ? true : $errors;
    }

    private function sanitize_supervisor_data($data)
    {
        $sanitized = array();

        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }

        if (array_key_exists('role', $data)) {
            $sanitized['role'] = sanitize_text_field($data['role']);
        }

        if (array_key_exists('is_default', $data)) {
            $sanitized['is_default'] = !empty($data['is_default']);
        }

        if (array_key_exists('is_active', $data)) {
            $sanitized['is_active'] = !empty($data['is_active']);
        }

        if (array_key_exists('client_assignments', $data)) {
            $sanitized['client_assignments'] = $this->normalize_assignments($data['client_assignments']);
        }

        if (array_key_exists('site_assignments', $data)) {
            $sanitized['site_assignments'] = $this->normalize_assignments($data['site_assignments']);
        }

        return $sanitized;
    }

    private function normalize_assignments($value)
    {
        if (is_array($value)) {
            return json_encode(array_values(array_map('intval', $value)));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode(array_values(array_map('intval', $decoded)));
            }
        }

        return json_encode(array());
    }

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
