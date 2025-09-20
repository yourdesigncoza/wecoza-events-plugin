<?php
/**
 * Audit service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

class AuditService
{
    private $db_service;

    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';

    const ACTION_EMAIL_SENT = 'email_sent';
    const ACTION_EMAIL_FAILED = 'email_failed';
    const ACTION_EMAIL_BOUNCED = 'email_bounced';
    const ACTION_EVENT_PROCESSED = 'event_processed';
    const ACTION_EVENT_FAILED = 'event_failed';
    const ACTION_TEMPLATE_MODIFIED = 'template_modified';
    const ACTION_SUPERVISOR_UPDATED = 'supervisor_updated';
    const ACTION_SETTINGS_CHANGED = 'settings_changed';
    const ACTION_USER_LOGIN = 'user_login';
    const ACTION_SYSTEM_ERROR = 'system_error';
    const ACTION_PERFORMANCE_SLOW = 'performance_slow';
    const ACTION_SECURITY_VIOLATION = 'security_violation';

    public function __construct()
    {
        $this->db_service = PostgreSQLDatabaseService::get_instance();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('wecoza_email_sent', array($this, 'log_email_sent'), 10, 2);
        add_action('wecoza_email_failed', array($this, 'log_email_failed'), 10, 2);
        add_action('wecoza_event_processed', array($this, 'log_event_processed'), 10, 2);
        add_action('wecoza_template_saved', array($this, 'log_template_modified'), 10, 2);
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);

        add_filter('wp_mail', array($this, 'track_wp_mail'), 10, 1);
    }

    public function log($level, $action, $message, $context = array(), $user_id = null)
    {

        // Validate and sanitize inputs
        $level = SecurityService::sanitize_text($level);
        $action = SecurityService::sanitize_text($action);
        $message = SecurityService::sanitize_text($message);

        $allowed_levels = array(self::LOG_LEVEL_INFO, self::LOG_LEVEL_WARNING, self::LOG_LEVEL_ERROR, self::LOG_LEVEL_CRITICAL);
        if (!in_array($level, $allowed_levels)) {
            $level = self::LOG_LEVEL_INFO;
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $user_id = SecurityService::sanitize_int($user_id);

        $log_data = array(
            'level' => $level,
            'action' => $action,
            'message' => $message,
            'context' => SecurityService::sanitize_json_for_db($context),
            'user_id' => $user_id,
            'ip_address' => SecurityService::get_client_ip(),
            'user_agent' => SecurityService::sanitize_text(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
            'request_uri' => SecurityService::sanitize_text(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''),
            'created_at' => current_time('mysql')
        );

        $result = $this->db_service->insert('audit_log', $log_data);

        if ($level === self::LOG_LEVEL_CRITICAL || $level === self::LOG_LEVEL_ERROR) {
            $this->trigger_alert($level, $action, $message, $context);
        }

        return $result !== false;
    }

    public function log_info($action, $message, $context = array(), $user_id = null)
    {
        return $this->log(self::LOG_LEVEL_INFO, $action, $message, $context, $user_id);
    }

    public function log_warning($action, $message, $context = array(), $user_id = null)
    {
        return $this->log(self::LOG_LEVEL_WARNING, $action, $message, $context, $user_id);
    }

    public function log_error($action, $message, $context = array(), $user_id = null)
    {
        return $this->log(self::LOG_LEVEL_ERROR, $action, $message, $context, $user_id);
    }

    public function log_critical($action, $message, $context = array(), $user_id = null)
    {
        return $this->log(self::LOG_LEVEL_CRITICAL, $action, $message, $context, $user_id);
    }

    public function log_email_sent($notification_id, $recipient_email)
    {
        global $wpdb;

        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wecoza_notification_queue WHERE id = %d",
            $notification_id
        ));

        if ($notification) {
            $this->log_info(
                self::ACTION_EMAIL_SENT,
                sprintf('Email sent successfully to %s', $recipient_email),
                array(
                    'notification_id' => $notification_id,
                    'recipient_email' => $recipient_email,
                    'template' => $notification->template_name,
                    'event_type' => $notification->event_name
                )
            );

            $this->update_delivery_stats($notification->template_name, 'sent');
        }
    }

    public function log_email_failed($notification_id, $error_message)
    {
        global $wpdb;

        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wecoza_notification_queue WHERE id = %d",
            $notification_id
        ));

        if ($notification) {
            $this->log_error(
                self::ACTION_EMAIL_FAILED,
                sprintf('Email failed to send to %s: %s', $notification->recipient_email, $error_message),
                array(
                    'notification_id' => $notification_id,
                    'recipient_email' => $notification->recipient_email,
                    'template' => $notification->template_name,
                    'error' => $error_message,
                    'retry_count' => $notification->retry_count
                )
            );

            $this->update_delivery_stats($notification->template_name, 'failed');
        }
    }

    public function log_email_bounced($notification_id, $bounce_reason)
    {
        $this->log_warning(
            self::ACTION_EMAIL_BOUNCED,
            sprintf('Email bounced: %s', $bounce_reason),
            array(
                'notification_id' => $notification_id,
                'bounce_reason' => $bounce_reason
            )
        );

        global $wpdb;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT template_name FROM {$wpdb->prefix}wecoza_notification_queue WHERE id = %d",
            $notification_id
        ));

        if ($notification) {
            $this->update_delivery_stats($notification->template_name, 'bounced');
        }
    }

    public function log_event_processed($event_id, $event_data)
    {
        $this->log_info(
            self::ACTION_EVENT_PROCESSED,
            sprintf('Event %s processed successfully for class %d', $event_data['event'], $event_data['class_id']),
            array(
                'event_id' => $event_id,
                'event_type' => $event_data['event'],
                'class_id' => $event_data['class_id'],
                'actor_id' => $event_data['actor_id'],
                'idempotency_key' => $event_data['idempotency_key']
            )
        );
    }

    public function log_template_modified($template_id, $changes)
    {
        $this->log_info(
            self::ACTION_TEMPLATE_MODIFIED,
            sprintf('Template %s was modified', $template_id),
            array(
                'template_id' => $template_id,
                'changes' => $changes,
                'user_id' => get_current_user_id()
            )
        );
    }

    public function log_user_login($user_login, $user)
    {
        if ($this->is_wecoza_admin_user($user)) {
            $this->log_info(
                self::ACTION_USER_LOGIN,
                sprintf('Admin user %s logged in', $user_login),
                array(
                    'user_id' => $user->ID,
                    'user_login' => $user_login,
                    'user_email' => $user->user_email
                ),
                $user->ID
            );
        }
    }

    public function log_system_error($error_message, $context = array())
    {
        $this->log_error(
            self::ACTION_SYSTEM_ERROR,
            $error_message,
            array_merge($context, array(
                'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
            ))
        );
    }

    public function log_performance_issue($operation, $execution_time, $threshold = 5000)
    {
        if ($execution_time > $threshold) {
            $this->log_warning(
                self::ACTION_PERFORMANCE_SLOW,
                sprintf('Slow operation detected: %s took %dms', $operation, $execution_time),
                array(
                    'operation' => $operation,
                    'execution_time' => $execution_time,
                    'threshold' => $threshold,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true)
                )
            );
        }
    }

    public function log_security_violation($violation_type, $details)
    {
        $this->log_critical(
            self::ACTION_SECURITY_VIOLATION,
            sprintf('Security violation detected: %s', $violation_type),
            array(
                'violation_type' => $violation_type,
                'details' => $details,
                'request_data' => $this->sanitize_request_data($_REQUEST)
            )
        );
    }

    public function track_wp_mail($args)
    {
        if (!is_array($args)) {
            return $args;
        }

        $start_time = microtime(true);

        add_action('wp_mail_succeeded', function($mail_data) use ($start_time) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            $this->log_info(
                'wp_mail_success',
                sprintf('WordPress mail sent to %s', is_array($mail_data['to']) ? implode(', ', $mail_data['to']) : $mail_data['to']),
                array(
                    'to' => $mail_data['to'],
                    'subject' => $mail_data['subject'],
                    'execution_time' => $execution_time
                )
            );
        });

        add_action('wp_mail_failed', function($error) use ($start_time) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            $this->log_error(
                'wp_mail_failed',
                sprintf('WordPress mail failed: %s', $error->get_error_message()),
                array(
                    'error_code' => $error->get_error_code(),
                    'error_message' => $error->get_error_message(),
                    'execution_time' => $execution_time
                )
            );
        });

        return $args;
    }

    public function get_audit_logs($filters = array(), $limit = 50, $offset = 0)
    {
        global $wpdb;

        $where_conditions = array('1=1');
        $params = array();

        if (!empty($filters['level'])) {
            $where_conditions[] = 'level = %s';
            $params[] = $filters['level'];
        }

        if (!empty($filters['action'])) {
            $where_conditions[] = 'action = %s';
            $params[] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = '(message LIKE %s OR context LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "
            SELECT al.*, u.display_name as user_name, u.user_email
            FROM {$wpdb->prefix}wecoza_audit_log al
            LEFT JOIN {$wpdb->users} u ON al.user_id = u.ID
            WHERE {$where_clause}
            ORDER BY al.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    public function get_audit_stats($period = '30 days')
    {
        $stats = array();

        // PostgreSQL interval syntax
        $interval = $period;

        $stats['total_logs'] = $this->db_service->get_var(
            "SELECT COUNT(*) FROM {$this->db_service->get_table('audit_log')}
             WHERE created_at >= NOW() - INTERVAL '$interval'"
        );

        $stats['by_level'] = $this->db_service->get_results(
            "SELECT level, COUNT(*) as count
             FROM {$this->db_service->get_table('audit_log')}
             WHERE created_at >= NOW() - INTERVAL '$interval'
             GROUP BY level
             ORDER BY count DESC"
        );

        $stats['by_action'] = $this->db_service->get_results(
            "SELECT action, COUNT(*) as count
             FROM {$this->db_service->get_table('audit_log')}
             WHERE created_at >= NOW() - INTERVAL '$interval'
             GROUP BY action
             ORDER BY count DESC
             LIMIT 10"
        );

        $stats['error_rate'] = $this->db_service->get_var(
            "SELECT ROUND((COUNT(CASE WHEN level IN ('error', 'critical') THEN 1 END)::numeric / COUNT(*)) * 100, 2)
             FROM {$this->db_service->get_table('audit_log')}
             WHERE created_at >= NOW() - INTERVAL '$interval'"
        );

        $stats['daily_counts'] = $this->db_service->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$this->db_service->get_table('audit_log')}
             WHERE created_at >= NOW() - INTERVAL '$interval'
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );

        return $stats;
    }

    public function get_system_health()
    {
        $health = array(
            'overall_status' => 'healthy',
            'checks' => array()
        );

        $health['checks']['database'] = $this->check_database_health();
        $health['checks']['email_queue'] = $this->check_email_queue_health();
        $health['checks']['error_rate'] = $this->check_error_rate();
        $health['checks']['disk_space'] = $this->check_disk_space();
        $health['checks']['memory_usage'] = $this->check_memory_usage();

        $issues = array_filter($health['checks'], function($check) {
            return $check['status'] !== 'ok';
        });

        if (count($issues) > 0) {
            $critical_issues = array_filter($issues, function($check) {
                return $check['status'] === 'critical';
            });

            $health['overall_status'] = count($critical_issues) > 0 ? 'critical' : 'warning';
        }

        return $health;
    }

    public function cleanup_old_logs($retention_days = 90)
    {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wecoza_audit_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        if ($deleted > 0) {
            $this->log_info(
                'audit_cleanup',
                sprintf('Cleaned up %d old audit log entries', $deleted),
                array('retention_days' => $retention_days, 'deleted_count' => $deleted)
            );
        }

        return $deleted;
    }

    public function export_audit_logs($filters = array(), $format = 'csv')
    {
        $logs = $this->get_audit_logs($filters, 10000, 0);

        if ($format === 'csv') {
            return $this->export_to_csv($logs);
        } elseif ($format === 'json') {
            return $this->export_to_json($logs);
        }

        return false;
    }

    private function update_delivery_stats($template_name, $status)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wecoza_analytics';
        $date = current_time('Y-m-d');

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE metric_type = 'email_delivery'
             AND metric_key = %s
             AND date = %s",
            $template_name,
            $date
        ));

        if ($existing) {
            $current_data = json_decode($existing->metric_value, true);
            $current_data[$status] = ($current_data[$status] ?? 0) + 1;

            $wpdb->update(
                $table_name,
                array('metric_value' => json_encode($current_data)),
                array('id' => $existing->id),
                array('%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'metric_type' => 'email_delivery',
                    'metric_key' => $template_name,
                    'metric_value' => json_encode(array($status => 1)),
                    'date' => $date,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    private function trigger_alert($level, $action, $message, $context)
    {
        $alert_threshold = get_option('wecoza_alert_threshold', 5);
        $time_window = get_option('wecoza_alert_window', 300); // 5 minutes

        global $wpdb;

        $recent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wecoza_audit_log
             WHERE level IN ('error', 'critical')
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $time_window
        ));

        if ($recent_count >= $alert_threshold) {
            $this->send_alert_notification($level, $action, $message, $context, $recent_count);
        }
    }

    private function send_alert_notification($level, $action, $message, $context, $error_count)
    {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $subject = sprintf('[WECOZA Alert] %s: %s', strtoupper($level), $action);
        $body = sprintf(
            "Alert Level: %s\nAction: %s\nMessage: %s\nError Count (last 5min): %d\nTime: %s\n\nContext:\n%s",
            strtoupper($level),
            $action,
            $message,
            $error_count,
            current_time('Y-m-d H:i:s'),
            print_r($context, true)
        );

        wp_mail($admin_email, $subject, $body);
    }

    private function get_client_ip()
    {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    private function is_wecoza_admin_user($user)
    {
        return user_can($user, 'manage_options') ||
               in_array('wecoza_admin', $user->roles) ||
               in_array('wecoza_supervisor', $user->roles);
    }

    private function sanitize_request_data($data)
    {
        $sanitized = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_request_data($value);
            } else {
                $sanitized[$key] = strlen($value) > 200 ? substr($value, 0, 200) . '...' : $value;
            }
        }
        return $sanitized;
    }

    private function check_database_health()
    {
        global $wpdb;

        try {
            $wpdb->get_var("SELECT 1");
            $table_status = $wpdb->get_results("SHOW TABLE STATUS LIKE '{$wpdb->prefix}wecoza_%'");

            return array(
                'status' => 'ok',
                'message' => 'Database connection healthy',
                'details' => array(
                    'tables_count' => count($table_status),
                    'last_check' => current_time('mysql')
                )
            );
        } catch (Exception $e) {
            return array(
                'status' => 'critical',
                'message' => 'Database connection failed',
                'details' => array('error' => $e->getMessage())
            );
        }
    }

    private function check_email_queue_health()
    {
        global $wpdb;

        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wecoza_notification_queue WHERE status = 'pending'"
        );

        $failed_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wecoza_notification_queue
             WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        $status = 'ok';
        $message = 'Email queue healthy';

        if ($pending_count > 100) {
            $status = 'warning';
            $message = 'High number of pending emails';
        }

        if ($failed_count > 10) {
            $status = 'critical';
            $message = 'High email failure rate';
        }

        return array(
            'status' => $status,
            'message' => $message,
            'details' => array(
                'pending_count' => $pending_count,
                'failed_count' => $failed_count
            )
        );
    }

    private function check_error_rate()
    {
        $total_logs = $this->db_service->get_var(
            "SELECT COUNT(*) FROM {$this->db_service->get_table('audit_log')}
             WHERE created_at >= NOW() - INTERVAL '1 hour'"
        );

        $error_logs = $this->db_service->get_var(
            "SELECT COUNT(*) FROM {$this->db_service->get_table('audit_log')}
             WHERE level IN ('error', 'critical') AND created_at >= NOW() - INTERVAL '1 hour'"
        );

        $error_rate = $total_logs > 0 ? ($error_logs / $total_logs) * 100 : 0;

        $status = 'ok';
        $message = 'Error rate normal';

        if ($error_rate > 10) {
            $status = 'warning';
            $message = 'Elevated error rate';
        }

        if ($error_rate > 25) {
            $status = 'critical';
            $message = 'High error rate';
        }

        return array(
            'status' => $status,
            'message' => $message,
            'details' => array(
                'error_rate' => round($error_rate, 2),
                'total_logs' => $total_logs,
                'error_logs' => $error_logs
            )
        );
    }

    private function check_disk_space()
    {
        $free_space = disk_free_space(ABSPATH);
        $total_space = disk_total_space(ABSPATH);
        $used_percentage = (($total_space - $free_space) / $total_space) * 100;

        $status = 'ok';
        $message = 'Disk space sufficient';

        if ($used_percentage > 80) {
            $status = 'warning';
            $message = 'Disk space running low';
        }

        if ($used_percentage > 90) {
            $status = 'critical';
            $message = 'Disk space critically low';
        }

        return array(
            'status' => $status,
            'message' => $message,
            'details' => array(
                'free_space' => $this->format_bytes($free_space),
                'total_space' => $this->format_bytes($total_space),
                'used_percentage' => round($used_percentage, 2)
            )
        );
    }

    private function check_memory_usage()
    {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);

        $limit_bytes = $this->parse_size($memory_limit);
        $usage_percentage = ($memory_usage / $limit_bytes) * 100;

        $status = 'ok';
        $message = 'Memory usage normal';

        if ($usage_percentage > 70) {
            $status = 'warning';
            $message = 'High memory usage';
        }

        if ($usage_percentage > 85) {
            $status = 'critical';
            $message = 'Critical memory usage';
        }

        return array(
            'status' => $status,
            'message' => $message,
            'details' => array(
                'memory_limit' => $memory_limit,
                'current_usage' => $this->format_bytes($memory_usage),
                'peak_usage' => $this->format_bytes($memory_peak),
                'usage_percentage' => round($usage_percentage, 2)
            )
        );
    }

    private function format_bytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function parse_size($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    private function export_to_csv($logs)
    {
        $csv_data = array();
        $csv_data[] = array('Date', 'Level', 'Action', 'Message', 'User', 'IP Address');

        foreach ($logs as $log) {
            $csv_data[] = array(
                $log->created_at,
                $log->level,
                $log->action,
                $log->message,
                $log->user_name ?: 'System',
                $log->ip_address
            );
        }

        return $csv_data;
    }

    private function export_to_json($logs)
    {
        return json_encode($logs, JSON_PRETTY_PRINT);
    }
}