<?php
/**
 * Database service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

/**
 * Database service class - Central database abstraction layer
 */
class DatabaseService
{
    /**
     * WordPress database instance
     */
    private $wpdb;

    /**
     * Table names
     */
    private $tables;

    /**
     * Cache group for transients
     */
    private $cache_group = 'wecoza_db';

    /**
     * Default cache expiration (1 hour)
     */
    private $cache_expiration = 3600;

    /**
     * Transaction depth counter
     */
    private $transaction_depth = 0;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        $this->tables = array(
            'supervisors' => $wpdb->prefix . 'wecoza_supervisors',
            'notification_queue' => $wpdb->prefix . 'wecoza_notification_queue',
            'events_log' => $wpdb->prefix . 'wecoza_events_log',
            'dashboard_status' => $wpdb->prefix . 'wecoza_dashboard_status',
            'audit_log' => $wpdb->prefix . 'wecoza_audit_log',
            'analytics' => $wpdb->prefix . 'wecoza_analytics',
            'template_versions' => $wpdb->prefix . 'wecoza_template_versions'
        );

        // Check database connection
        $this->check_connection();
    }

    /**
     * Check database connection and handle errors
     */
    private function check_connection()
    {
        if (!$this->wpdb || $this->wpdb->last_error) {
            $this->log_error('Database connection failed', array(
                'error' => $this->wpdb->last_error ?? 'Unknown connection error'
            ));

            // Trigger reconnection attempt
            $this->wpdb->check_connection();
        }
    }

    /**
     * Get table name
     */
    public function get_table($table_name)
    {
        return isset($this->tables[$table_name]) ? $this->tables[$table_name] : false;
    }

    /**
     * Execute query with error handling
     */
    public function query($sql, $params = array())
    {
        try {
            if (!empty($params)) {
                $prepared = $this->wpdb->prepare($sql, $params);
                $result = $this->wpdb->query($prepared);
            } else {
                $result = $this->wpdb->query($sql);
            }

            if ($result === false) {
                $this->log_error('Database query failed', array(
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $result;
        } catch (Exception $e) {
            $this->log_error('Database exception', array(
                'message' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params
            ));
            return false;
        }
    }

    /**
     * Get results from query
     */
    public function get_results($sql, $params = array(), $output = OBJECT)
    {
        try {
            if (!empty($params)) {
                $prepared = $this->wpdb->prepare($sql, $params);
                $results = $this->wpdb->get_results($prepared, $output);
            } else {
                $results = $this->wpdb->get_results($sql, $output);
            }

            if ($this->wpdb->last_error) {
                $this->log_error('Database get_results failed', array(
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $results;
        } catch (Exception $e) {
            $this->log_error('Database exception in get_results', array(
                'message' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params
            ));
            return false;
        }
    }

    /**
     * Get single row
     */
    public function get_row($sql, $params = array(), $output = OBJECT)
    {
        try {
            if (!empty($params)) {
                $prepared = $this->wpdb->prepare($sql, $params);
                $result = $this->wpdb->get_row($prepared, $output);
            } else {
                $result = $this->wpdb->get_row($sql, $output);
            }

            if ($this->wpdb->last_error) {
                $this->log_error('Database get_row failed', array(
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $result;
        } catch (Exception $e) {
            $this->log_error('Database exception in get_row', array(
                'message' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params
            ));
            return false;
        }
    }

    /**
     * Get single variable
     */
    public function get_var($sql, $params = array())
    {
        try {
            if (!empty($params)) {
                $prepared = $this->wpdb->prepare($sql, $params);
                $result = $this->wpdb->get_var($prepared);
            } else {
                $result = $this->wpdb->get_var($sql);
            }

            if ($this->wpdb->last_error) {
                $this->log_error('Database get_var failed', array(
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $result;
        } catch (Exception $e) {
            $this->log_error('Database exception in get_var', array(
                'message' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params
            ));
            return false;
        }
    }

    /**
     * Insert data
     */
    public function insert($table, $data, $format = null)
    {
        $table_name = $this->get_table($table);
        if (!$table_name) {
            $this->log_error('Invalid table name', array('table' => $table));
            return false;
        }

        try {
            $result = $this->wpdb->insert($table_name, $data, $format);

            if ($result === false) {
                $this->log_error('Database insert failed', array(
                    'table' => $table,
                    'data' => $data,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            $this->log_error('Database exception in insert', array(
                'message' => $e->getMessage(),
                'table' => $table,
                'data' => $data
            ));
            return false;
        }
    }

    /**
     * Update data
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        $table_name = $this->get_table($table);
        if (!$table_name) {
            $this->log_error('Invalid table name', array('table' => $table));
            return false;
        }

        try {
            $result = $this->wpdb->update($table_name, $data, $where, $format, $where_format);

            if ($result === false) {
                $this->log_error('Database update failed', array(
                    'table' => $table,
                    'data' => $data,
                    'where' => $where,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $result;
        } catch (Exception $e) {
            $this->log_error('Database exception in update', array(
                'message' => $e->getMessage(),
                'table' => $table,
                'data' => $data,
                'where' => $where
            ));
            return false;
        }
    }

    /**
     * Delete data
     */
    public function delete($table, $where, $where_format = null)
    {
        $table_name = $this->get_table($table);
        if (!$table_name) {
            $this->log_error('Invalid table name', array('table' => $table));
            return false;
        }

        try {
            $result = $this->wpdb->delete($table_name, $where, $where_format);

            if ($result === false) {
                $this->log_error('Database delete failed', array(
                    'table' => $table,
                    'where' => $where,
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }

            return $result;
        } catch (Exception $e) {
            $this->log_error('Database exception in delete', array(
                'message' => $e->getMessage(),
                'table' => $table,
                'where' => $where
            ));
            return false;
        }
    }

    /**
     * Start transaction with nested support
     */
    public function start_transaction()
    {
        if ($this->transaction_depth === 0) {
            $result = $this->wpdb->query('START TRANSACTION');
            if ($result === false) {
                $this->log_error('Failed to start transaction', array(
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }
        }
        $this->transaction_depth++;
        return true;
    }

    /**
     * Commit transaction with nested support
     */
    public function commit()
    {
        if ($this->transaction_depth <= 0) {
            $this->log_error('Attempted to commit without active transaction');
            return false;
        }

        $this->transaction_depth--;

        if ($this->transaction_depth === 0) {
            $result = $this->wpdb->query('COMMIT');
            if ($result === false) {
                $this->log_error('Failed to commit transaction', array(
                    'error' => $this->wpdb->last_error
                ));
                return false;
            }
        }
        return true;
    }

    /**
     * Rollback transaction with nested support
     */
    public function rollback()
    {
        if ($this->transaction_depth <= 0) {
            $this->log_error('Attempted to rollback without active transaction');
            return false;
        }

        $this->transaction_depth = 0; // Reset depth on rollback
        $result = $this->wpdb->query('ROLLBACK');

        if ($result === false) {
            $this->log_error('Failed to rollback transaction', array(
                'error' => $this->wpdb->last_error
            ));
            return false;
        }
        return true;
    }

    /**
     * Execute transaction with automatic rollback on error
     */
    public function transaction($callback)
    {
        $started_transaction = $this->start_transaction();
        if (!$started_transaction) {
            return false;
        }

        try {
            $result = call_user_func($callback, $this);

            if ($result === false) {
                $this->rollback();
                $this->log_error('Transaction callback returned false');
                return false;
            }

            if (!$this->commit()) {
                $this->rollback();
                return false;
            }

            return $result;
        } catch (Exception $e) {
            $this->rollback();
            $this->log_error('Transaction failed with exception', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return false;
        }
    }

    /**
     * Get current transaction depth
     */
    public function get_transaction_depth()
    {
        return $this->transaction_depth;
    }

    /**
     * Check if event exists (idempotency check)
     */
    public function event_exists($idempotency_key)
    {
        $sql = "SELECT id FROM {$this->tables['events_log']} WHERE idempotency_key = %s";
        $result = $this->get_var($sql, array($idempotency_key));
        return !is_null($result);
    }

    /**
     * Log event
     */
    public function log_event($event_data)
    {
        $data = array(
            'event_name' => $event_data['event'],
            'event_payload' => json_encode($event_data),
            'class_id' => isset($event_data['class_id']) ? $event_data['class_id'] : null,
            'actor_id' => isset($event_data['actor_id']) ? $event_data['actor_id'] : null,
            'idempotency_key' => $event_data['idempotency_key'],
            'occurred_at' => isset($event_data['occurred_at']) ? $event_data['occurred_at'] : current_time('mysql'),
            'processed' => 0
        );

        return $this->insert('events_log', $data);
    }

    /**
     * Mark event as processed
     */
    public function mark_event_processed($event_id)
    {
        return $this->update(
            'events_log',
            array(
                'processed' => 1,
                'processed_at' => current_time('mysql')
            ),
            array('id' => $event_id)
        );
    }

    /**
     * Get unprocessed events
     */
    public function get_unprocessed_events($limit = 50)
    {
        $sql = "SELECT * FROM {$this->tables['events_log']}
                WHERE processed = 0
                ORDER BY occurred_at ASC
                LIMIT %d";

        return $this->get_results($sql, array($limit));
    }

    /**
     * Add notification to queue
     */
    public function queue_notification($notification_data)
    {
        $data = array(
            'event_name' => $notification_data['event_name'],
            'idempotency_key' => $notification_data['idempotency_key'],
            'recipient_email' => $notification_data['recipient_email'],
            'recipient_name' => isset($notification_data['recipient_name']) ? $notification_data['recipient_name'] : '',
            'channel' => isset($notification_data['channel']) ? $notification_data['channel'] : 'email',
            'template_name' => $notification_data['template_name'],
            'payload' => json_encode($notification_data['payload']),
            'status' => 'pending',
            'scheduled_at' => isset($notification_data['scheduled_at']) ? $notification_data['scheduled_at'] : current_time('mysql')
        );

        return $this->insert('notification_queue', $data);
    }

    /**
     * Get pending notifications
     */
    public function get_pending_notifications($limit = 50)
    {
        $sql = "SELECT * FROM {$this->tables['notification_queue']}
                WHERE status = 'pending'
                AND scheduled_at <= %s
                AND attempts < max_attempts
                ORDER BY scheduled_at ASC
                LIMIT %d";

        return $this->get_results($sql, array(current_time('mysql'), $limit));
    }

    /**
     * Update notification status
     */
    public function update_notification_status($notification_id, $status, $error = null)
    {
        $notification_id = SecurityService::sanitize_int($notification_id);
        $status = SecurityService::sanitize_text($status);

        if ($notification_id <= 0 || empty($status)) {
            return false;
        }

        // Validate status against allowed values
        $allowed_statuses = array('pending', 'sent', 'failed', 'cancelled');
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }

        // Use raw SQL to increment attempts
        $sql = "UPDATE `{$this->tables['notification_queue']}`
                SET `status` = %s, `attempts` = `attempts` + 1";

        $params = array($status);

        if ($status === 'sent') {
            $sql .= ", `sent_at` = %s";
            $params[] = current_time('mysql');
        }

        if ($error) {
            $sql .= ", `last_error` = %s";
            $params[] = SecurityService::sanitize_text($error);
        }

        $sql .= " WHERE `id` = %d";
        $params[] = $notification_id;

        return $this->query($sql, $params);
    }

    /**
     * Get notification by ID
     */
    public function get_notification_by_id($notification_id)
    {
        $sql = "SELECT * FROM {$this->tables['notification_queue']} WHERE id = %d";
        return $this->get_row($sql, array($notification_id));
    }

    /**
     * Clean up old records
     */
    public function cleanup_old_records($days = 30)
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Clean up sent notifications
        $sql = "DELETE FROM {$this->tables['notification_queue']}
                WHERE status = 'sent' AND sent_at < %s";
        $this->query($sql, array($cutoff_date));

        // Clean up old event logs
        $sql = "DELETE FROM {$this->tables['events_log']}
                WHERE processed = 1 AND processed_at < %s";
        $this->query($sql, array($cutoff_date));

        return true;
    }

    /**
     * Cache management methods
     */

    /**
     * Get cached result
     */
    public function get_cache($key)
    {
        return get_transient($this->cache_group . '_' . $key);
    }

    /**
     * Set cached result
     */
    public function set_cache($key, $data, $expiration = null)
    {
        if ($expiration === null) {
            $expiration = $this->cache_expiration;
        }
        return set_transient($this->cache_group . '_' . $key, $data, $expiration);
    }

    /**
     * Delete cached result
     */
    public function delete_cache($key)
    {
        return delete_transient($this->cache_group . '_' . $key);
    }

    /**
     * Clear all plugin cache
     */
    public function clear_cache()
    {
        global $wpdb;

        // Delete all transients with our cache group prefix
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
            '_transient_' . $this->cache_group . '_%'
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
            '_transient_timeout_' . $this->cache_group . '_%'
        ));

        return true;
    }

    /**
     * Get cached results with fallback to database
     */
    public function get_results_cached($sql, $params = array(), $cache_key = null, $expiration = null)
    {
        // Generate cache key if not provided
        if ($cache_key === null) {
            $cache_key = 'query_' . md5($sql . serialize($params));
        }

        // Try to get from cache first
        $cached_result = $this->get_cache($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Get from database and cache the result
        $result = $this->get_results($sql, $params);
        if ($result !== false) {
            $this->set_cache($cache_key, $result, $expiration);
        }

        return $result;
    }

    /**
     * Get cached row with fallback to database
     */
    public function get_row_cached($sql, $params = array(), $cache_key = null, $expiration = null)
    {
        // Generate cache key if not provided
        if ($cache_key === null) {
            $cache_key = 'row_' . md5($sql . serialize($params));
        }

        // Try to get from cache first
        $cached_result = $this->get_cache($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Get from database and cache the result
        $result = $this->get_row($sql, $params);
        if ($result !== false) {
            $this->set_cache($cache_key, $result, $expiration);
        }

        return $result;
    }

    /**
     * Get cached variable with fallback to database
     */
    public function get_var_cached($sql, $params = array(), $cache_key = null, $expiration = null)
    {
        // Generate cache key if not provided
        if ($cache_key === null) {
            $cache_key = 'var_' . md5($sql . serialize($params));
        }

        // Try to get from cache first
        $cached_result = $this->get_cache($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Get from database and cache the result
        $result = $this->get_var($sql, $params);
        if ($result !== false) {
            $this->set_cache($cache_key, $result, $expiration);
        }

        return $result;
    }

    /**
     * Invalidate cache for table operations
     */
    private function invalidate_table_cache($table)
    {
        // Clear cache patterns that might be affected by changes to this table
        $patterns = array(
            $table . '_',
            'stats_',
            'list_' . $table,
        );

        foreach ($patterns as $pattern) {
            // This is a simple implementation - in production you might want
            // a more sophisticated cache tagging system
            $this->delete_cache($pattern . 'all');
            $this->delete_cache($pattern . 'count');
        }
    }

    /**
     * Enhanced insert with cache invalidation
     */
    public function insert_with_cache_invalidation($table, $data, $format = null)
    {
        $result = $this->insert($table, $data, $format);
        if ($result !== false) {
            $this->invalidate_table_cache($table);
        }
        return $result;
    }

    /**
     * Enhanced update with cache invalidation
     */
    public function update_with_cache_invalidation($table, $data, $where, $format = null, $where_format = null)
    {
        $result = $this->update($table, $data, $where, $format, $where_format);
        if ($result !== false) {
            $this->invalidate_table_cache($table);
        }
        return $result;
    }

    /**
     * Enhanced delete with cache invalidation
     */
    public function delete_with_cache_invalidation($table, $where, $where_format = null)
    {
        $result = $this->delete($table, $where, $where_format);
        if ($result !== false) {
            $this->invalidate_table_cache($table);
        }
        return $result;
    }

    /**
     * Get database performance statistics
     */
    public function get_performance_stats()
    {
        return array(
            'queries' => $this->wpdb->num_queries,
            'last_error' => $this->wpdb->last_error,
            'transaction_depth' => $this->transaction_depth,
            'cache_hits' => wp_cache_get_stats(),
        );
    }

    /**
     * Log error
     */
    private function log_error($message, $context = array())
    {
        if (function_exists('error_log')) {
            $log_message = "WECOZA Notifications Database Error: {$message}";
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            error_log($log_message);
        }

        // Also log to security service if available
        if (class_exists('\WecozaNotifications\SecurityService')) {
            SecurityService::log_security_event('database_error', array(
                'message' => $message,
                'context' => $context
            ));
        }
    }

    /**
     * Supervisor management methods
     */

    /**
     * Create supervisor
     */
    public function create_supervisor($data)
    {
        // Validate and sanitize input data
        $sanitized_data = array(
            'name' => SecurityService::sanitize_text($data['name'] ?? ''),
            'email' => SecurityService::sanitize_email($data['email'] ?? ''),
            'role' => SecurityService::sanitize_text($data['role'] ?? 'supervisor'),
            'is_default' => SecurityService::sanitize_int($data['is_default'] ?? 0),
            'client_assignments' => SecurityService::sanitize_json_for_db($data['client_assignments'] ?? array()),
            'site_assignments' => SecurityService::sanitize_json_for_db($data['site_assignments'] ?? array()),
            'active' => SecurityService::sanitize_int($data['active'] ?? 1)
        );

        // Validate required fields
        if (empty($sanitized_data['name']) || empty($sanitized_data['email'])) {
            return false;
        }

        $sql = "INSERT INTO `{$this->tables['supervisors']}`
                (`name`, `email`, `role`, `is_default`, `client_assignments`, `site_assignments`, `active`, `created_at`, `updated_at`)
                VALUES (%s, %s, %s, %d, %s, %s, %d, %s, %s)";

        $params = array(
            $sanitized_data['name'],
            $sanitized_data['email'],
            $sanitized_data['role'],
            $sanitized_data['is_default'],
            $sanitized_data['client_assignments'],
            $sanitized_data['site_assignments'],
            $sanitized_data['active'],
            current_time('mysql'),
            current_time('mysql')
        );

        $result = $this->query($sql, $params);
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get supervisor by ID
     */
    public function get_supervisor_by_id($supervisor_id)
    {
        $sql = "SELECT * FROM {$this->tables['supervisors']} WHERE id = %d";
        return $this->get_row($sql, array($supervisor_id));
    }

    /**
     * Get supervisor by email
     */
    public function get_supervisor_by_email($email)
    {
        $sql = "SELECT * FROM {$this->tables['supervisors']} WHERE email = %s";
        return $this->get_row($sql, array($email));
    }

    /**
     * Get all supervisors
     */
    public function get_all_supervisors($active_only = false)
    {
        $sql = "SELECT * FROM {$this->tables['supervisors']}";

        if ($active_only) {
            $sql .= " WHERE active = 1";
        }

        $sql .= " ORDER BY name ASC";

        return $this->get_results($sql);
    }

    /**
     * Update supervisor
     */
    public function update_supervisor($supervisor_id, $data)
    {
        $supervisor_id = SecurityService::sanitize_int($supervisor_id);
        if ($supervisor_id <= 0) {
            return false;
        }

        $allowed_fields = array('name', 'email', 'phone', 'client_assignments', 'site_assignments', 'is_default', 'is_active');
        $set_clauses = array();
        $params = array();

        foreach ($data as $field => $value) {
            // Validate field name against whitelist
            if (!in_array($field, $allowed_fields) || !SecurityService::validate_column_name($field)) {
                continue;
            }
            $set_clauses[] = "`{$field}` = %s";
            $params[] = SecurityService::sanitize_text($value);
        }

        if (empty($set_clauses)) {
            return false;
        }

        $set_clauses[] = "`updated_at` = %s";
        $params[] = current_time('mysql');

        $sql = "UPDATE `{$this->tables['supervisors']}` SET " . implode(', ', $set_clauses) . " WHERE `id` = %d";
        $params[] = $supervisor_id;

        return $this->query($sql, $params);
    }

    /**
     * Delete supervisor
     */
    public function delete_supervisor($supervisor_id)
    {
        $sql = "DELETE FROM {$this->tables['supervisors']} WHERE id = %d";
        return $this->query($sql, array($supervisor_id));
    }

    /**
     * Clear default supervisors
     */
    public function clear_default_supervisors()
    {
        $sql = "UPDATE {$this->tables['supervisors']} SET is_default = 0";
        return $this->query($sql);
    }

    /**
     * Get default supervisor
     */
    public function get_default_supervisor()
    {
        $sql = "SELECT * FROM {$this->tables['supervisors']} WHERE is_default = 1 AND active = 1 LIMIT 1";
        return $this->get_row($sql);
    }

    /**
     * Get supervisors for client
     */
    public function get_supervisors_for_client($client_id)
    {
        $client_id = SecurityService::sanitize_int($client_id);
        if ($client_id <= 0) {
            return array();
        }

        $sql = "SELECT * FROM `{$this->tables['supervisors']}`
                WHERE `active` = 1
                AND JSON_CONTAINS(`client_assignments`, %s)
                ORDER BY `name` ASC";

        $search_value = wp_json_encode(array($client_id));
        return $this->get_results($sql, array($search_value));
    }

    /**
     * Get supervisors for site
     */
    public function get_supervisors_for_site($site_id)
    {
        $site_id = SecurityService::sanitize_int($site_id);
        if ($site_id <= 0) {
            return array();
        }

        $sql = "SELECT * FROM `{$this->tables['supervisors']}`
                WHERE `active` = 1
                AND JSON_CONTAINS(`site_assignments`, %s)
                ORDER BY `name` ASC";

        $search_value = wp_json_encode(array($site_id));
        return $this->get_results($sql, array($search_value));
    }

    /**
     * Count supervisors with client assignments
     */
    public function count_supervisors_with_client_assignments()
    {
        $sql = "SELECT COUNT(*) FROM `{$this->tables['supervisors']}`
                WHERE `client_assignments` != %s AND `client_assignments` IS NOT NULL";
        return $this->get_var($sql, array('[]'));
    }

    /**
     * Count supervisors with site assignments
     */
    public function count_supervisors_with_site_assignments()
    {
        $sql = "SELECT COUNT(*) FROM `{$this->tables['supervisors']}`
                WHERE `site_assignments` != %s AND `site_assignments` IS NOT NULL";
        return $this->get_var($sql, array('[]'));
    }

    /**
     * Get database statistics
     */
    public function get_statistics()
    {
        $stats = array();

        // Queue statistics
        $stats['queue'] = array(
            'pending' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['notification_queue']}` WHERE `status` = %s", array('pending')),
            'sent' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['notification_queue']}` WHERE `status` = %s", array('sent')),
            'failed' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['notification_queue']}` WHERE `status` = %s", array('failed'))
        );

        // Event statistics
        $stats['events'] = array(
            'total' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['events_log']}`"),
            'processed' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['events_log']}` WHERE `processed` = %d", array(1)),
            'pending' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['events_log']}` WHERE `processed` = %d", array(0))
        );

        // Supervisor statistics
        $stats['supervisors'] = array(
            'total' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['supervisors']}`"),
            'active' => $this->get_var("SELECT COUNT(*) FROM `{$this->tables['supervisors']}` WHERE `active` = %d", array(1))
        );

        return $stats;
    }
}