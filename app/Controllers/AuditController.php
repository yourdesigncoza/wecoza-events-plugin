<?php

namespace WecozaNotifications;

class AuditController
{
    private $audit_service;

    public function __construct()
    {
        $this->audit_service = new AuditService();
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wecoza_export_audit_logs', array($this, 'ajax_export_audit_logs'));
        add_action('wp_ajax_wecoza_get_system_health', array($this, 'ajax_get_system_health'));
        add_action('wp_ajax_wecoza_cleanup_audit_logs', array($this, 'ajax_cleanup_audit_logs'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'wecoza-notifications',
            __('Audit Log', 'wecoza-notifications'),
            __('Audit Log', 'wecoza-notifications'),
            'manage_options',
            'wecoza-audit',
            array($this, 'render_audit_page')
        );

        add_submenu_page(
            'wecoza-notifications',
            __('System Health', 'wecoza-notifications'),
            __('System Health', 'wecoza-notifications'),
            'manage_options',
            'wecoza-health',
            array($this, 'render_health_page')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'wecoza-audit') !== false || strpos($hook, 'wecoza-health') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1');

            wp_enqueue_script(
                'wecoza-audit-admin',
                WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/js/audit-admin.js',
                array('jquery', 'chart-js'),
                WECOZA_NOTIFICATIONS_VERSION,
                true
            );

            wp_localize_script('wecoza-audit-admin', 'wecoza_audit', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wecoza_audit_nonce'),
                'strings' => array(
                    'export_success' => __('Audit logs exported successfully', 'wecoza-notifications'),
                    'export_error' => __('Error exporting audit logs', 'wecoza-notifications'),
                    'cleanup_confirm' => __('Are you sure you want to cleanup old audit logs? This action cannot be undone.', 'wecoza-notifications'),
                    'cleanup_success' => __('Old audit logs cleaned up successfully', 'wecoza-notifications'),
                    'health_updated' => __('System health updated', 'wecoza-notifications')
                )
            ));

            wp_enqueue_style(
                'wecoza-audit-admin',
                WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/css/audit-admin.css',
                array(),
                WECOZA_NOTIFICATIONS_VERSION
            );
        }
    }

    public function render_audit_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'logs';

        echo '<div class="wrap">';
        echo '<h1>' . __('Audit Log', 'wecoza-notifications') . '</h1>';

        $this->render_audit_tabs($current_tab);

        switch ($current_tab) {
            case 'logs':
                $this->render_audit_logs();
                break;
            case 'analytics':
                $this->render_audit_analytics();
                break;
            case 'settings':
                $this->render_audit_settings();
                break;
        }

        echo '</div>';
    }

    public function render_health_page()
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('System Health', 'wecoza-notifications') . '</h1>';

        $this->render_system_health();

        echo '</div>';
    }

    private function render_audit_tabs($current_tab)
    {
        $tabs = array(
            'logs' => __('Audit Logs', 'wecoza-notifications'),
            'analytics' => __('Analytics', 'wecoza-notifications'),
            'settings' => __('Settings', 'wecoza-notifications')
        );

        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_name) {
            $active = ($current_tab === $tab_key) ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=wecoza-audit&tab=' . $tab_key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($tab_name) . '</a>';
        }
        echo '</nav>';
    }

    private function render_audit_logs()
    {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $filters = array();
        if (!empty($_GET['level'])) {
            $filters['level'] = sanitize_text_field($_GET['level']);
        }
        if (!empty($_GET['action'])) {
            $filters['action'] = sanitize_text_field($_GET['action']);
        }
        if (!empty($_GET['user_id'])) {
            $filters['user_id'] = intval($_GET['user_id']);
        }
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field($_GET['search']);
        }

        $logs = $this->audit_service->get_audit_logs($filters, $per_page, $offset);

        echo '<div class="audit-logs-container">';

        $this->render_audit_filters($filters);

        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<button class="button" id="export-logs-csv">' . __('Export CSV', 'wecoza-notifications') . '</button>';
        echo '<button class="button" id="export-logs-json">' . __('Export JSON', 'wecoza-notifications') . '</button>';
        echo '<button class="button" id="cleanup-logs">' . __('Cleanup Old Logs', 'wecoza-notifications') . '</button>';
        echo '</div>';
        echo '</div>';

        if (empty($logs)) {
            echo '<div class="no-logs-message">';
            echo '<p>' . __('No audit logs found matching your criteria.', 'wecoza-notifications') . '</p>';
            echo '</div>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped audit-logs">';
            echo '<thead>';
            echo '<tr>';
            echo '<th scope="col" class="column-date">' . __('Date', 'wecoza-notifications') . '</th>';
            echo '<th scope="col" class="column-level">' . __('Level', 'wecoza-notifications') . '</th>';
            echo '<th scope="col" class="column-action">' . __('Action', 'wecoza-notifications') . '</th>';
            echo '<th scope="col" class="column-message">' . __('Message', 'wecoza-notifications') . '</th>';
            echo '<th scope="col" class="column-user">' . __('User', 'wecoza-notifications') . '</th>';
            echo '<th scope="col" class="column-ip">' . __('IP Address', 'wecoza-notifications') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($logs as $log) {
                $level_class = 'level-' . $log->level;
                $user_display = $log->user_name ?: ($log->user_id ? 'User #' . $log->user_id : 'System');

                echo '<tr class="' . esc_attr($level_class) . '">';
                echo '<td class="column-date">' . esc_html($log->created_at) . '</td>';
                echo '<td class="column-level">';
                echo '<span class="level-badge level-' . esc_attr($log->level) . '">' . esc_html(ucfirst($log->level)) . '</span>';
                echo '</td>';
                echo '<td class="column-action">' . esc_html($log->action) . '</td>';
                echo '<td class="column-message">';
                echo '<div class="message-text">' . esc_html($log->message) . '</div>';
                if (!empty($log->context)) {
                    echo '<button type="button" class="button-link show-context" data-context="' . esc_attr($log->context) . '">' . __('Show Context', 'wecoza-notifications') . '</button>';
                }
                echo '</td>';
                echo '<td class="column-user">' . esc_html($user_display) . '</td>';
                echo '<td class="column-ip">' . esc_html($log->ip_address) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            $this->render_pagination($page, $per_page, count($logs));
        }

        echo '</div>';

        $this->render_context_modal();
    }

    private function render_audit_filters($current_filters)
    {
        echo '<div class="audit-filters">';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="wecoza-audit" />';
        echo '<input type="hidden" name="tab" value="logs" />';

        echo '<div class="filter-row">';

        echo '<select name="level">';
        echo '<option value="">' . __('All Levels', 'wecoza-notifications') . '</option>';
        $levels = array('info', 'warning', 'error', 'critical');
        foreach ($levels as $level) {
            $selected = isset($current_filters['level']) && $current_filters['level'] === $level ? ' selected' : '';
            echo '<option value="' . esc_attr($level) . '"' . $selected . '>' . esc_html(ucfirst($level)) . '</option>';
        }
        echo '</select>';

        echo '<select name="action">';
        echo '<option value="">' . __('All Actions', 'wecoza-notifications') . '</option>';
        $actions = $this->get_unique_actions();
        foreach ($actions as $action) {
            $selected = isset($current_filters['action']) && $current_filters['action'] === $action ? ' selected' : '';
            echo '<option value="' . esc_attr($action) . '"' . $selected . '>' . esc_html($action) . '</option>';
        }
        echo '</select>';

        echo '<input type="date" name="date_from" value="' . esc_attr($current_filters['date_from'] ?? '') . '" placeholder="' . __('From Date', 'wecoza-notifications') . '" />';
        echo '<input type="date" name="date_to" value="' . esc_attr($current_filters['date_to'] ?? '') . '" placeholder="' . __('To Date', 'wecoza-notifications') . '" />';

        echo '<input type="text" name="search" value="' . esc_attr($current_filters['search'] ?? '') . '" placeholder="' . __('Search message...', 'wecoza-notifications') . '" />';

        echo '<button type="submit" class="button">' . __('Filter', 'wecoza-notifications') . '</button>';
        echo '<a href="' . admin_url('admin.php?page=wecoza-audit&tab=logs') . '" class="button">' . __('Clear', 'wecoza-notifications') . '</a>';

        echo '</div>';
        echo '</form>';
        echo '</div>';
    }

    private function render_audit_analytics()
    {
        $stats = $this->audit_service->get_audit_stats('30 days');

        echo '<div class="audit-analytics-container">';

        echo '<div class="analytics-summary">';
        echo '<div class="summary-cards">';

        echo '<div class="summary-card">';
        echo '<h3>' . __('Total Log Entries', 'wecoza-notifications') . '</h3>';
        echo '<div class="metric-value">' . number_format($stats['total_logs']) . '</div>';
        echo '<div class="metric-period">' . __('Last 30 days', 'wecoza-notifications') . '</div>';
        echo '</div>';

        echo '<div class="summary-card">';
        echo '<h3>' . __('Error Rate', 'wecoza-notifications') . '</h3>';
        echo '<div class="metric-value">' . number_format($stats['error_rate'], 1) . '%</div>';
        echo '<div class="metric-period">' . __('Errors/Critical', 'wecoza-notifications') . '</div>';
        echo '</div>';

        echo '<div class="summary-card">';
        echo '<h3>' . __('Top Action', 'wecoza-notifications') . '</h3>';
        echo '<div class="metric-value">' . esc_html($stats['by_action'][0]['action'] ?? 'N/A') . '</div>';
        echo '<div class="metric-period">' . number_format($stats['by_action'][0]['count'] ?? 0) . ' ' . __('occurrences', 'wecoza-notifications') . '</div>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '<div class="analytics-charts">';

        echo '<div class="chart-section">';
        echo '<h3>' . __('Log Levels Distribution', 'wecoza-notifications') . '</h3>';
        echo '<div class="chart-container">';
        echo '<canvas id="levels-chart" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';

        echo '<div class="chart-section">';
        echo '<h3>' . __('Daily Activity', 'wecoza-notifications') . '</h3>';
        echo '<div class="chart-container">';
        echo '<canvas id="activity-chart" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';

        echo '<div class="chart-section">';
        echo '<h3>' . __('Top Actions', 'wecoza-notifications') . '</h3>';
        echo '<div class="chart-container">';
        echo '<canvas id="actions-chart" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        echo '</div>';

        echo '<script type="text/javascript">';
        echo 'var auditStats = ' . json_encode($stats) . ';';
        echo '</script>';
    }

    private function render_audit_settings()
    {
        echo '<div class="audit-settings-container">';
        echo '<h2>' . __('Audit Settings', 'wecoza-notifications') . '</h2>';

        echo '<form method="post" action="options.php">';
        settings_fields('wecoza_audit_settings');
        do_settings_sections('wecoza_audit_settings');

        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row">' . __('Log Retention Period', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<input type="number" name="wecoza_audit_retention_days" value="' . esc_attr(get_option('wecoza_audit_retention_days', 90)) . '" min="7" max="365" />';
        echo '<p class="description">' . __('Number of days to keep audit logs (7-365 days)', 'wecoza-notifications') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Alert Threshold', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<input type="number" name="wecoza_alert_threshold" value="' . esc_attr(get_option('wecoza_alert_threshold', 5)) . '" min="1" max="50" />';
        echo '<p class="description">' . __('Number of errors to trigger an alert', 'wecoza-notifications') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Alert Time Window', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<select name="wecoza_alert_window">';
        $windows = array(
            300 => __('5 minutes', 'wecoza-notifications'),
            600 => __('10 minutes', 'wecoza-notifications'),
            1800 => __('30 minutes', 'wecoza-notifications'),
            3600 => __('1 hour', 'wecoza-notifications')
        );
        $current_window = get_option('wecoza_alert_window', 300);
        foreach ($windows as $value => $label) {
            $selected = $current_window == $value ? ' selected' : '';
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Time window for counting errors', 'wecoza-notifications') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Enable Performance Monitoring', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<input type="checkbox" name="wecoza_enable_performance_monitoring" value="1" ' . checked(get_option('wecoza_enable_performance_monitoring', 1), 1, false) . ' />';
        echo '<label>' . __('Track slow operations and performance issues', 'wecoza-notifications') . '</label>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Performance Threshold', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<input type="number" name="wecoza_performance_threshold" value="' . esc_attr(get_option('wecoza_performance_threshold', 5000)) . '" min="1000" max="30000" step="100" />';
        echo '<span> ms</span>';
        echo '<p class="description">' . __('Threshold in milliseconds for slow operation alerts', 'wecoza-notifications') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }

    private function render_system_health()
    {
        $health = $this->audit_service->get_system_health();

        echo '<div class="system-health-container">';

        echo '<div class="health-header">';
        echo '<div class="overall-status status-' . esc_attr($health['overall_status']) . '">';
        echo '<h2>' . esc_html(ucfirst($health['overall_status'])) . '</h2>';
        echo '<p>' . __('Overall system status', 'wecoza-notifications') . '</p>';
        echo '</div>';
        echo '<div class="health-actions">';
        echo '<button type="button" class="button button-primary" id="refresh-health">' . __('Refresh Status', 'wecoza-notifications') . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="health-checks">';
        foreach ($health['checks'] as $check_name => $check_data) {
            echo '<div class="health-check status-' . esc_attr($check_data['status']) . '">';
            echo '<div class="check-header">';
            echo '<h3>' . esc_html($this->get_check_title($check_name)) . '</h3>';
            echo '<span class="status-indicator">' . esc_html(ucfirst($check_data['status'])) . '</span>';
            echo '</div>';
            echo '<div class="check-content">';
            echo '<p class="check-message">' . esc_html($check_data['message']) . '</p>';
            if (!empty($check_data['details'])) {
                echo '<div class="check-details">';
                foreach ($check_data['details'] as $key => $value) {
                    echo '<div class="detail-item">';
                    echo '<span class="detail-key">' . esc_html($this->format_detail_key($key)) . ':</span>';
                    echo '<span class="detail-value">' . esc_html($value) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    private function render_context_modal()
    {
        echo '<div id="context-modal" class="audit-modal" style="display: none;">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h2>' . __('Log Context', 'wecoza-notifications') . '</h2>';
        echo '<button type="button" class="modal-close">&times;</button>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<pre id="context-content"></pre>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_pagination($current_page, $per_page, $result_count)
    {
        // Simple pagination implementation
        if ($result_count === $per_page) {
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">';

            if ($current_page > 1) {
                $prev_url = add_query_arg('paged', $current_page - 1);
                echo '<a class="button" href="' . esc_url($prev_url) . '">' . __('Previous', 'wecoza-notifications') . '</a>';
            }

            $next_url = add_query_arg('paged', $current_page + 1);
            echo '<a class="button" href="' . esc_url($next_url) . '">' . __('Next', 'wecoza-notifications') . '</a>';

            echo '</div>';
            echo '</div>';
        }
    }

    public function ajax_export_audit_logs()
    {
        check_ajax_referer('wecoza_audit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wecoza-notifications'));
        }

        $format = sanitize_text_field($_POST['format']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

        $export_data = $this->audit_service->export_audit_logs($filters, $format);

        if ($export_data) {
            wp_send_json_success(array('data' => $export_data));
        } else {
            wp_send_json_error(__('Failed to export logs', 'wecoza-notifications'));
        }
    }

    public function ajax_get_system_health()
    {
        check_ajax_referer('wecoza_audit_nonce', 'nonce');

        $health = $this->audit_service->get_system_health();
        wp_send_json_success($health);
    }

    public function ajax_cleanup_audit_logs()
    {
        check_ajax_referer('wecoza_audit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wecoza-notifications'));
        }

        $retention_days = intval($_POST['retention_days']) ?: 90;
        $deleted_count = $this->audit_service->cleanup_old_logs($retention_days);

        wp_send_json_success(array(
            'message' => sprintf(__('Cleaned up %d old log entries', 'wecoza-notifications'), $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

    private function get_unique_actions()
    {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT DISTINCT action FROM {$wpdb->prefix}wecoza_audit_log
             ORDER BY action ASC"
        );
    }

    private function get_check_title($check_name)
    {
        $titles = array(
            'database' => __('Database Health', 'wecoza-notifications'),
            'email_queue' => __('Email Queue', 'wecoza-notifications'),
            'error_rate' => __('Error Rate', 'wecoza-notifications'),
            'disk_space' => __('Disk Space', 'wecoza-notifications'),
            'memory_usage' => __('Memory Usage', 'wecoza-notifications')
        );

        return isset($titles[$check_name]) ? $titles[$check_name] : ucfirst(str_replace('_', ' ', $check_name));
    }

    private function format_detail_key($key)
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}