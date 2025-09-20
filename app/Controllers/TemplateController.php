<?php

namespace WecozaNotifications;

class TemplateController
{
    private $template_service;
    private $db_service;

    public function __construct()
    {
        $this->template_service = new TemplateService();
        $this->db_service = new DatabaseService();
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wecoza_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_wecoza_preview_template', array($this, 'ajax_preview_template'));
        add_action('wp_ajax_wecoza_restore_template', array($this, 'ajax_restore_template'));
        add_action('wp_ajax_wecoza_export_template', array($this, 'ajax_export_template'));
        add_action('wp_ajax_wecoza_import_template', array($this, 'ajax_import_template'));
        add_action('wp_ajax_wecoza_delete_template_version', array($this, 'ajax_delete_template_version'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'wecoza-notifications',
            __('Template Management', 'wecoza-notifications'),
            __('Templates', 'wecoza-notifications'),
            'manage_options',
            'wecoza-templates',
            array($this, 'render_templates_page')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'wecoza-templates') !== false) {
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_script('codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js', array(), '5.65.2');
            wp_enqueue_script('codemirror-html', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js', array('codemirror'), '5.65.2');
            wp_enqueue_style('codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css', array(), '5.65.2');
            wp_enqueue_style('codemirror-theme', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material.min.css', array('codemirror'), '5.65.2');

            wp_enqueue_script(
                'wecoza-templates-admin',
                WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/js/templates-admin.js',
                array('jquery', 'jquery-ui-tabs', 'codemirror'),
                WECOZA_NOTIFICATIONS_VERSION,
                true
            );

            wp_localize_script('wecoza-templates-admin', 'wecoza_templates', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wecoza_templates_nonce'),
                'strings' => array(
                    'save_success' => __('Template saved successfully', 'wecoza-notifications'),
                    'save_error' => __('Error saving template', 'wecoza-notifications'),
                    'confirm_restore' => __('Are you sure you want to restore this version? Current changes will be lost.', 'wecoza-notifications'),
                    'confirm_delete' => __('Are you sure you want to delete this version?', 'wecoza-notifications'),
                    'preview_loading' => __('Loading preview...', 'wecoza-notifications'),
                    'export_success' => __('Template exported successfully', 'wecoza-notifications'),
                    'import_success' => __('Template imported successfully', 'wecoza-notifications')
                )
            ));

            wp_enqueue_style(
                'wecoza-templates-admin',
                WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/css/templates-admin.css',
                array(),
                WECOZA_NOTIFICATIONS_VERSION
            );
        }
    }

    public function render_templates_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';
        $template_id = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : '';

        echo '<div class="wrap">';
        echo '<h1>' . __('Template Management', 'wecoza-notifications') . '</h1>';

        $this->render_tabs($current_tab);

        switch ($current_tab) {
            case 'list':
                $this->render_templates_list();
                break;
            case 'edit':
                $this->render_template_editor($template_id);
                break;
            case 'analytics':
                $this->render_template_analytics();
                break;
            case 'settings':
                $this->render_template_settings();
                break;
        }

        echo '</div>';
    }

    private function render_tabs($current_tab)
    {
        $tabs = array(
            'list' => __('Templates', 'wecoza-notifications'),
            'edit' => __('Editor', 'wecoza-notifications'),
            'analytics' => __('Analytics', 'wecoza-notifications'),
            'settings' => __('Settings', 'wecoza-notifications')
        );

        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_name) {
            $active = ($current_tab === $tab_key) ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=wecoza-templates&tab=' . $tab_key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($tab_name) . '</a>';
        }
        echo '</nav>';
    }

    private function render_templates_list()
    {
        $templates = $this->get_all_templates();

        echo '<div class="template-list-container">';
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<a href="#" class="button button-primary" id="import-template-btn">' . __('Import Template', 'wecoza-notifications') . '</a>';
        echo '</div>';
        echo '</div>';

        echo '<table class="wp-list-table widefat fixed striped templates">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">' . __('Template Name', 'wecoza-notifications') . '</th>';
        echo '<th scope="col">' . __('Type', 'wecoza-notifications') . '</th>';
        echo '<th scope="col">' . __('Last Modified', 'wecoza-notifications') . '</th>';
        echo '<th scope="col">' . __('Versions', 'wecoza-notifications') . '</th>';
        echo '<th scope="col">' . __('Usage Count', 'wecoza-notifications') . '</th>';
        echo '<th scope="col">' . __('Actions', 'wecoza-notifications') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($templates as $template_key => $template_data) {
            $versions = $this->get_template_versions($template_key);
            $usage_count = $this->get_template_usage_count($template_key);
            $last_modified = $this->get_template_last_modified($template_key);

            echo '<tr>';
            echo '<td><strong>' . esc_html($template_data['name']) . '</strong><br>';
            echo '<small>' . esc_html($template_key) . '</small></td>';
            echo '<td>' . esc_html($this->get_template_type_label($template_data['type'])) . '</td>';
            echo '<td>' . esc_html($last_modified) . '</td>';
            echo '<td>' . count($versions) . '</td>';
            echo '<td>' . intval($usage_count) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wecoza-templates&tab=edit&template=' . $template_key)) . '" class="button">' . __('Edit', 'wecoza-notifications') . '</a> ';
            echo '<a href="#" class="button preview-template" data-template="' . esc_attr($template_key) . '">' . __('Preview', 'wecoza-notifications') . '</a> ';
            echo '<a href="#" class="button export-template" data-template="' . esc_attr($template_key) . '">' . __('Export', 'wecoza-notifications') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $this->render_preview_modal();
        $this->render_import_modal();
    }

    private function render_template_editor($template_id)
    {
        if (empty($template_id)) {
            echo '<div class="notice notice-error"><p>' . __('Please select a template to edit.', 'wecoza-notifications') . '</p></div>';
            return;
        }

        $template_data = $this->get_template_data($template_id);
        if (!$template_data) {
            echo '<div class="notice notice-error"><p>' . __('Template not found.', 'wecoza-notifications') . '</p></div>';
            return;
        }

        $versions = $this->get_template_versions($template_id);

        echo '<div class="template-editor-container">';
        echo '<div class="template-editor-header">';
        echo '<h2>' . esc_html($template_data['name']) . ' <span class="template-id">(' . esc_html($template_id) . ')</span></h2>';
        echo '<div class="template-actions">';
        echo '<button type="button" class="button" id="preview-current">' . __('Preview', 'wecoza-notifications') . '</button>';
        echo '<button type="button" class="button button-primary" id="save-template">' . __('Save Changes', 'wecoza-notifications') . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="template-editor-content">';
        echo '<div class="editor-main">';
        echo '<div class="editor-tabs">';
        echo '<ul>';
        echo '<li><a href="#subject-tab">' . __('Subject', 'wecoza-notifications') . '</a></li>';
        echo '<li><a href="#body-tab">' . __('Body', 'wecoza-notifications') . '</a></li>';
        echo '<li><a href="#variables-tab">' . __('Variables', 'wecoza-notifications') . '</a></li>';
        echo '<li><a href="#css-tab">' . __('Custom CSS', 'wecoza-notifications') . '</a></li>';
        echo '</ul>';

        echo '<div id="subject-tab">';
        echo '<label for="template-subject">' . __('Email Subject', 'wecoza-notifications') . '</label>';
        echo '<input type="text" id="template-subject" name="template_subject" value="' . esc_attr($template_data['subject']) . '" class="large-text" />';
        echo '<p class="description">' . __('Use {{variable_name}} for dynamic content.', 'wecoza-notifications') . '</p>';
        echo '</div>';

        echo '<div id="body-tab">';
        echo '<label for="template-body">' . __('Email Body', 'wecoza-notifications') . '</label>';
        echo '<textarea id="template-body" name="template_body" rows="20" class="large-text code">' . esc_textarea($template_data['body']) . '</textarea>';
        echo '<p class="description">' . __('HTML content with variable placeholders.', 'wecoza-notifications') . '</p>';
        echo '</div>';

        echo '<div id="variables-tab">';
        echo '<h3>' . __('Available Variables', 'wecoza-notifications') . '</h3>';
        echo '<div class="variables-list">';
        $variables = $this->get_template_variables($template_id);
        foreach ($variables as $var => $description) {
            echo '<div class="variable-item">';
            echo '<code>{{' . esc_html($var) . '}}</code>';
            echo '<span class="variable-description">' . esc_html($description) . '</span>';
            echo '<button type="button" class="button-link insert-variable" data-variable="' . esc_attr($var) . '">' . __('Insert', 'wecoza-notifications') . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div id="css-tab">';
        echo '<label for="template-css">' . __('Custom CSS', 'wecoza-notifications') . '</label>';
        echo '<textarea id="template-css" name="template_css" rows="15" class="large-text code">' . esc_textarea($template_data['custom_css'] ?? '') . '</textarea>';
        echo '<p class="description">' . __('Additional CSS styles for this template.', 'wecoza-notifications') . '</p>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '<div class="editor-sidebar">';
        echo '<div class="sidebar-section">';
        echo '<h3>' . __('Template Versions', 'wecoza-notifications') . '</h3>';
        echo '<div class="versions-list">';
        foreach (array_slice($versions, 0, 10) as $version) {
            echo '<div class="version-item">';
            echo '<div class="version-info">';
            echo '<strong>v' . esc_html($version['version']) . '</strong>';
            echo '<small>' . esc_html($version['created_at']) . '</small>';
            echo '</div>';
            echo '<div class="version-actions">';
            echo '<button type="button" class="button-link restore-version" data-version="' . esc_attr($version['id']) . '">' . __('Restore', 'wecoza-notifications') . '</button>';
            echo '<button type="button" class="button-link delete-version" data-version="' . esc_attr($version['id']) . '">' . __('Delete', 'wecoza-notifications') . '</button>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="sidebar-section">';
        echo '<h3>' . __('Template Info', 'wecoza-notifications') . '</h3>';
        echo '<p><strong>' . __('Type:', 'wecoza-notifications') . '</strong> ' . esc_html($this->get_template_type_label($template_data['type'])) . '</p>';
        echo '<p><strong>' . __('Usage:', 'wecoza-notifications') . '</strong> ' . intval($this->get_template_usage_count($template_id)) . ' ' . __('emails sent', 'wecoza-notifications') . '</p>';
        echo '<p><strong>' . __('Last Modified:', 'wecoza-notifications') . '</strong> ' . esc_html($this->get_template_last_modified($template_id)) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '<input type="hidden" id="current-template-id" value="' . esc_attr($template_id) . '" />';
    }

    private function render_template_analytics()
    {
        echo '<div class="template-analytics-container">';
        echo '<h2>' . __('Template Analytics', 'wecoza-notifications') . '</h2>';

        $analytics_data = $this->get_template_analytics();

        echo '<div class="analytics-cards">';
        echo '<div class="analytics-card">';
        echo '<h3>' . __('Total Emails Sent', 'wecoza-notifications') . '</h3>';
        echo '<div class="metric-value">' . number_format($analytics_data['total_emails']) . '</div>';
        echo '</div>';

        echo '<div class="analytics-card">';
        echo '<h3>' . __('Success Rate', 'wecoza-notifications') . '</h3>';
        echo '<div class="metric-value">' . number_format($analytics_data['success_rate'], 1) . '%</div>';
        echo '</div>';

        echo '<div class="analytics-card">';
        echo '<h3>' . __('Most Used Template', 'wecoza-notifications') . '</h3>';
        echo '<div class="metric-value">' . esc_html($analytics_data['most_used_template']) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="analytics-charts">';
        echo '<div class="chart-container">';
        echo '<h3>' . __('Template Usage (Last 30 Days)', 'wecoza-notifications') . '</h3>';
        echo '<canvas id="template-usage-chart" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    private function render_template_settings()
    {
        echo '<div class="template-settings-container">';
        echo '<h2>' . __('Template Settings', 'wecoza-notifications') . '</h2>';

        echo '<form method="post" action="options.php">';
        settings_fields('wecoza_template_settings');
        do_settings_sections('wecoza_template_settings');

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Default Template Language', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<select name="wecoza_default_template_language">';
        echo '<option value="en">' . __('English', 'wecoza-notifications') . '</option>';
        echo '<option value="af">' . __('Afrikaans', 'wecoza-notifications') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Version Retention', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<input type="number" name="wecoza_template_version_retention" value="' . esc_attr(get_option('wecoza_template_version_retention', 10)) . '" min="1" max="50" />';
        echo '<p class="description">' . __('Number of template versions to keep.', 'wecoza-notifications') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Auto-backup Templates', 'wecoza-notifications') . '</th>';
        echo '<td>';
        echo '<input type="checkbox" name="wecoza_template_auto_backup" value="1" ' . checked(get_option('wecoza_template_auto_backup', 1), 1, false) . ' />';
        echo '<label>' . __('Automatically create backups before edits', 'wecoza-notifications') . '</label>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }

    private function render_preview_modal()
    {
        echo '<div id="template-preview-modal" class="template-modal" style="display: none;">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h2>' . __('Template Preview', 'wecoza-notifications') . '</h2>';
        echo '<button type="button" class="modal-close">&times;</button>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<div class="preview-controls">';
        echo '<label for="preview-sample-data">' . __('Sample Data:', 'wecoza-notifications') . '</label>';
        echo '<select id="preview-sample-data">';
        echo '<option value="default">' . __('Default Sample', 'wecoza-notifications') . '</option>';
        echo '<option value="class_1">' . __('Class Example 1', 'wecoza-notifications') . '</option>';
        echo '<option value="class_2">' . __('Class Example 2', 'wecoza-notifications') . '</option>';
        echo '</select>';
        echo '<button type="button" class="button" id="refresh-preview">' . __('Refresh', 'wecoza-notifications') . '</button>';
        echo '</div>';
        echo '<div id="preview-content">';
        echo '<div class="preview-loading">' . __('Loading preview...', 'wecoza-notifications') . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_import_modal()
    {
        echo '<div id="template-import-modal" class="template-modal" style="display: none;">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h2>' . __('Import Template', 'wecoza-notifications') . '</h2>';
        echo '<button type="button" class="modal-close">&times;</button>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<form id="template-import-form" enctype="multipart/form-data">';
        echo '<p>';
        echo '<label for="template-import-file">' . __('Select Template File:', 'wecoza-notifications') . '</label>';
        echo '<input type="file" id="template-import-file" name="template_file" accept=".json" />';
        echo '</p>';
        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" name="overwrite_existing" value="1" />';
        echo __('Overwrite existing template if it exists', 'wecoza-notifications');
        echo '</label>';
        echo '</p>';
        echo '<div class="modal-actions">';
        echo '<button type="submit" class="button button-primary">' . __('Import', 'wecoza-notifications') . '</button>';
        echo '<button type="button" class="button modal-close">' . __('Cancel', 'wecoza-notifications') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function ajax_save_template()
    {
        check_ajax_referer('wecoza_templates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wecoza-notifications'));
        }

        $template_id = sanitize_text_field($_POST['template_id']);
        $template_data = array(
            'subject' => sanitize_text_field($_POST['subject']),
            'body' => wp_kses_post($_POST['body']),
            'custom_css' => sanitize_textarea_field($_POST['custom_css'])
        );

        $result = $this->save_template($template_id, $template_data);

        if ($result) {
            wp_send_json_success(__('Template saved successfully', 'wecoza-notifications'));
        } else {
            wp_send_json_error(__('Failed to save template', 'wecoza-notifications'));
        }
    }

    public function ajax_preview_template()
    {
        check_ajax_referer('wecoza_templates_nonce', 'nonce');

        $template_id = sanitize_text_field($_POST['template_id']);
        $sample_data = sanitize_text_field($_POST['sample_data']);

        $preview_html = $this->generate_template_preview($template_id, $sample_data);

        wp_send_json_success(array('html' => $preview_html));
    }

    public function ajax_restore_template()
    {
        check_ajax_referer('wecoza_templates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wecoza-notifications'));
        }

        $version_id = intval($_POST['version_id']);
        $result = $this->restore_template_version($version_id);

        if ($result) {
            wp_send_json_success(__('Template restored successfully', 'wecoza-notifications'));
        } else {
            wp_send_json_error(__('Failed to restore template', 'wecoza-notifications'));
        }
    }

    public function ajax_export_template()
    {
        check_ajax_referer('wecoza_templates_nonce', 'nonce');

        $template_id = sanitize_text_field($_POST['template_id']);
        $export_data = $this->export_template($template_id);

        if ($export_data) {
            wp_send_json_success(array('data' => $export_data));
        } else {
            wp_send_json_error(__('Failed to export template', 'wecoza-notifications'));
        }
    }

    public function ajax_import_template()
    {
        check_ajax_referer('wecoza_templates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wecoza-notifications'));
        }

        if (!isset($_FILES['template_file'])) {
            wp_send_json_error(__('No file uploaded', 'wecoza-notifications'));
        }

        $overwrite = isset($_POST['overwrite_existing']) && $_POST['overwrite_existing'] === '1';
        $result = $this->import_template($_FILES['template_file'], $overwrite);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function ajax_delete_template_version()
    {
        check_ajax_referer('wecoza_templates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wecoza-notifications'));
        }

        $version_id = intval($_POST['version_id']);
        $result = $this->delete_template_version($version_id);

        if ($result) {
            wp_send_json_success(__('Version deleted successfully', 'wecoza-notifications'));
        } else {
            wp_send_json_error(__('Failed to delete version', 'wecoza-notifications'));
        }
    }

    private function get_all_templates()
    {
        $template_config = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/templates.php';
        return $template_config;
    }

    private function get_template_data($template_id)
    {
        $templates = $this->get_all_templates();
        return isset($templates[$template_id]) ? $templates[$template_id] : null;
    }

    private function get_template_versions($template_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wecoza_template_versions
             WHERE template_id = %s
             ORDER BY version DESC
             LIMIT 20",
            $template_id
        ), ARRAY_A);
    }

    private function get_template_usage_count($template_id)
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wecoza_notification_queue
             WHERE template_name = %s",
            $template_id
        ));
    }

    private function get_template_last_modified($template_id)
    {
        global $wpdb;

        $last_modified = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$wpdb->prefix}wecoza_template_versions
             WHERE template_id = %s
             ORDER BY created_at DESC
             LIMIT 1",
            $template_id
        ));

        return $last_modified ? date('Y-m-d H:i', strtotime($last_modified)) : __('Never', 'wecoza-notifications');
    }

    private function get_template_type_label($type)
    {
        $types = array(
            'confirmation' => __('Confirmation', 'wecoza-notifications'),
            'reminder' => __('Reminder', 'wecoza-notifications'),
            'system' => __('System', 'wecoza-notifications')
        );

        return isset($types[$type]) ? $types[$type] : $type;
    }

    private function get_template_variables($template_id)
    {
        $common_variables = array(
            'user_name' => __('Recipient name', 'wecoza-notifications'),
            'class_name' => __('Class name', 'wecoza-notifications'),
            'client_name' => __('Client name', 'wecoza-notifications'),
            'due_date' => __('Task due date', 'wecoza-notifications'),
            'dashboard_url' => __('Dashboard URL', 'wecoza-notifications'),
            'site_name' => __('Website name', 'wecoza-notifications'),
            'current_date' => __('Current date', 'wecoza-notifications')
        );

        $template_specific = array();
        if (strpos($template_id, 'reminder') !== false) {
            $template_specific['time_remaining'] = __('Time remaining until due', 'wecoza-notifications');
            $template_specific['urgency_level'] = __('Urgency level', 'wecoza-notifications');
        }

        if (strpos($template_id, 'overdue') !== false) {
            $template_specific['overdue_by'] = __('How long overdue', 'wecoza-notifications');
            $template_specific['escalation_date'] = __('Escalation date', 'wecoza-notifications');
        }

        return array_merge($common_variables, $template_specific);
    }

    private function get_template_analytics()
    {
        global $wpdb;

        $total_emails = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wecoza_notification_queue
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $successful_emails = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wecoza_notification_queue
             WHERE status = 'sent' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $success_rate = $total_emails > 0 ? ($successful_emails / $total_emails) * 100 : 0;

        $most_used = $wpdb->get_var(
            "SELECT template_name FROM {$wpdb->prefix}wecoza_notification_queue
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY template_name
             ORDER BY COUNT(*) DESC
             LIMIT 1"
        );

        return array(
            'total_emails' => intval($total_emails),
            'success_rate' => floatval($success_rate),
            'most_used_template' => $most_used ?: __('None', 'wecoza-notifications')
        );
    }

    private function save_template($template_id, $template_data)
    {
        global $wpdb;

        if (get_option('wecoza_template_auto_backup', 1)) {
            $this->create_template_backup($template_id);
        }

        $version = $this->get_next_version_number($template_id);

        $result = $wpdb->insert(
            $wpdb->prefix . 'wecoza_template_versions',
            array(
                'template_id' => $template_id,
                'version' => $version,
                'subject' => $template_data['subject'],
                'body' => $template_data['body'],
                'custom_css' => $template_data['custom_css'],
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result) {
            $this->cleanup_old_versions($template_id);
        }

        return $result !== false;
    }

    private function create_template_backup($template_id)
    {
        $current_data = $this->get_current_template_content($template_id);
        if ($current_data) {
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'wecoza_template_versions',
                array_merge($current_data, array(
                    'is_backup' => 1,
                    'created_at' => current_time('mysql')
                ))
            );
        }
    }

    private function get_next_version_number($template_id)
    {
        global $wpdb;

        $latest_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM {$wpdb->prefix}wecoza_template_versions WHERE template_id = %s",
            $template_id
        ));

        return $latest_version ? $latest_version + 1 : 1;
    }

    private function cleanup_old_versions($template_id)
    {
        $retention = get_option('wecoza_template_version_retention', 10);
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wecoza_template_versions
             WHERE template_id = %s
             AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM {$wpdb->prefix}wecoza_template_versions
                     WHERE template_id = %s
                     ORDER BY created_at DESC
                     LIMIT %d
                 ) t
             )",
            $template_id, $template_id, $retention
        ));
    }

    private function generate_template_preview($template_id, $sample_data)
    {
        $sample_vars = $this->get_sample_variables($sample_data);
        $template_data = $this->get_current_template_content($template_id);

        if (!$template_data) {
            return '<p>' . __('Template not found', 'wecoza-notifications') . '</p>';
        }

        $subject = $this->replace_variables($template_data['subject'], $sample_vars);
        $body = $this->replace_variables($template_data['body'], $sample_vars);

        $html = '<div class="email-preview">';
        $html .= '<div class="email-subject"><strong>' . __('Subject:', 'wecoza-notifications') . '</strong> ' . esc_html($subject) . '</div>';
        $html .= '<div class="email-body">' . $body . '</div>';
        $html .= '</div>';

        return $html;
    }

    private function get_sample_variables($sample_type)
    {
        $samples = array(
            'default' => array(
                'user_name' => 'John Doe',
                'class_name' => 'Basic Computer Skills',
                'client_name' => 'ABC Company',
                'due_date' => date('Y-m-d H:i', strtotime('+3 days')),
                'dashboard_url' => home_url('/dashboard/'),
                'site_name' => get_bloginfo('name'),
                'current_date' => date('Y-m-d H:i'),
                'time_remaining' => '3 days',
                'urgency_level' => 'medium'
            ),
            'class_1' => array(
                'user_name' => 'Sarah Smith',
                'class_name' => 'Advanced Excel Training',
                'client_name' => 'XYZ Corporation',
                'due_date' => date('Y-m-d H:i', strtotime('+1 day')),
                'dashboard_url' => home_url('/dashboard/'),
                'site_name' => get_bloginfo('name'),
                'current_date' => date('Y-m-d H:i'),
                'time_remaining' => '1 day',
                'urgency_level' => 'high'
            )
        );

        return isset($samples[$sample_type]) ? $samples[$sample_type] : $samples['default'];
    }

    private function replace_variables($content, $variables)
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }

    private function get_current_template_content($template_id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wecoza_template_versions
             WHERE template_id = %s
             ORDER BY created_at DESC
             LIMIT 1",
            $template_id
        ), ARRAY_A);
    }

    private function restore_template_version($version_id)
    {
        global $wpdb;

        $version_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wecoza_template_versions WHERE id = %d",
            $version_id
        ), ARRAY_A);

        if (!$version_data) {
            return false;
        }

        unset($version_data['id']);
        $version_data['version'] = $this->get_next_version_number($version_data['template_id']);
        $version_data['created_by'] = get_current_user_id();
        $version_data['created_at'] = current_time('mysql');
        $version_data['is_restore'] = 1;

        return $wpdb->insert($wpdb->prefix . 'wecoza_template_versions', $version_data);
    }

    private function export_template($template_id)
    {
        $template_data = $this->get_current_template_content($template_id);
        $template_config = $this->get_template_data($template_id);

        if (!$template_data || !$template_config) {
            return false;
        }

        $export_data = array(
            'template_id' => $template_id,
            'name' => $template_config['name'],
            'type' => $template_config['type'],
            'subject' => $template_data['subject'],
            'body' => $template_data['body'],
            'custom_css' => $template_data['custom_css'],
            'variables' => $this->get_template_variables($template_id),
            'export_date' => current_time('mysql'),
            'export_version' => '1.0'
        );

        return $export_data;
    }

    private function import_template($file, $overwrite = false)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => __('File upload error', 'wecoza-notifications'));
        }

        $file_content = file_get_contents($file['tmp_name']);
        $template_data = json_decode($file_content, true);

        if (!$template_data) {
            return array('success' => false, 'message' => __('Invalid template file', 'wecoza-notifications'));
        }

        $required_fields = array('template_id', 'name', 'subject', 'body');
        foreach ($required_fields as $field) {
            if (!isset($template_data[$field])) {
                return array('success' => false, 'message' => sprintf(__('Missing required field: %s', 'wecoza-notifications'), $field));
            }
        }

        if (!$overwrite && $this->get_current_template_content($template_data['template_id'])) {
            return array('success' => false, 'message' => __('Template already exists. Check overwrite option to replace.', 'wecoza-notifications'));
        }

        $save_data = array(
            'subject' => $template_data['subject'],
            'body' => $template_data['body'],
            'custom_css' => $template_data['custom_css'] ?? ''
        );

        $result = $this->save_template($template_data['template_id'], $save_data);

        if ($result) {
            return array('success' => true, 'message' => __('Template imported successfully', 'wecoza-notifications'));
        } else {
            return array('success' => false, 'message' => __('Failed to import template', 'wecoza-notifications'));
        }
    }

    private function delete_template_version($version_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . 'wecoza_template_versions',
            array('id' => $version_id),
            array('%d')
        );
    }
}