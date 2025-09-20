<?php

namespace WecozaNotifications;

class AnalyticsService
{
    private $db_service;

    const METRIC_EMAIL_DELIVERY = 'email_delivery';
    const METRIC_EVENT_PROCESSING = 'event_processing';
    const METRIC_TEMPLATE_USAGE = 'template_usage';
    const METRIC_USER_ACTIVITY = 'user_activity';
    const METRIC_PERFORMANCE = 'performance';
    const METRIC_SYSTEM_HEALTH = 'system_health';
    const METRIC_ERROR_TRACKING = 'error_tracking';

    public function __construct()
    {
        $this->db_service = PostgreSQLDatabaseService::get_instance();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('wecoza_email_sent', array($this, 'track_email_delivery'), 10, 3);
        add_action('wecoza_email_failed', array($this, 'track_email_failure'), 10, 3);
        add_action('wecoza_event_processed', array($this, 'track_event_processing'), 10, 2);
        add_action('wecoza_template_used', array($this, 'track_template_usage'), 10, 2);

        add_action('wp_cron_daily', array($this, 'generate_daily_reports'));
        add_action('wp_cron_weekly', array($this, 'generate_weekly_reports'));
    }

    public function record_metric($metric_type, $metric_key, $metric_value, $date = null)
    {
        if (!$date) {
            $date = current_time('Y-m-d');
        }

        $existing = $this->db_service->get_row(
            "SELECT * FROM wecoza_events.analytics
             WHERE metric_type = $1 AND metric_key = $2 AND date = $3",
            array($metric_type, $metric_key, $date)
        );

        if ($existing) {
            $current_value = json_decode($existing->metric_value, true);
            if (is_array($metric_value) && is_array($current_value)) {
                $updated_value = array_merge_recursive($current_value, $metric_value);
            } else {
                $updated_value = $metric_value;
            }

            return $wpdb->update(
                $table_name,
                array(
                    'metric_value' => json_encode($updated_value),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $table_name,
                array(
                    'metric_type' => $metric_type,
                    'metric_key' => $metric_key,
                    'metric_value' => json_encode($metric_value),
                    'date' => $date,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    public function increment_metric($metric_type, $metric_key, $increment = 1, $date = null)
    {
        global $wpdb;

        if (!$date) {
            $date = current_time('Y-m-d');
        }

        $table_name = $wpdb->prefix . 'wecoza_analytics';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE metric_type = %s AND metric_key = %s AND date = %s",
            $metric_type, $metric_key, $date
        ));

        if ($existing) {
            if ($current_value !== false) {
                $new_value = is_numeric($current_value) ? $current_value + $increment : $increment;
            } else {
                $new_value = $increment;
            }

            return $wpdb->update(
                $table_name,
                array(
                    'metric_value' => SecurityService::sanitize_json_for_db($new_value),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => SecurityService::sanitize_int($existing->id)),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $table_name,
                array(
                    'metric_type' => $metric_type,
                    'metric_key' => $metric_key,
                    'metric_value' => SecurityService::sanitize_json_for_db($increment),
                    'date' => $date,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    public function track_email_delivery($notification_id, $recipient_email, $template_name)
    {
        $notification_id = SecurityService::sanitize_int($notification_id);
        $recipient_email = SecurityService::sanitize_email($recipient_email);
        $template_name = SecurityService::sanitize_text($template_name);

        if ($notification_id <= 0 || empty($template_name)) {
            return false;
        }

        $this->increment_metric(self::METRIC_EMAIL_DELIVERY, 'total_sent');
        $this->increment_metric(self::METRIC_EMAIL_DELIVERY, 'by_template_' . $template_name);
        $this->increment_metric(self::METRIC_TEMPLATE_USAGE, $template_name);

        $this->record_email_analytics($notification_id, 'sent', $template_name);
    }

    public function track_email_failure($notification_id, $error_message, $template_name)
    {
        $notification_id = SecurityService::sanitize_int($notification_id);
        $error_message = SecurityService::sanitize_text($error_message);
        $template_name = SecurityService::sanitize_text($template_name);

        if ($notification_id <= 0 || empty($template_name)) {
            return false;
        }

        $this->increment_metric(self::METRIC_EMAIL_DELIVERY, 'total_failed');
        $this->increment_metric(self::METRIC_EMAIL_DELIVERY, 'failed_by_template_' . $template_name);

        $this->record_email_analytics($notification_id, 'failed', $template_name, $error_message);
    }

    public function track_email_bounce($notification_id, $bounce_reason, $template_name)
    {
        $notification_id = SecurityService::sanitize_int($notification_id);
        $bounce_reason = SecurityService::sanitize_text($bounce_reason);
        $template_name = SecurityService::sanitize_text($template_name);

        if ($notification_id <= 0 || empty($template_name)) {
            return false;
        }

        $this->increment_metric(self::METRIC_EMAIL_DELIVERY, 'total_bounced');
        $this->increment_metric(self::METRIC_EMAIL_DELIVERY, 'bounced_by_template_' . $template_name);

        $this->record_email_analytics($notification_id, 'bounced', $template_name, $bounce_reason);
    }

    public function track_event_processing($event_id, $event_data)
    {
        $event_type = $event_data['event'];
        $processing_time = isset($event_data['processing_time']) ? $event_data['processing_time'] : 0;

        $this->increment_metric(self::METRIC_EVENT_PROCESSING, 'total_processed');
        $this->increment_metric(self::METRIC_EVENT_PROCESSING, 'by_type_' . $event_type);

        if ($processing_time > 0) {
            $this->record_metric(
                self::METRIC_PERFORMANCE,
                'event_processing_time',
                array(
                    'event_type' => $event_type,
                    'processing_time' => $processing_time,
                    'timestamp' => current_time('mysql')
                )
            );
        }
    }

    public function track_template_usage($template_id, $context = array())
    {
        $this->increment_metric(self::METRIC_TEMPLATE_USAGE, $template_id);
        $this->increment_metric(self::METRIC_TEMPLATE_USAGE, 'total_usage');

        if (isset($context['render_time'])) {
            $this->record_metric(
                self::METRIC_PERFORMANCE,
                'template_render_time',
                array(
                    'template_id' => $template_id,
                    'render_time' => $context['render_time'],
                    'timestamp' => current_time('mysql')
                )
            );
        }
    }

    public function track_user_activity($user_id, $action, $context = array())
    {
        $user_key = 'user_' . $user_id;
        $this->increment_metric(self::METRIC_USER_ACTIVITY, $user_key);
        $this->increment_metric(self::METRIC_USER_ACTIVITY, 'action_' . $action);

        $this->record_metric(
            self::METRIC_USER_ACTIVITY,
            'detailed_activity',
            array(
                'user_id' => $user_id,
                'action' => $action,
                'context' => $context,
                'timestamp' => current_time('mysql')
            )
        );
    }

    public function track_performance_metric($operation, $execution_time, $memory_usage = null)
    {
        $metric_data = array(
            'operation' => $operation,
            'execution_time' => $execution_time,
            'timestamp' => current_time('mysql')
        );

        if ($memory_usage !== null) {
            $metric_data['memory_usage'] = $memory_usage;
        }

        $this->record_metric(self::METRIC_PERFORMANCE, $operation, $metric_data);

        if ($execution_time > 5000) { // More than 5 seconds
            $this->increment_metric(self::METRIC_PERFORMANCE, 'slow_operations');
        }
    }

    public function track_system_health($health_data)
    {
        $this->record_metric(self::METRIC_SYSTEM_HEALTH, 'overall_status', $health_data);

        foreach ($health_data['checks'] as $check_name => $check_data) {
            $this->record_metric(self::METRIC_SYSTEM_HEALTH, 'check_' . $check_name, $check_data);
        }
    }

    public function track_error($error_type, $error_message, $context = array())
    {
        $this->increment_metric(self::METRIC_ERROR_TRACKING, 'total_errors');
        $this->increment_metric(self::METRIC_ERROR_TRACKING, 'by_type_' . $error_type);

        $this->record_metric(
            self::METRIC_ERROR_TRACKING,
            'error_details',
            array(
                'error_type' => $error_type,
                'error_message' => $error_message,
                'context' => $context,
                'timestamp' => current_time('mysql')
            )
        );
    }

    public function get_email_delivery_stats($period = '30 days')
    {
        global $wpdb;

        $date_condition = $this->get_date_condition($period);

        $stats = array();

        $stats['total_sent'] = $this->get_metric_sum(self::METRIC_EMAIL_DELIVERY, 'total_sent', $date_condition);
        $stats['total_failed'] = $this->get_metric_sum(self::METRIC_EMAIL_DELIVERY, 'total_failed', $date_condition);
        $stats['total_bounced'] = $this->get_metric_sum(self::METRIC_EMAIL_DELIVERY, 'total_bounced', $date_condition);

        $stats['total_attempted'] = $stats['total_sent'] + $stats['total_failed'];
        $stats['success_rate'] = $stats['total_attempted'] > 0 ?
            round(($stats['total_sent'] / $stats['total_attempted']) * 100, 2) : 0;

        $stats['by_template'] = $this->get_template_delivery_stats($date_condition);
        $stats['daily_counts'] = $this->get_daily_email_counts($date_condition);

        return $stats;
    }

    public function get_event_processing_stats($period = '30 days')
    {
        $date_condition = $this->get_date_condition($period);

        $stats = array();

        $stats['total_processed'] = $this->get_metric_sum(self::METRIC_EVENT_PROCESSING, 'total_processed', $date_condition);
        $stats['by_event_type'] = $this->get_metrics_by_prefix(self::METRIC_EVENT_PROCESSING, 'by_type_', $date_condition);
        $stats['processing_times'] = $this->get_performance_metrics('event_processing_time', $date_condition);

        return $stats;
    }

    public function get_template_usage_stats($period = '30 days')
    {
        $date_condition = $this->get_date_condition($period);

        $stats = array();

        $stats['total_usage'] = $this->get_metric_sum(self::METRIC_TEMPLATE_USAGE, 'total_usage', $date_condition);
        $stats['by_template'] = $this->get_template_specific_usage($date_condition);
        $stats['render_times'] = $this->get_performance_metrics('template_render_time', $date_condition);

        return $stats;
    }

    public function get_user_activity_stats($period = '30 days')
    {
        $date_condition = $this->get_date_condition($period);

        $stats = array();

        $stats['active_users'] = $this->get_metrics_by_prefix(self::METRIC_USER_ACTIVITY, 'user_', $date_condition);
        $stats['by_action'] = $this->get_metrics_by_prefix(self::METRIC_USER_ACTIVITY, 'action_', $date_condition);
        $stats['total_activities'] = array_sum(array_column($stats['by_action'], 'total'));

        return $stats;
    }

    public function get_performance_overview($period = '30 days')
    {
        $date_condition = $this->get_date_condition($period);

        $stats = array();

        $stats['slow_operations'] = $this->get_metric_sum(self::METRIC_PERFORMANCE, 'slow_operations', $date_condition);
        $stats['avg_response_times'] = $this->get_average_performance_metrics($date_condition);
        $stats['memory_usage_trends'] = $this->get_memory_usage_trends($date_condition);

        return $stats;
    }

    public function get_system_health_trends($period = '30 days')
    {
        $date_condition = $this->get_date_condition($period);

        return $this->get_metrics_by_prefix(self::METRIC_SYSTEM_HEALTH, 'check_', $date_condition);
    }

    public function get_error_analysis($period = '30 days')
    {
        $date_condition = $this->get_date_condition($period);

        $stats = array();

        $stats['total_errors'] = $this->get_metric_sum(self::METRIC_ERROR_TRACKING, 'total_errors', $date_condition);
        $stats['by_error_type'] = $this->get_metrics_by_prefix(self::METRIC_ERROR_TRACKING, 'by_type_', $date_condition);
        $stats['error_trends'] = $this->get_error_trends($date_condition);

        return $stats;
    }

    public function generate_comprehensive_report($period = '30 days')
    {
        $report = array(
            'period' => $period,
            'generated_at' => current_time('mysql'),
            'email_delivery' => $this->get_email_delivery_stats($period),
            'event_processing' => $this->get_event_processing_stats($period),
            'template_usage' => $this->get_template_usage_stats($period),
            'user_activity' => $this->get_user_activity_stats($period),
            'performance' => $this->get_performance_overview($period),
            'system_health' => $this->get_system_health_trends($period),
            'errors' => $this->get_error_analysis($period)
        );

        $report['summary'] = $this->generate_report_summary($report);

        return $report;
    }

    public function generate_daily_reports()
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $report = $this->generate_comprehensive_report('1 day');

        $this->record_metric('daily_reports', $yesterday, $report);

        do_action('wecoza_daily_report_generated', $report);
    }

    public function generate_weekly_reports()
    {
        $last_week = date('Y-m-d', strtotime('-1 week'));
        $report = $this->generate_comprehensive_report('7 days');

        $this->record_metric('weekly_reports', $last_week, $report);

        do_action('wecoza_weekly_report_generated', $report);
    }

    public function export_analytics_data($metric_types = array(), $period = '30 days', $format = 'json')
    {
        global $wpdb;

        $date_condition = $this->get_date_condition($period);
        $type_condition = '';

        if (!empty($metric_types)) {
            $placeholders = implode(',', array_fill(0, count($metric_types), '%s'));
            $type_condition = " AND metric_type IN ($placeholders)";
        }

        $query = "
            SELECT * FROM {$wpdb->prefix}wecoza_analytics
            WHERE {$date_condition} {$type_condition}
            ORDER BY date DESC, metric_type ASC
        ";

        $params = array_merge(array($period), $metric_types);
        $data = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

        if ($format === 'csv') {
            return $this->export_to_csv($data);
        } else {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
    }

    public function cleanup_old_analytics($retention_days = 365)
    {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wecoza_analytics
             WHERE date < DATE_SUB(CURDATE(), INTERVAL %d DAY)
             AND metric_type NOT IN ('daily_reports', 'weekly_reports')",
            $retention_days
        ));

        return $deleted;
    }

    private function record_email_analytics($notification_id, $status, $template_name, $error_message = null)
    {
        global $wpdb;

        $analytics_data = array(
            'notification_id' => $notification_id,
            'status' => $status,
            'template_name' => $template_name,
            'timestamp' => current_time('mysql')
        );

        if ($error_message) {
            $analytics_data['error_message'] = $error_message;
        }

        $this->record_metric(self::METRIC_EMAIL_DELIVERY, 'detailed_tracking', $analytics_data);
    }

    private function get_date_condition($period)
    {
        global $wpdb;

        if (is_numeric($period)) {
            return $wpdb->prepare("date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)", $period);
        } else {
            return $wpdb->prepare("date >= DATE_SUB(CURDATE(), INTERVAL %s)", $period);
        }
    }

    private function get_metric_sum($metric_type, $metric_key, $date_condition)
    {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED))
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key = %s AND {$date_condition}",
            $metric_type, $metric_key
        ));

        return intval($result);
    }

    private function get_metrics_by_prefix($metric_type, $prefix, $date_condition)
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_key, SUM(CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED)) as total
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key LIKE %s AND {$date_condition}
             GROUP BY metric_key
             ORDER BY total DESC",
            $metric_type, $prefix . '%'
        ), ARRAY_A);

        // Remove prefix from metric keys
        foreach ($results as &$result) {
            $result['metric_key'] = str_replace($prefix, '', $result['metric_key']);
        }

        return $results;
    }

    private function get_template_delivery_stats($date_condition)
    {
        global $wpdb;

        $sent_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT
                REPLACE(metric_key, 'by_template_', '') as template_name,
                SUM(CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED)) as sent_count
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key LIKE 'by_template_%' AND {$date_condition}
             GROUP BY metric_key",
            self::METRIC_EMAIL_DELIVERY
        ), ARRAY_A);

        $failed_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT
                REPLACE(metric_key, 'failed_by_template_', '') as template_name,
                SUM(CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED)) as failed_count
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key LIKE 'failed_by_template_%' AND {$date_condition}
             GROUP BY metric_key",
            self::METRIC_EMAIL_DELIVERY
        ), ARRAY_A);

        $combined_stats = array();
        foreach ($sent_stats as $sent) {
            $template_name = $sent['template_name'];
            $combined_stats[$template_name] = array(
                'sent' => intval($sent['sent_count']),
                'failed' => 0
            );
        }

        foreach ($failed_stats as $failed) {
            $template_name = $failed['template_name'];
            if (!isset($combined_stats[$template_name])) {
                $combined_stats[$template_name] = array('sent' => 0, 'failed' => 0);
            }
            $combined_stats[$template_name]['failed'] = intval($failed['failed_count']);
        }

        // Calculate success rates
        foreach ($combined_stats as &$stats) {
            $total = $stats['sent'] + $stats['failed'];
            $stats['total'] = $total;
            $stats['success_rate'] = $total > 0 ? round(($stats['sent'] / $total) * 100, 2) : 0;
        }

        return $combined_stats;
    }

    private function get_daily_email_counts($date_condition)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                date,
                SUM(CASE WHEN metric_key = 'total_sent' THEN CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED) ELSE 0 END) as sent,
                SUM(CASE WHEN metric_key = 'total_failed' THEN CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED) ELSE 0 END) as failed
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND {$date_condition}
             GROUP BY date
             ORDER BY date ASC",
            self::METRIC_EMAIL_DELIVERY
        ), ARRAY_A);
    }

    private function get_template_specific_usage($date_condition)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                metric_key as template_name,
                SUM(CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED)) as usage_count
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key != 'total_usage' AND {$date_condition}
             GROUP BY metric_key
             ORDER BY usage_count DESC",
            self::METRIC_TEMPLATE_USAGE
        ), ARRAY_A);
    }

    private function get_performance_metrics($operation_type, $date_condition)
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_value FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key = %s AND {$date_condition}",
            self::METRIC_PERFORMANCE, $operation_type
        ));

        $times = array();
        foreach ($results as $result) {
            $data = json_decode($result->metric_value, true);
            if (isset($data['execution_time']) || isset($data['processing_time']) || isset($data['render_time'])) {
                $time = $data['execution_time'] ?? $data['processing_time'] ?? $data['render_time'];
                $times[] = $time;
            }
        }

        if (empty($times)) {
            return array('avg' => 0, 'min' => 0, 'max' => 0, 'count' => 0);
        }

        return array(
            'avg' => round(array_sum($times) / count($times), 2),
            'min' => min($times),
            'max' => max($times),
            'count' => count($times)
        );
    }

    private function get_average_performance_metrics($date_condition)
    {
        $metrics = array();
        $operation_types = array('event_processing_time', 'template_render_time');

        foreach ($operation_types as $type) {
            $metrics[$type] = $this->get_performance_metrics($type, $date_condition);
        }

        return $metrics;
    }

    private function get_memory_usage_trends($date_condition)
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT date, metric_value FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key LIKE '%memory%' AND {$date_condition}
             ORDER BY date ASC",
            self::METRIC_PERFORMANCE
        ), ARRAY_A);

        $trends = array();
        foreach ($results as $result) {
            $data = json_decode($result['metric_value'], true);
            if (isset($data['memory_usage'])) {
                $trends[] = array(
                    'date' => $result['date'],
                    'memory_usage' => $data['memory_usage']
                );
            }
        }

        return $trends;
    }

    private function get_error_trends($date_condition)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                date,
                SUM(CAST(JSON_UNQUOTE(metric_value) AS UNSIGNED)) as error_count
             FROM {$wpdb->prefix}wecoza_analytics
             WHERE metric_type = %s AND metric_key = 'total_errors' AND {$date_condition}
             GROUP BY date
             ORDER BY date ASC",
            self::METRIC_ERROR_TRACKING
        ), ARRAY_A);
    }

    private function generate_report_summary($report)
    {
        $summary = array();

        $summary['email_performance'] = array(
            'total_sent' => $report['email_delivery']['total_sent'],
            'success_rate' => $report['email_delivery']['success_rate'],
            'status' => $report['email_delivery']['success_rate'] >= 95 ? 'excellent' :
                       ($report['email_delivery']['success_rate'] >= 85 ? 'good' : 'needs_attention')
        );

        $summary['system_activity'] = array(
            'events_processed' => $report['event_processing']['total_processed'],
            'template_usage' => $report['template_usage']['total_usage'],
            'active_users' => count($report['user_activity']['active_users'])
        );

        $summary['performance'] = array(
            'slow_operations' => $report['performance']['slow_operations'],
            'status' => $report['performance']['slow_operations'] < 10 ? 'good' : 'needs_attention'
        );

        $summary['error_rate'] = array(
            'total_errors' => $report['errors']['total_errors'],
            'status' => $report['errors']['total_errors'] < 50 ? 'good' : 'needs_attention'
        );

        return $summary;
    }

    private function export_to_csv($data)
    {
        if (empty($data)) {
            return '';
        }

        $csv_data = array();
        $csv_data[] = array_keys($data[0]);

        foreach ($data as $row) {
            $csv_data[] = array_values($row);
        }

        return $csv_data;
    }
}