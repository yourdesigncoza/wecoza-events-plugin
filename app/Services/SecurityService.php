<?php
/**
 * Security service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security service class
 * Provides security utilities for the plugin
 */
class SecurityService
{
    /**
     * Plugin capabilities
     */
    const CAP_MANAGE_NOTIFICATIONS = 'wecoza_manage_notifications';
    const CAP_VIEW_REPORTS = 'wecoza_view_reports';
    const CAP_MANAGE_SUPERVISORS = 'wecoza_manage_supervisors';
    const CAP_MANAGE_TEMPLATES = 'wecoza_manage_templates';
    const CAP_VIEW_ANALYTICS = 'wecoza_view_analytics';
    const CAP_MANAGE_SETTINGS = 'wecoza_manage_settings';

    /**
     * Initialize security hooks
     */
    public function __construct()
    {
        add_action('init', array($this, 'setup_capabilities'));
        add_action('wp_loaded', array($this, 'register_capabilities'));
    }

    /**
     * Setup plugin capabilities
     */
    public function setup_capabilities()
    {
        $this->register_capabilities();
    }

    /**
     * Register custom capabilities
     */
    public function register_capabilities()
    {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap(self::CAP_MANAGE_NOTIFICATIONS);
            $admin_role->add_cap(self::CAP_VIEW_REPORTS);
            $admin_role->add_cap(self::CAP_MANAGE_SUPERVISORS);
            $admin_role->add_cap(self::CAP_MANAGE_TEMPLATES);
            $admin_role->add_cap(self::CAP_VIEW_ANALYTICS);
            $admin_role->add_cap(self::CAP_MANAGE_SETTINGS);
        }

        // Add capabilities to editor role for viewing reports only
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap(self::CAP_VIEW_REPORTS);
            $editor_role->add_cap(self::CAP_VIEW_ANALYTICS);
        }
    }

    /**
     * Check if current user has capability
     */
    public static function current_user_can($capability)
    {
        return current_user_can($capability);
    }

    /**
     * Generate nonce for action
     */
    public static function create_nonce($action)
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify nonce for action
     */
    public static function verify_nonce($nonce, $action)
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Check AJAX nonce
     */
    public static function check_ajax_nonce($action, $nonce_key = '_wpnonce')
    {
        if (!isset($_POST[$nonce_key])) {
            wp_die(__('Security check failed.', 'wecoza-notifications'), __('Security Error', 'wecoza-notifications'), array('response' => 403));
        }

        if (!wp_verify_nonce($_POST[$nonce_key], $action)) {
            wp_die(__('Security check failed.', 'wecoza-notifications'), __('Security Error', 'wecoza-notifications'), array('response' => 403));
        }

        return true;
    }

    /**
     * Sanitize text input
     */
    public static function sanitize_text($input)
    {
        if (is_array($input)) {
            return array_map(array(__CLASS__, 'sanitize_text'), $input);
        }
        return sanitize_text_field($input);
    }

    /**
     * Sanitize email input
     */
    public static function sanitize_email($email)
    {
        return sanitize_email($email);
    }

    /**
     * Sanitize integer input
     */
    public static function sanitize_int($input)
    {
        return intval($input);
    }

    /**
     * Sanitize array of integers
     */
    public static function sanitize_int_array($input)
    {
        if (!is_array($input)) {
            return array();
        }
        return array_map('intval', $input);
    }

    /**
     * Sanitize HTML content
     */
    public static function sanitize_html($input)
    {
        return wp_kses_post($input);
    }

    /**
     * Escape HTML output
     */
    public static function escape_html($output)
    {
        if (is_array($output)) {
            return array_map(array(__CLASS__, 'escape_html'), $output);
        }
        return esc_html($output);
    }

    /**
     * Escape HTML attributes
     */
    public static function escape_attr($output)
    {
        return esc_attr($output);
    }

    /**
     * Escape URLs
     */
    public static function escape_url($url)
    {
        return esc_url($url);
    }

    /**
     * Validate table name
     */
    public static function validate_table_name($table_name)
    {
        // Only allow alphanumeric characters, underscores, and dashes
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $table_name)) {
            return false;
        }
        return true;
    }

    /**
     * Validate column name
     */
    public static function validate_column_name($column_name)
    {
        // Only allow alphanumeric characters, underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column_name)) {
            return false;
        }
        return true;
    }

    /**
     * Sanitize SQL ORDER BY clause
     */
    public static function sanitize_order_by($order_by, $allowed_columns = array())
    {
        $order_by = trim($order_by);

        // Split column and direction
        $parts = explode(' ', $order_by);
        $column = $parts[0];
        $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';

        // Validate column name
        if (!self::validate_column_name($column)) {
            return 'id ASC';
        }

        // Check if column is in allowed list
        if (!empty($allowed_columns) && !in_array($column, $allowed_columns)) {
            return 'id ASC';
        }

        // Validate direction
        if (!in_array($direction, array('ASC', 'DESC'))) {
            $direction = 'ASC';
        }

        return $column . ' ' . $direction;
    }

    /**
     * Generate secure random token
     */
    public static function generate_token($length = 32)
    {
        return wp_generate_password($length, false);
    }

    /**
     * Rate limit check
     */
    public static function check_rate_limit($action, $max_attempts = 10, $time_window = 3600)
    {
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        $transient_key = 'wecoza_rate_limit_' . $action . '_' . $user_id . '_' . md5($ip_address);

        $attempts = get_transient($transient_key);
        if ($attempts === false) {
            $attempts = 0;
        }

        if ($attempts >= $max_attempts) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, $time_window);
        return true;
    }

    /**
     * Get client IP address securely
     */
    public static function get_client_ip()
    {
        $ip_fields = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];

                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Log security event
     */
    public static function log_security_event($event_type, $details = array())
    {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'details' => $details
        );

        error_log('WECOZA Security Event: ' . json_encode($log_data));

        // Also trigger a hook for external logging systems
        do_action('wecoza_security_event', $event_type, $log_data);
    }

    /**
     * Validate JSON input
     */
    public static function validate_json($json_string)
    {
        if (empty($json_string)) {
            return array();
        }

        $decoded = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $decoded;
    }

    /**
     * Sanitize JSON data for database storage
     */
    public static function sanitize_json_for_db($data)
    {
        if (is_array($data)) {
            $data = array_map(array(__CLASS__, 'sanitize_text'), $data);
        }
        return wp_json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Check if request is from admin area
     */
    public static function is_admin_request()
    {
        return is_admin() && !wp_doing_ajax();
    }

    /**
     * Check if request is AJAX
     */
    public static function is_ajax_request()
    {
        return wp_doing_ajax();
    }

    /**
     * Check if request is from frontend
     */
    public static function is_frontend_request()
    {
        return !is_admin() && !wp_doing_ajax() && !wp_doing_cron();
    }
}