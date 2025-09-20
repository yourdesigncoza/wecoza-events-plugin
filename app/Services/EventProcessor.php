<?php
/**
 * Event processor service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

/**
 * Event processor class
 */
class EventProcessor
{
    /**
     * Database service instance
     */
    private $db;

    /**
     * Email service instance
     */
    private $email_service;

    /**
     * Event configuration
     */
    private $event_config;

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
        $this->email_service = new EmailService();
        $this->load_configurations();
    }

    /**
     * Load configurations
     */
    private function load_configurations()
    {
        $this->event_config = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/events.php';
        $this->settings = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/settings.php';
    }

    /**
     * Process incoming event
     */
    public function process_event($event_data)
    {
        // Validate event data
        if (!$this->validate_event_data($event_data)) {
            $this->log_error('Invalid event data', $event_data);
            return false;
        }

        // Check for duplicate events (idempotency)
        if ($this->db->event_exists($event_data['idempotency_key'])) {
            $this->log_info('Duplicate event ignored', array(
                'idempotency_key' => $event_data['idempotency_key'],
                'event' => $event_data['event']
            ));
            return true; // Not an error, just a duplicate
        }

        // Log the event
        $event_id = $this->db->log_event($event_data);
        if (!$event_id) {
            $this->log_error('Failed to log event', $event_data);
            return false;
        }

        // Process the event
        $result = $this->process_event_by_type($event_data, $event_id);

        if ($result) {
            // Mark event as processed
            $this->db->mark_event_processed($event_id);
            $this->log_info('Event processed successfully', array(
                'event_id' => $event_id,
                'event' => $event_data['event']
            ));
        } else {
            $this->log_error('Failed to process event', array(
                'event_id' => $event_id,
                'event_data' => $event_data
            ));
        }

        return $result;
    }

    /**
     * Process event by type
     */
    private function process_event_by_type($event_data, $event_id)
    {
        $event_name = $event_data['event'];

        // Check if event type is configured
        if (!isset($this->event_config[$event_name])) {
            $this->log_error('Unknown event type', array('event' => $event_name));
            return false;
        }

        $config = $this->event_config[$event_name];
        $success = true;

        // Special handling for class.created event
        if ($event_name === 'class.created') {
            $class_details = $this->get_class_details($event_data['class_id']);
            $this->initialize_class_dashboard($event_data['class_id'], $class_details['supervisor_id']);
        }

        // Update dashboard status if configured
        if (isset($config['dashboard_update'])) {
            $dashboard_success = $this->update_dashboard_status($event_data, $config['dashboard_update']);
            if (!$dashboard_success) {
                $this->log_error('Failed to update dashboard status', array(
                    'event' => $event_name,
                    'class_id' => $event_data['class_id']
                ));
                $success = false;
            }
        }

        // Process notifications if configured
        if (isset($config['notifications']) && is_array($config['notifications'])) {
            foreach ($config['notifications'] as $notification_config) {
                $notification_success = $this->process_notification($event_data, $notification_config);
                if (!$notification_success) {
                    $this->log_error('Failed to process notification', array(
                        'event' => $event_name,
                        'notification_config' => $notification_config
                    ));
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Initialize dashboard status for new class
     */
    public function initialize_class_dashboard($class_id, $supervisor_id = null)
    {
        $cron_service = new CronService();

        // Define the standard task flow for all classes
        $tasks = array(
            'load_learners' => array(
                'responsible_user_id' => $supervisor_id ?: 1,
                'due_days' => 3
            ),
            'agent_order' => array(
                'responsible_user_id' => $supervisor_id ?: 1,
                'due_days' => 5
            ),
            'training_schedule' => array(
                'responsible_user_id' => $supervisor_id ?: 1,
                'due_days' => 7
            ),
            'material_delivery' => array(
                'responsible_user_id' => $supervisor_id ?: 1,
                'due_days' => 10
            ),
            'agent_paperwork' => array(
                'responsible_user_id' => $supervisor_id ?: 1,
                'due_days' => 14
            ),
            'supervisor_approval' => array(
                'responsible_user_id' => $supervisor_id ?: 1,
                'due_days' => 2
            )
        );

        foreach ($tasks as $task_type => $task_config) {
            $due_date = date('Y-m-d H:i:s', strtotime('+' . $task_config['due_days'] . ' days'));
            $cron_service->create_dashboard_status($class_id, $task_type, $task_config['responsible_user_id'], $due_date);
        }

        $this->log_info('Dashboard status initialized for class', array('class_id' => $class_id));
        return true;
    }

    /**
     * Update dashboard status
     */
    private function update_dashboard_status($event_data, $dashboard_config)
    {
        if (!isset($event_data['class_id']) || !isset($dashboard_config['task_type'])) {
            return false;
        }

        $status_data = array(
            'class_id' => $event_data['class_id'],
            'task_type' => $dashboard_config['task_type'],
            'task_status' => $dashboard_config['status'],
            'responsible_user_id' => isset($event_data['actor_id']) ? $event_data['actor_id'] : null,
            'completed_at' => ($dashboard_config['status'] === 'completed') ? current_time('mysql') : null,
            'completion_data' => isset($event_data['metadata']) ? json_encode($event_data['metadata']) : null
        );

        // Check if status record exists
        $class_id = SecurityService::sanitize_int($event_data['class_id']);
        $task_type = SecurityService::sanitize_text($dashboard_config['task_type']);

        $existing = $this->db->get_row(
            "SELECT id FROM wecoza_events.dashboard_status WHERE class_id = $1 AND task_type = $2",
            array($class_id, $task_type)
        );

        if ($existing) {
            // Update existing record
            return $this->db->update(
                'dashboard_status',
                $status_data,
                array('id' => $existing['id'])
            );
        } else {
            // Insert new record
            return $this->db->insert('dashboard_status', $status_data);
        }
    }

    /**
     * Process notification
     */
    private function process_notification($event_data, $notification_config)
    {
        // Get recipients
        $recipients = $this->resolve_recipients($event_data, $notification_config['recipients']);
        if (empty($recipients)) {
            $this->log_info('No recipients found for notification', array(
                'event' => $event_data['event'],
                'recipients_type' => $notification_config['recipients']
            ));
            return true; // Not an error if no recipients
        }

        $success = true;

        // Process each channel
        $channels = isset($notification_config['channels']) ? $notification_config['channels'] : array('email');

        foreach ($channels as $channel) {
            foreach ($recipients as $recipient) {
                $notification_success = $this->queue_notification($event_data, $notification_config, $recipient, $channel);
                if (!$notification_success) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Resolve recipients based on type
     */
    private function resolve_recipients($event_data, $recipient_type)
    {
        $recipients = array();

        switch ($recipient_type) {
            case 'supervisor':
                $recipients = $this->get_supervisors_for_class($event_data);
                break;

            case 'responsible_user':
                if (isset($event_data['actor_id'])) {
                    $user = get_user_by('id', $event_data['actor_id']);
                    if ($user) {
                        $recipients[] = array(
                            'email' => $user->user_email,
                            'name' => $user->display_name,
                            'user_id' => $user->ID
                        );
                    }
                }
                break;

            case 'learners':
                $recipients = $this->get_learners_for_class($event_data);
                break;

            case 'agents':
                $recipients = $this->get_agents_for_class($event_data);
                break;

            case 'internal':
                // Internal notifications (dashboard only)
                $recipients[] = array(
                    'email' => get_option('admin_email'),
                    'name' => 'System',
                    'user_id' => 1
                );
                break;

            default:
                $this->log_error('Unknown recipient type', array('type' => $recipient_type));
        }

        return $recipients;
    }

    /**
     * Get supervisors for class
     */
    private function get_supervisors_for_class($event_data)
    {
        $supervisors = array();

        // Get class details to determine client/site
        $class_details = $this->get_class_details($event_data['class_id']);
        if (!$class_details) {
            return $supervisors;
        }

        // Query supervisors table for matching assignments using PostgreSQL JSONB
        $client_id = SecurityService::sanitize_int($class_details['client_id']);
        $site_id = SecurityService::sanitize_int($class_details['site_id']);

        $sql = "SELECT * FROM wecoza_events.supervisors
                WHERE is_active = true
                AND (
                    is_default = true
                    OR client_assignments @> $1::jsonb
                    OR site_assignments @> $2::jsonb
                )
                ORDER BY is_default ASC";

        $client_json = json_encode(array($client_id));
        $site_json = json_encode(array($site_id));

        $supervisor_records = $this->db->get_results($sql, array($client_json, $site_json));

        foreach ($supervisor_records as $supervisor) {
            $supervisors[] = array(
                'email' => $supervisor->email,
                'name' => $supervisor->name,
                'supervisor_id' => $supervisor->id
            );
        }

        return $supervisors;
    }

    /**
     * Get learners for class
     */
    private function get_learners_for_class($event_data)
    {
        $learners = array();

        // Query learners from the PostgreSQL classes table using JSONB
        $class_id = SecurityService::sanitize_int($event_data['class_id']);
        if ($class_id <= 0) {
            return array();
        }

        // Get learner IDs from classes table JSONB column
        $class = $this->db->get_row(
            "SELECT learner_ids FROM public.classes WHERE class_id = $1",
            array($class_id)
        );

        if ($class && !empty($class['learner_ids'])) {
            $learner_ids = json_decode($class['learner_ids'], true);
            if (is_array($learner_ids)) {
                foreach ($learner_ids as $learner_data) {
                    if (isset($learner_data['email']) && !empty($learner_data['email'])) {
                        $learners[] = array(
                            'email' => $learner_data['email'],
                            'name' => isset($learner_data['name']) ? $learner_data['name'] : 'Learner',
                            'phone' => isset($learner_data['phone']) ? $learner_data['phone'] : null,
                            'user_id' => null // Learners don't have WordPress user accounts
                        );
                    }
                }
            }
        }

        return $learners;
    }

    /**
     * Get agents for class
     */
    private function get_agents_for_class($event_data)
    {
        global $wpdb;

        $agents = array();

        // Get class details to find assigned agent
        $class_details = $this->get_class_details($event_data['class_id']);

        if ($class_details['agent_id']) {
            // Query agent details from WordPress users table (still MySQL)
            $agent_id = SecurityService::sanitize_int($class_details['agent_id']);
            if ($agent_id > 0) {
                $agent = $wpdb->get_row($wpdb->prepare(
                    "SELECT `ID`, `user_email`, `display_name`, `user_login`
                     FROM `{$wpdb->users}`
                     WHERE `ID` = %d",
                    $agent_id
                ));
            } else {
                $agent = null;
            }

            if ($agent && $agent->user_email) {
                $agents[] = array(
                    'email' => $agent->user_email,
                    'name' => $agent->display_name ?: $agent->user_login,
                    'user_id' => $agent->ID
                );
            }
        }

        // Also check for backup agents from classes table JSONB
        $class_id = SecurityService::sanitize_int($event_data['class_id']);
        if ($class_id > 0) {
            $class = $this->db->get_row(
                "SELECT backup_agent_ids FROM public.classes WHERE class_id = $1",
                array($class_id)
            );

            if ($class && !empty($class['backup_agent_ids'])) {
                $backup_agent_ids = json_decode($class['backup_agent_ids'], true);
                if (is_array($backup_agent_ids)) {
                    foreach ($backup_agent_ids as $agent_data) {
                        if (isset($agent_data['email']) && !empty($agent_data['email'])) {
                            $agents[] = array(
                                'email' => $agent_data['email'],
                                'name' => isset($agent_data['name']) ? $agent_data['name'] : 'Agent',
                                'phone' => isset($agent_data['phone']) ? $agent_data['phone'] : null,
                                'user_id' => null
                            );
                        }
                    }
                }
            }
        }

        return $agents;
    }

    /**
     * Get class details
     */
    private function get_class_details($class_id)
    {
        // Query the PostgreSQL classes table for class details
        $class_id = SecurityService::sanitize_int($class_id);
        if ($class_id <= 0) {
            return false;
        }

        // Use PostgreSQL syntax to query classes table from public schema
        $class = $this->db->get_row(
            "SELECT class_id, client_id, site_id, class_code, class_subject, project_supervisor_id, class_agent, created_at
             FROM public.classes
             WHERE class_id = $1",
            array($class_id)
        );

        if ($class) {
            return array(
                'class_id' => SecurityService::sanitize_int($class['class_id']),
                'client_id' => SecurityService::sanitize_int($class['client_id']),
                'site_id' => SecurityService::sanitize_int($class['site_id']),
                'class_name' => SecurityService::sanitize_text($class['class_code'] ?: sprintf(__('Class #%d', 'wecoza-notifications'), $class_id)),
                'client_name' => SecurityService::sanitize_text(sprintf(__('Client #%d', 'wecoza-notifications'), $class['client_id'])),
                'site_name' => SecurityService::sanitize_text(sprintf(__('Site #%d', 'wecoza-notifications'), $class['site_id'])),
                'supervisor_id' => SecurityService::sanitize_int($class['project_supervisor_id']),
                'learner_count' => 0, // Will be calculated from JSONB learner_ids
                'agent_id' => SecurityService::sanitize_int($class['class_agent']),
                'created_at' => SecurityService::sanitize_text($class['created_at'])
            );
        }

        // Return mock data if class not found
        return array(
            'class_id' => SecurityService::sanitize_int($class_id),
            'client_id' => SecurityService::sanitize_int($class_id),
            'site_id' => SecurityService::sanitize_int($class_id),
            'class_name' => SecurityService::sanitize_text(sprintf(__('Class #%d', 'wecoza-notifications'), $class_id)),
            'client_name' => SecurityService::sanitize_text(__('Unknown Client', 'wecoza-notifications')),
            'site_name' => SecurityService::sanitize_text(__('Unknown Site', 'wecoza-notifications')),
            'supervisor_id' => null,
            'learner_count' => 0,
            'agent_id' => null,
            'created_at' => current_time('mysql')
        );
    }

    /**
     * Queue notification for delivery
     */
    private function queue_notification($event_data, $notification_config, $recipient, $channel)
    {
        $notification_data = array(
            'event_name' => $event_data['event'],
            'idempotency_key' => $event_data['idempotency_key'] . '_' . $recipient['email'] . '_' . $channel,
            'recipient_email' => $recipient['email'],
            'recipient_name' => $recipient['name'],
            'channel' => $channel,
            'template_name' => $notification_config['template'],
            'payload' => array_merge($event_data, array('recipient' => $recipient))
        );

        return $this->db->queue_notification($notification_data);
    }

    /**
     * Backup polling for missed events
     */
    public function backup_polling()
    {
        // Poll the classes database for recent changes that might have been missed
        $this->log_info('Running backup polling for missed events');

        // Get unprocessed events from our log
        $unprocessed_events = $this->db->get_unprocessed_events(100);

        foreach ($unprocessed_events as $event) {
            $event_data = json_decode($event->event_payload, true);
            if ($event_data) {
                $this->log_info('Processing missed event from backup polling', array(
                    'event_id' => $event->id,
                    'event' => $event->event_name
                ));

                $result = $this->process_event_by_type($event_data, $event->id);
                if ($result) {
                    $this->db->mark_event_processed($event->id);
                }
            }
        }

        // Also check for class status changes directly in the classes database
        $this->poll_class_changes();
    }

    /**
     * Poll for class changes directly from classes database
     */
    private function poll_class_changes()
    {
        // This would check the classes plugin database for recent changes
        // and generate missing events
        $this->log_info('Polling for direct class changes');

        // Implementation will be added when classes plugin integration is complete
    }

    /**
     * Validate event data
     */
    private function validate_event_data($event_data)
    {
        if (!is_array($event_data)) {
            SecurityService::log_security_event('invalid_event_data', array('type' => 'not_array'));
            return false;
        }

        $required_fields = array('event', 'idempotency_key');
        foreach ($required_fields as $field) {
            if (!isset($event_data[$field]) || empty($event_data[$field])) {
                SecurityService::log_security_event('missing_required_field', array('field' => $field));
                return false;
            }
        }

        // Validate event name format
        $event_name = SecurityService::sanitize_text($event_data['event']);
        if (!preg_match('/^[a-z][a-z0-9_.]*[a-z0-9]$/', $event_name)) {
            SecurityService::log_security_event('invalid_event_name', array('event' => $event_name));
            return false;
        }

        // Validate idempotency key format
        $idempotency_key = SecurityService::sanitize_text($event_data['idempotency_key']);
        if (strlen($idempotency_key) > 255 || strlen($idempotency_key) < 10) {
            SecurityService::log_security_event('invalid_idempotency_key', array('key_length' => strlen($idempotency_key)));
            return false;
        }

        return true;
    }

    /**
     * Log info message
     */
    private function log_info($message, $context = array())
    {
        if ($this->settings['system']['debug_mode']) {
            $log_message = "WECOZA Notifications Info: {$message}";
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
        $log_message = "WECOZA Notifications Error: {$message}";
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        error_log($log_message);
    }
}