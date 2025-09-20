<?php
/**
 * Template service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template service class
 */
class TemplateService
{
    /**
     * Template configuration
     */
    private $template_config;

    /**
     * System settings
     */
    private $settings;

    /**
     * Template cache
     */
    private $template_cache = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_configurations();
    }

    /**
     * Load configurations
     */
    private function load_configurations()
    {
        $this->template_config = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/templates.php';
        $this->settings = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/settings.php';
    }

    /**
     * Render template with variables
     */
    public function render_template($template_name, $variables)
    {
        try {
            // Check if template exists in configuration
            if (!isset($this->template_config[$template_name])) {
                $this->log_error('Template not found in configuration', array('template' => $template_name));
                return false;
            }

            $template_info = $this->template_config[$template_name];

            // Load template content
            $template_content = $this->load_template($template_name, $template_info);
            if (!$template_content) {
                return false;
            }

            // Process variables
            $processed_variables = $this->process_variables($variables, $template_info);

            // Replace variables in subject
            $subject = $this->replace_variables($template_info['subject'], $processed_variables);

            // Replace variables in body
            $body = $this->replace_variables($template_content, $processed_variables);

            // Apply filters for customization
            $subject = apply_filters('wecoza_email_subject', $subject, $template_name, $variables);
            $body = apply_filters('wecoza_email_body', $body, $template_name, $variables);

            return array(
                'subject' => $subject,
                'body' => $body,
                'template' => $template_name,
                'variables' => $processed_variables
            );

        } catch (Exception $e) {
            $this->log_error('Exception in render_template', array(
                'message' => $e->getMessage(),
                'template' => $template_name
            ));
            return false;
        }
    }

    /**
     * Load template content
     */
    private function load_template($template_name, $template_info)
    {
        // Check cache first
        if (isset($this->template_cache[$template_name])) {
            return $this->template_cache[$template_name];
        }

        $template_content = null;

        // Try to load custom template from theme first
        if ($this->settings['templates']['allow_overrides']) {
            $custom_path = $this->settings['templates']['override_path'] . $template_info['template_file'];
            if (file_exists($custom_path)) {
                $template_content = $this->load_template_file($custom_path);
                if ($template_content) {
                    $this->log_info('Loaded custom template', array('path' => $custom_path));
                }
            }
        }

        // Load default template if custom not found
        if (!$template_content) {
            $default_path = $this->settings['templates']['base_path'] . $template_info['template_file'];
            $template_content = $this->load_template_file($default_path);
        }

        // Use fallback template if file not found
        if (!$template_content) {
            $template_content = $this->get_fallback_template($template_name);
            $this->log_info('Using fallback template', array('template' => $template_name));
        }

        // Cache the template if caching is enabled
        if ($this->settings['templates']['cache_enabled']) {
            $this->template_cache[$template_name] = $template_content;
        }

        return $template_content;
    }

    /**
     * Load template file
     */
    private function load_template_file($file_path)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        if (!is_readable($file_path)) {
            $this->log_error('Template file not readable', array('path' => $file_path));
            return false;
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            $this->log_error('Failed to read template file', array('path' => $file_path));
            return false;
        }

        return $content;
    }

    /**
     * Process variables for template
     */
    private function process_variables($variables, $template_info)
    {
        $processed = array();

        // Ensure all required variables are present
        if (isset($template_info['variables'])) {
            foreach ($template_info['variables'] as $var_name) {
                $processed[$var_name] = isset($variables[$var_name]) ? $variables[$var_name] : '';
            }
        }

        // Add all provided variables
        foreach ($variables as $key => $value) {
            $processed[$key] = $this->sanitize_variable($value);
        }

        // Add system variables
        $processed = array_merge($processed, $this->get_system_variables());

        return $processed;
    }

    /**
     * Get system variables
     */
    private function get_system_variables()
    {
        return array(
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'admin_email' => get_option('admin_email'),
            'current_date' => current_time('F j, Y'),
            'current_time' => current_time('g:i A'),
            'current_year' => current_time('Y')
        );
    }

    /**
     * Sanitize variable value
     */
    private function sanitize_variable($value)
    {
        if (is_string($value)) {
            return wp_kses_post($value);
        } elseif (is_array($value)) {
            return array_map(array($this, 'sanitize_variable'), $value);
        } else {
            return $value;
        }
    }

    /**
     * Replace variables in content
     */
    private function replace_variables($content, $variables)
    {
        foreach ($variables as $key => $value) {
            // Handle array values
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            // Replace {variable_name} patterns
            $content = str_replace('{' . $key . '}', $value, $content);
        }

        // Clean up any unreplaced variables
        $content = preg_replace('/\{[^}]+\}/', '', $content);

        return $content;
    }

    /**
     * Get fallback template
     */
    private function get_fallback_template($template_name)
    {
        // Return basic HTML template as fallback
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_name} Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{site_name}</h1>
        </div>
        <div class="content">
            <h2>Notification</h2>
            <p>This is a notification from the WECOZA system.</p>
            <p><strong>Template:</strong> ' . $template_name . '</p>
            <p><strong>Date:</strong> {current_date} at {current_time}</p>
        </div>
        <div class="footer">
            <p>&copy; {current_year} {site_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Validate template
     */
    public function validate_template($template_name, $content)
    {
        $errors = array();

        // Check template configuration exists
        if (!isset($this->template_config[$template_name])) {
            $errors[] = 'Template not found in configuration';
            return $errors;
        }

        $template_info = $this->template_config[$template_name];

        // Check required variables are present
        if (isset($template_info['variables'])) {
            foreach ($template_info['variables'] as $var_name) {
                if (strpos($content, '{' . $var_name . '}') === false) {
                    $errors[] = "Required variable '{$var_name}' not found in template";
                }
            }
        }

        // Check for basic HTML structure
        if (strpos($content, '<html') === false) {
            $errors[] = 'Template should contain proper HTML structure';
        }

        // Check for potentially malicious content
        $dangerous_tags = array('<script', '<iframe', '<object', '<embed');
        foreach ($dangerous_tags as $tag) {
            if (stripos($content, $tag) !== false) {
                $errors[] = "Potentially dangerous tag found: {$tag}";
            }
        }

        return $errors;
    }

    /**
     * Get available templates
     */
    public function get_available_templates()
    {
        $templates = array();

        foreach ($this->template_config as $template_name => $template_info) {
            $templates[$template_name] = array(
                'name' => $template_name,
                'subject' => $template_info['subject'],
                'description' => isset($template_info['description']) ? $template_info['description'] : '',
                'variables' => isset($template_info['variables']) ? $template_info['variables'] : array(),
                'file' => $template_info['template_file']
            );
        }

        return $templates;
    }

    /**
     * Preview template with sample data
     */
    public function preview_template($template_name, $sample_variables = array())
    {
        // Merge with default sample data
        $sample_data = array_merge(array(
            'class_name' => 'Sample Class Name',
            'client_name' => 'Sample Client',
            'site_name' => 'Sample Site',
            'created_by' => 'John Doe',
            'class_url' => get_site_url() . '/class/123',
            'dashboard_url' => get_site_url() . '/dashboard',
            'due_date' => date('F j, Y', strtotime('+3 days')),
            'days_overdue' => '2',
            'learner_count' => '15',
            'start_date' => date('F j, Y', strtotime('+1 week')),
            'end_date' => date('F j, Y', strtotime('+2 weeks'))
        ), $sample_variables);

        return $this->render_template($template_name, $sample_data);
    }

    /**
     * Create template directory if not exists
     */
    public function ensure_template_directory()
    {
        $template_dir = $this->settings['templates']['base_path'];

        if (!file_exists($template_dir)) {
            if (!wp_mkdir_p($template_dir)) {
                $this->log_error('Failed to create template directory', array('path' => $template_dir));
                return false;
            }
        }

        // Create subdirectories
        $subdirs = array('confirmations', 'reminders');
        foreach ($subdirs as $subdir) {
            $subdir_path = $template_dir . $subdir;
            if (!file_exists($subdir_path)) {
                if (!wp_mkdir_p($subdir_path)) {
                    $this->log_error('Failed to create template subdirectory', array('path' => $subdir_path));
                }
            }
        }

        return true;
    }

    /**
     * Clear template cache
     */
    public function clear_cache()
    {
        $this->template_cache = array();
        $this->log_info('Template cache cleared');
        return true;
    }

    /**
     * Log info message
     */
    private function log_info($message, $context = array())
    {
        if ($this->settings['system']['debug_mode']) {
            $log_message = "WECOZA Template Service Info: {$message}";
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            error_log($log_message);
        }
    }

    /**
     * Log error message
     */
    private function log_error($message, $context = array())
    {
        $log_message = "WECOZA Template Service Error: {$message}";
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        error_log($log_message);
    }
}