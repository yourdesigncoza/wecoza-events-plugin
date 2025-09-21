<?php
/**
 * PostgreSQL Database service for WECOZA Events Plugin
 * Integrates with existing WeCoza Classes PostgreSQL database
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

/**
 * PostgreSQL Database service class - Central database abstraction layer
 */
class PostgreSQLDatabaseService
{
    /**
     * PDO instance
     */
    private $pdo;

    /**
     * Table names in events schema
     */
    private $tables;

    /**
     * Cache group for transients
     */
    private $cache_group = 'wecoza_events_db';

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
     * Database connection settings
     */
    private $connection_settings;

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
        $this->load_connection_settings();
        $this->setup_tables();
        // Don't connect immediately - use lazy connection
    }

    /**
     * Load PostgreSQL connection settings
     */
    private function load_connection_settings()
    {
        $this->connection_settings = array(
            'host' => get_option('wecoza_postgres_host', 'db-wecoza-3-do-user-17263152-0.m.db.ondigitalocean.com'),
            'port' => get_option('wecoza_postgres_port', '25060'),
            'dbname' => get_option('wecoza_postgres_dbname', 'defaultdb'),
            'user' => get_option('wecoza_postgres_user', 'doadmin'),
            'password' => get_option('wecoza_postgres_password', ''),
            'sslmode' => get_option('wecoza_postgres_sslmode', 'require')
        );

        if (empty($this->connection_settings['password'])) {
            $this->log_error('PostgreSQL password not configured', array(
                'option' => 'wecoza_postgres_password'
            ));
        }
    }

    /**
     * Connect to PostgreSQL database
     */
    private function connect()
    {
        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $this->connection_settings['host'],
                $this->connection_settings['port'],
                $this->connection_settings['dbname'],
                $this->connection_settings['sslmode']
            );

            $this->pdo = new \PDO(
                $dsn,
                $this->connection_settings['user'],
                $this->connection_settings['password'],
                array(
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_TIMEOUT => 30
                )
            );

            // Set search path to include events schema
            $this->pdo->exec("SET search_path TO wecoza_events, public");

        } catch (\PDOException $e) {
            $this->log_error('PostgreSQL connection failed', array(
                'error' => $e->getMessage(),
                'host' => $this->connection_settings['host'],
                'port' => $this->connection_settings['port'],
                'dbname' => $this->connection_settings['dbname']
            ));
            $this->pdo = null;
            return false;
        }
        return true;
    }

    /**
     * Ensure database connection exists
     */
    private function ensure_connection()
    {
        if ($this->pdo === null) {
            if (!$this->connect()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Setup table names for events schema
     */
    private function setup_tables()
    {
        $this->tables = array(
            'supervisors' => 'wecoza_events.supervisors',
            'notification_queue' => 'wecoza_events.notification_queue',
            'events_log' => 'wecoza_events.events_log',
            'dashboard_status' => 'wecoza_events.dashboard_status',
            'audit_log' => 'wecoza_events.audit_log',
            'analytics' => 'wecoza_events.analytics',
            'template_versions' => 'wecoza_events.template_versions',
            // Classes table from main schema
            'classes' => 'public.classes'
        );
    }

    /**
     * Check database connection and handle errors
     */
    private function check_connection()
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            $this->log_error('Database connection check failed', array(
                'error' => $e->getMessage()
            ));
            $this->pdo = null;
            return false;
        }
    }

    /**
     * Reconnect to database
     */
    private function reconnect()
    {
        $this->pdo = null;
        return $this->connect();
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
        if (!$this->ensure_connection()) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                $this->log_error('Database query failed', array(
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $stmt->errorInfo()
                ));
                return false;
            }

            return $stmt;
        } catch (\PDOException $e) {
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
    public function get_results($sql, $params = array())
    {
        try {
            $stmt = $this->query($sql, $params);
            if ($stmt === false) {
                return false;
            }

            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            $this->log_error('Database get_results failed', array(
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
    public function get_row($sql, $params = array())
    {
        try {
            $stmt = $this->query($sql, $params);
            if ($stmt === false) {
                return false;
            }

            $result = $stmt->fetch(\PDO::FETCH_OBJ);
            return $result === false ? null : $result;
        } catch (\PDOException $e) {
            $this->log_error('Database get_row failed', array(
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
            $stmt = $this->query($sql, $params);
            if ($stmt === false) {
                return false;
            }

            $result = $stmt->fetchColumn(0);
            return $result === false ? null : $result;
        } catch (\PDOException $e) {
            $this->log_error('Database get_var failed', array(
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
            if (!$this->ensure_connection()) {
                $this->log_error('Database insert failed: no active connection', array(
                    'table' => $table,
                    'data' => $data
                ));
                return false;
            }

            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
                $table_name,
                implode(', ', array_map(function($col) { return '"' . $col . '"'; }, $columns)),
                implode(', ', $placeholders)
            );

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));

            if (!$result) {
                $this->log_error('Database insert failed', array(
                    'table' => $table,
                    'data' => $data,
                    'error' => $stmt->errorInfo()
                ));
                return false;
            }

            // Get the inserted ID
            $insertedId = $stmt->fetchColumn(0);
            return $insertedId !== false ? $insertedId : true;

        } catch (\PDOException $e) {
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
            if (!$this->ensure_connection()) {
                $this->log_error('Database update failed: no active connection', array(
                    'table' => $table,
                    'data' => $data,
                    'where' => $where
                ));
                return false;
            }

            $set_clauses = array();
            $params = array();

            foreach ($data as $column => $value) {
                $set_clauses[] = '"' . $column . '" = ?';
                $params[] = $value;
            }

            $where_clauses = array();
            foreach ($where as $column => $value) {
                $where_clauses[] = '"' . $column . '" = ?';
                $params[] = $value;
            }

            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s',
                $table_name,
                implode(', ', $set_clauses),
                implode(' AND ', $where_clauses)
            );

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                $this->log_error('Database update failed', array(
                    'table' => $table,
                    'data' => $data,
                    'where' => $where,
                    'error' => $stmt->errorInfo()
                ));
                return false;
            }

            return $stmt->rowCount();

        } catch (\PDOException $e) {
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
            if (!$this->ensure_connection()) {
                $this->log_error('Database delete failed: no active connection', array(
                    'table' => $table,
                    'where' => $where
                ));
                return false;
            }

            $where_clauses = array();
            $params = array();

            foreach ($where as $column => $value) {
                $where_clauses[] = '"' . $column . '" = ?';
                $params[] = $value;
            }

            $sql = sprintf(
                'DELETE FROM %s WHERE %s',
                $table_name,
                implode(' AND ', $where_clauses)
            );

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                $this->log_error('Database delete failed', array(
                    'table' => $table,
                    'where' => $where,
                    'error' => $stmt->errorInfo()
                ));
                return false;
            }

            return $stmt->rowCount();

        } catch (\PDOException $e) {
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
        try {
            if ($this->transaction_depth === 0) {
                $result = $this->pdo->beginTransaction();
                if (!$result) {
                    $this->log_error('Failed to start transaction');
                    return false;
                }
            } else {
                // Use savepoints for nested transactions
                $savepoint_name = 'sp_' . $this->transaction_depth;
                $result = $this->pdo->exec("SAVEPOINT $savepoint_name");
                if ($result === false) {
                    $this->log_error('Failed to create savepoint', array('savepoint' => $savepoint_name));
                    return false;
                }
            }
            $this->transaction_depth++;
            return true;
        } catch (\PDOException $e) {
            $this->log_error('Transaction start failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Commit transaction with nested support
     */
    public function commit()
    {
        try {
            if ($this->transaction_depth <= 0) {
                $this->log_error('Attempted to commit without active transaction');
                return false;
            }

            $this->transaction_depth--;

            if ($this->transaction_depth === 0) {
                $result = $this->pdo->commit();
                if (!$result) {
                    $this->log_error('Failed to commit transaction');
                    return false;
                }
            } else {
                // Release savepoint for nested transactions
                $savepoint_name = 'sp_' . $this->transaction_depth;
                $result = $this->pdo->exec("RELEASE SAVEPOINT $savepoint_name");
                if ($result === false) {
                    $this->log_error('Failed to release savepoint', array('savepoint' => $savepoint_name));
                    return false;
                }
            }
            return true;
        } catch (\PDOException $e) {
            $this->log_error('Transaction commit failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Rollback transaction with nested support
     */
    public function rollback()
    {
        try {
            if ($this->transaction_depth <= 0) {
                $this->log_error('Attempted to rollback without active transaction');
                return false;
            }

            if ($this->transaction_depth === 1) {
                $result = $this->pdo->rollback();
                if (!$result) {
                    $this->log_error('Failed to rollback transaction');
                    return false;
                }
                $this->transaction_depth = 0;
            } else {
                // Rollback to savepoint for nested transactions
                $savepoint_name = 'sp_' . ($this->transaction_depth - 1);
                $result = $this->pdo->exec("ROLLBACK TO SAVEPOINT $savepoint_name");
                if ($result === false) {
                    $this->log_error('Failed to rollback to savepoint', array('savepoint' => $savepoint_name));
                    return false;
                }
                $this->transaction_depth--;
            }
            return true;
        } catch (\PDOException $e) {
            $this->log_error('Transaction rollback failed', array('error' => $e->getMessage()));
            $this->transaction_depth = 0; // Reset on error
            return false;
        }
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
        } catch (\Exception $e) {
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
     * Events Plugin specific methods
     */

    /**
     * Check if event exists (idempotency check)
     */
    public function event_exists($idempotency_key)
    {
        $sql = "SELECT id FROM {$this->tables['events_log']} WHERE idempotency_key = ?";
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
            'event_payload' => SecurityService::sanitize_json_for_db($event_data),
            'class_id' => isset($event_data['class_id']) ? SecurityService::sanitize_int($event_data['class_id']) : null,
            'actor_id' => isset($event_data['actor_id']) ? SecurityService::sanitize_int($event_data['actor_id']) : null,
            'idempotency_key' => $event_data['idempotency_key'],
            'occurred_at' => isset($event_data['occurred_at']) ? $event_data['occurred_at'] : date('Y-m-d H:i:s'),
            'processed' => false
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
                'processed' => true,
                'processed_at' => date('Y-m-d H:i:s')
            ),
            array('id' => SecurityService::sanitize_int($event_id))
        );
    }

    /**
     * Get unprocessed events
     */
    public function get_unprocessed_events($limit = 50)
    {
        $sql = "SELECT * FROM {$this->tables['events_log']}
                WHERE processed = false
                ORDER BY occurred_at ASC
                LIMIT ?";

        return $this->get_results($sql, array(SecurityService::sanitize_int($limit)));
    }

    /**
     * Add notification to queue
     */
    public function queue_notification($notification_data)
    {
        $data = array(
            'event_name' => SecurityService::sanitize_text($notification_data['event_name']),
            'idempotency_key' => SecurityService::sanitize_text($notification_data['idempotency_key']),
            'recipient_email' => SecurityService::sanitize_email($notification_data['recipient_email']),
            'recipient_name' => SecurityService::sanitize_text($notification_data['recipient_name'] ?? ''),
            'channel' => SecurityService::sanitize_text($notification_data['channel'] ?? 'email'),
            'template_name' => SecurityService::sanitize_text($notification_data['template_name']),
            'payload' => SecurityService::sanitize_json_for_db($notification_data['payload']),
            'status' => 'pending',
            'scheduled_at' => isset($notification_data['scheduled_at']) ? $notification_data['scheduled_at'] : date('Y-m-d H:i:s')
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
                AND scheduled_at <= CURRENT_TIMESTAMP
                AND attempts < max_attempts
                ORDER BY scheduled_at ASC
                LIMIT ?";

        return $this->get_results($sql, array(SecurityService::sanitize_int($limit)));
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

        $data = array(
            'status' => $status,
            'attempts' => new \stdClass() // Will be incremented in SQL
        );

        if ($status === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }

        if ($error) {
            $data['last_error'] = SecurityService::sanitize_text($error);
        }

        // Use raw SQL to increment attempts
        $sql = "UPDATE {$this->tables['notification_queue']}
                SET status = ?, attempts = attempts + 1";

        $params = array($status);

        if ($status === 'sent') {
            $sql .= ", sent_at = ?";
            $params[] = date('Y-m-d H:i:s');
        }

        if ($error) {
            $sql .= ", last_error = ?";
            $params[] = SecurityService::sanitize_text($error);
        }

        $sql .= " WHERE id = ?";
        $params[] = $notification_id;

        $stmt = $this->query($sql, $params);
        return $stmt !== false;
    }

    /**
     * Get class details from classes table
     */
    public function get_class_details($class_id)
    {
        $class_id = SecurityService::sanitize_int($class_id);
        if ($class_id <= 0) {
            return false;
        }

        $cache_key = 'class_details_' . $class_id;
        $sql = "SELECT
                    class_id,
                    client_id,
                    site_id,
                    class_subject as class_name,
                    class_code,
                    class_agent,
                    project_supervisor_id as supervisor_id,
                    learner_ids,
                    created_at
                FROM {$this->tables['classes']}
                WHERE class_id = ?";

        return $this->get_row_cached($sql, array($class_id), $cache_key, 3600);
    }

    /**
     * Get database performance statistics
     */
    public function get_performance_stats()
    {
        return array(
            'connection_status' => $this->pdo ? 'connected' : 'disconnected',
            'transaction_depth' => $this->transaction_depth,
            'cache_hits' => wp_cache_get_stats(),
            'server_version' => $this->get_var('SELECT version()'),
        );
    }

    /**
     * Log error
     */
    private function log_error($message, $context = array())
    {
        if (function_exists('error_log')) {
            $log_message = "WECOZA Events PostgreSQL Error: {$message}";
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            error_log($log_message);
        }

        // Also log to security service if available
        if (class_exists('\\WecozaNotifications\\SecurityService')) {
            SecurityService::log_security_event('database_error', array(
                'message' => $message,
                'context' => $context
            ));
        }
    }
}