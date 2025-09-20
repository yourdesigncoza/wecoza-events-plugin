<?php
/**
 * Email service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email service class
 */
class EmailService
{
    /**
     * Database service instance
     */
    private $db;

    /**
     * Template service instance
     */
    private $template_service;

    /**
     * System settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = PostgreSQLDatabaseService::get_instance();
        $this->template_service = new TemplateService();
        $this->load_settings();
    }

    /**
     * Load system settings
     */
    private function load_settings()
    {
        $this->settings = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/settings.php';
    }

    /**
     * Initialize Action Scheduler integration
     */
    public function init_action_scheduler()
    {
        // Check if Action Scheduler is available
        if (!function_exists('as_next_scheduled_action')) {
            // Fallback to WordPress cron if Action Scheduler is not available
            $this->init_wp_cron_fallback();
            return;
        }

        // Register our action hook
        add_action('wecoza_process_email_queue', array($this, 'process_queue_action_scheduler'));

        // Schedule recurring email queue processing if not already scheduled
        if (false === as_next_scheduled_action('wecoza_process_email_queue')) {
            as_schedule_recurring_action(
                time() + 60, // Start in 1 minute
                300, // Every 5 minutes
                'wecoza_process_email_queue',
                array(),
                'wecoza-notifications'
            );
        }
    }

    /**
     * Fallback to WordPress cron when Action Scheduler is not available
     */
    private function init_wp_cron_fallback()
    {
        // Register our action hook
        add_action('wecoza_process_email_queue', array($this, 'process_queue_action_scheduler'));

        // Schedule recurring email queue processing using WordPress cron
        if (!wp_next_scheduled('wecoza_process_email_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'wecoza_process_email_queue');
        }

        // Add custom cron interval for 5 minutes if it doesn't exist
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules)
    {
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = array(
                'interval' => 300, // 5 minutes in seconds
                'display' => 'Every Five Minutes'
            );
        }
        return $schedules;
    }

    /**
     * Action Scheduler callback for queue processing
     */
    public function process_queue_action_scheduler()
    {
        $this->process_queue(50);
    }

    /**
     * Schedule individual email for Action Scheduler processing
     */
    public function schedule_email($notification_id, $delay = 0)
    {
        $when = time() + $delay;

        if (function_exists('as_schedule_single_action')) {
            // Use Action Scheduler if available
            as_schedule_single_action(
                $when,
                'wecoza_send_single_email',
                array('notification_id' => $notification_id),
                'wecoza-notifications'
            );
        } else {
            // Fallback to WordPress cron for single events
            wp_schedule_single_event(
                $when,
                'wecoza_send_single_email',
                array('notification_id' => $notification_id)
            );
        }
    }

    /**
     * Process email queue
     */
    public function process_queue($limit = 50)
    {
        $this->log_info('Starting email queue processing', array('limit' => $limit));

        // Get pending notifications
        $notifications = $this->db->get_pending_notifications($limit);

        if (empty($notifications)) {
            $this->log_info('No pending notifications found');
            return true;
        }

        $processed = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            $success = $this->send_notification($notification);

            if ($success) {
                $processed++;
                $this->log_info('Notification sent successfully', array(
                    'id' => $notification->id,
                    'recipient' => $notification->recipient_email
                ));
            } else {
                $failed++;
                $this->log_error('Failed to send notification', array(
                    'id' => $notification->id,
                    'recipient' => $notification->recipient_email
                ));
            }
        }

        $this->log_info('Email queue processing completed', array(
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($notifications)
        ));

        return true;
    }

    /**
     * Send individual notification
     */
    public function send_notification($notification)
    {
        try {
            // Parse payload
            $payload = json_decode($notification->payload, true);
            if (!$payload) {
                $this->update_notification_failed($notification->id, 'Invalid payload JSON');
                return false;
            }

            // Generate email content
            $email_content = $this->template_service->render_template(
                $notification->template_name,
                $payload
            );

            if (!$email_content) {
                $this->update_notification_failed($notification->id, 'Failed to render template');
                return false;
            }

            // Send email based on channel
            switch ($notification->channel) {
                case 'email':
                    $success = $this->send_email($notification, $email_content);
                    break;

                case 'dashboard':
                    $success = $this->send_dashboard_notification($notification, $payload);
                    break;

                default:
                    $this->update_notification_failed($notification->id, 'Unsupported channel: ' . $notification->channel);
                    return false;
            }

            if ($success) {
                $this->update_notification_sent($notification->id);
                return true;
            } else {
                return false;
            }

        } catch (Exception $e) {
            $this->update_notification_failed($notification->id, $e->getMessage());
            $this->log_error('Exception in send_notification', array(
                'message' => $e->getMessage(),
                'notification_id' => $notification->id
            ));
            return false;
        }
    }

    /**
     * Send email using WordPress wp_mail
     */
    private function send_email($notification, $email_content)
    {
        // Prepare email headers
        $headers = array();
        $headers[] = 'Content-Type: ' . $this->settings['email']['content_type'] . '; charset=UTF-8';
        $headers[] = 'From: ' . $this->settings['email']['from_name'] . ' <' . $this->settings['email']['from_email'] . '>';

        if (!empty($this->settings['email']['reply_to'])) {
            $headers[] = 'Reply-To: ' . $this->settings['email']['reply_to'];
        }

        // Add custom headers for tracking
        $headers[] = 'X-WECOZA-Notification-ID: ' . $notification->id;
        $headers[] = 'X-WECOZA-Event: ' . $notification->event_name;

        // Send email
        $success = wp_mail(
            $notification->recipient_email,
            $email_content['subject'],
            $email_content['body'],
            $headers
        );

        if (!$success) {
            $error = 'wp_mail() returned false';
            $this->update_notification_failed($notification->id, $error);
            $this->log_error('wp_mail failed', array(
                'notification_id' => $notification->id,
                'recipient' => $notification->recipient_email,
                'subject' => $email_content['subject']
            ));
            return false;
        }

        return true;
    }

    /**
     * Send dashboard notification
     */
    private function send_dashboard_notification($notification, $payload)
    {
        // Dashboard notifications are handled by updating the dashboard status
        // This is already done in the EventProcessor, so we just mark as sent
        return true;
    }

    /**
     * Update notification as sent
     */
    private function update_notification_sent($notification_id)
    {
        return $this->db->update_notification_status($notification_id, 'sent');
    }

    /**
     * Update notification as failed
     */
    private function update_notification_failed($notification_id, $error)
    {
        return $this->db->update_notification_status($notification_id, 'failed', $error);
    }

    /**
     * Queue notification for sending
     */
    public function queue_notification($notification_data)
    {
        // Validate required fields
        $required_fields = array('event_name', 'idempotency_key', 'recipient_email', 'template_name');
        foreach ($required_fields as $field) {
            if (!isset($notification_data[$field]) || empty($notification_data[$field])) {
                $this->log_error('Missing required field for notification', array(
                    'field' => $field,
                    'data' => $notification_data
                ));
                return false;
            }
        }

        // Set defaults
        $notification_data = array_merge(array(
            'channel' => 'email',
            'recipient_name' => '',
            'scheduled_at' => current_time('mysql'),
            'payload' => array()
        ), $notification_data);

        return $this->db->queue_notification($notification_data);
    }

    /**
     * Send immediate email (bypasses queue)
     */
    public function send_immediate($recipient_email, $recipient_name, $template_name, $payload)
    {
        try {
            // Generate email content
            $email_content = $this->template_service->render_template($template_name, $payload);

            if (!$email_content) {
                $this->log_error('Failed to render template for immediate email', array(
                    'template' => $template_name,
                    'recipient' => $recipient_email
                ));
                return false;
            }

            // Create temporary notification object
            $notification = (object) array(
                'id' => 'immediate_' . time(),
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'event_name' => 'immediate',
                'channel' => 'email'
            );

            return $this->send_email($notification, $email_content);

        } catch (Exception $e) {
            $this->log_error('Exception in send_immediate', array(
                'message' => $e->getMessage(),
                'recipient' => $recipient_email,
                'template' => $template_name
            ));
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function test_email_config($test_email = null)
    {
        $test_email = $test_email ?: get_option('admin_email');

        $test_payload = array(
            'test_message' => __('This is a test email from WECOZA Notifications', 'wecoza-notifications'),
            'timestamp' => current_time('mysql'),
            'site_name' => get_bloginfo('name')
        );

        return $this->send_immediate(
            $test_email,
            __('Test Recipient', 'wecoza-notifications'),
            'test_email',
            $test_payload
        );
    }

    /**
     * Get email delivery statistics
     */
    public function get_delivery_stats($days = 30)
    {
        $stats = array();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Total emails in period
        $stats['total'] = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->get_table('notification_queue')}
             WHERE created_at >= %s AND channel = 'email'",
            array($cutoff_date)
        );

        // Sent emails
        $stats['sent'] = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->get_table('notification_queue')}
             WHERE created_at >= %s AND status = 'sent' AND channel = 'email'",
            array($cutoff_date)
        );

        // Failed emails
        $stats['failed'] = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->get_table('notification_queue')}
             WHERE created_at >= %s AND status = 'failed' AND channel = 'email'",
            array($cutoff_date)
        );

        // Pending emails
        $stats['pending'] = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->get_table('notification_queue')}
             WHERE created_at >= %s AND status = 'pending' AND channel = 'email'",
            array($cutoff_date)
        );

        // Calculate success rate
        $stats['success_rate'] = $stats['total'] > 0 ?
            round(($stats['sent'] / $stats['total']) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Retry failed emails
     */
    public function retry_failed_emails($limit = 50)
    {
        $this->log_info('Retrying failed emails', array('limit' => $limit));

        // Get failed notifications that haven't exceeded max attempts
        $failed_notifications = $this->db->get_results(
            "SELECT * FROM {$this->db->get_table('notification_queue')}
             WHERE status = 'failed'
             AND attempts < max_attempts
             AND channel = 'email'
             ORDER BY updated_at ASC
             LIMIT %d",
            array($limit)
        );

        if (empty($failed_notifications)) {
            $this->log_info('No failed emails to retry');
            return true;
        }

        $retried = 0;
        foreach ($failed_notifications as $notification) {
            // Reset status to pending for retry
            $this->db->update_notification_status($notification->id, 'pending');
            $retried++;
        }

        $this->log_info('Reset failed emails for retry', array('count' => $retried));
        return true;
    }

    /**
     * Clean up old sent emails
     */
    public function cleanup_old_emails($days = 30)
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $this->db->query(
            "DELETE FROM {$this->db->get_table('notification_queue')}
             WHERE status = 'sent' AND sent_at < %s",
            array($cutoff_date)
        );

        $this->log_info('Cleaned up old sent emails', array(
            'deleted' => $deleted,
            'cutoff_date' => $cutoff_date
        ));

        return $deleted;
    }

    /**
     * Log info message
     */
    private function log_info($message, $context = array())
    {
        if ($this->settings['system']['debug_mode']) {
            $log_message = "WECOZA Email Service Info: {$message}";
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
        $log_message = "WECOZA Email Service Error: {$message}";
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        error_log($log_message);
    }
}