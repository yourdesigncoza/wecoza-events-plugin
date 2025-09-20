<?php
/**
 * Event model for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Event model class
 */
class EventModel
{
    /**
     * Database service instance
     */
    private $db;

    /**
     * Event configuration
     */
    private $event_config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new DatabaseService();
        $this->load_event_config();
    }

    /**
     * Load event configuration
     */
    private function load_event_config()
    {
        $this->event_config = include WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/events.php';
    }

    /**
     * Validate event payload
     */
    public function validate_event($event_data)
    {
        $errors = array();

        // Check required fields
        $required_fields = array('event', 'class_id', 'actor_id', 'occurred_at', 'idempotency_key');
        foreach ($required_fields as $field) {
            if (!isset($event_data[$field]) || empty($event_data[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate event name
        if (isset($event_data['event'])) {
            if (!$this->is_supported_event($event_data['event'])) {
                $errors[] = "Unsupported event type: {$event_data['event']}";
            }
        }

        // Validate class_id
        if (isset($event_data['class_id']) && !is_numeric($event_data['class_id'])) {
            $errors[] = "class_id must be numeric";
        }

        // Validate actor_id
        if (isset($event_data['actor_id']) && !is_numeric($event_data['actor_id'])) {
            $errors[] = "actor_id must be numeric";
        }

        // Validate occurred_at
        if (isset($event_data['occurred_at'])) {
            $timestamp = strtotime($event_data['occurred_at']);
            if ($timestamp === false) {
                $errors[] = "occurred_at must be a valid datetime";
            }
        }

        // Validate metadata if present
        if (isset($event_data['metadata']) && !is_array($event_data['metadata'])) {
            $errors[] = "metadata must be an array";
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Check if event type is supported
     */
    public function is_supported_event($event_name)
    {
        $supported_events = array(
            'class.created',
            'class.learners.loaded',
            'class.agent_order.submitted',
            'class.schedule.set',
            'class.material.delivery.confirmed',
            'class.agent.paperwork.submitted',
            'class.approved',
            'task.reminder.due'
        );

        return in_array($event_name, $supported_events);
    }

    /**
     * Sanitize event data
     */
    public function sanitize_event($event_data)
    {
        $sanitized = array();

        // Sanitize string fields
        $string_fields = array('event', 'source_plugin', 'idempotency_key');
        foreach ($string_fields as $field) {
            if (isset($event_data[$field])) {
                $sanitized[$field] = sanitize_text_field($event_data[$field]);
            }
        }

        // Sanitize numeric fields
        $numeric_fields = array('class_id', 'actor_id');
        foreach ($numeric_fields as $field) {
            if (isset($event_data[$field])) {
                $sanitized[$field] = intval($event_data[$field]);
            }
        }

        // Sanitize datetime
        if (isset($event_data['occurred_at'])) {
            $sanitized['occurred_at'] = sanitize_text_field($event_data['occurred_at']);
        }

        // Sanitize metadata array
        if (isset($event_data['metadata']) && is_array($event_data['metadata'])) {
            $sanitized['metadata'] = $this->sanitize_metadata($event_data['metadata']);
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata array
     */
    private function sanitize_metadata($metadata)
    {
        $sanitized = array();

        foreach ($metadata as $key => $value) {
            $clean_key = sanitize_key($key);

            if (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_metadata($value);
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = $value;
            } else {
                // Convert other types to string and sanitize
                $sanitized[$clean_key] = sanitize_text_field(strval($value));
            }
        }

        return $sanitized;
    }

    /**
     * Create event structure for specific event types
     */
    public function create_event($event_type, $class_id, $actor_id, $metadata = array())
    {
        $event_data = array(
            'event' => $event_type,
            'class_id' => $class_id,
            'actor_id' => $actor_id,
            'occurred_at' => current_time('mysql'),
            'idempotency_key' => $this->generate_idempotency_key($event_type, $class_id),
            'source_plugin' => 'wecoza-notifications-core',
            'metadata' => $metadata
        );

        return $this->sanitize_event($event_data);
    }

    /**
     * Generate idempotency key
     */
    public function generate_idempotency_key($event_type, $class_id, $suffix = null)
    {
        $key = "{$event_type}:{$class_id}";

        if ($suffix) {
            $key .= ":{$suffix}";
        } else {
            $key .= ":" . time();
        }

        return $key;
    }

    /**
     * Get event configuration for specific event type
     */
    public function get_event_config($event_type)
    {
        return isset($this->event_config[$event_type]) ? $this->event_config[$event_type] : null;
    }

    /**
     * Get all supported events
     */
    public function get_supported_events()
    {
        return array_keys($this->event_config);
    }

    /**
     * Save event to database
     */
    public function save_event($event_data)
    {
        // Validate event first
        $validation_result = $this->validate_event($event_data);
        if ($validation_result !== true) {
            return array('success' => false, 'errors' => $validation_result);
        }

        // Sanitize event data
        $sanitized_event = $this->sanitize_event($event_data);

        // Check for duplicate using idempotency key
        if ($this->is_duplicate_event($sanitized_event['idempotency_key'])) {
            return array('success' => false, 'error' => 'Duplicate event detected');
        }

        // Save to database
        $result = $this->db->log_event(
            $sanitized_event['event'],
            $sanitized_event['class_id'],
            json_encode($sanitized_event),
            $sanitized_event['source_plugin'] ?? 'unknown'
        );

        if ($result) {
            return array('success' => true, 'event_id' => $result);
        } else {
            return array('success' => false, 'error' => 'Failed to save event to database');
        }
    }

    /**
     * Check if event is duplicate
     */
    public function is_duplicate_event($idempotency_key)
    {
        return $this->db->event_exists($idempotency_key);
    }

    /**
     * Get events by class ID
     */
    public function get_events_by_class($class_id, $limit = 50)
    {
        return $this->db->get_events_by_class($class_id, $limit);
    }

    /**
     * Get recent events
     */
    public function get_recent_events($limit = 50)
    {
        return $this->db->get_recent_events($limit);
    }

    /**
     * Create class created event
     */
    public function create_class_created_event($class_id, $actor_id, $metadata)
    {
        $required_metadata = array('class_name', 'client_name', 'site_name', 'created_by');
        $missing_fields = array();

        foreach ($required_metadata as $field) {
            if (!isset($metadata[$field])) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            return array('success' => false, 'error' => 'Missing required metadata: ' . implode(', ', $missing_fields));
        }

        return $this->create_event('class.created', $class_id, $actor_id, $metadata);
    }

    /**
     * Create learners loaded event
     */
    public function create_learners_loaded_event($class_id, $actor_id, $learner_count, $metadata = array())
    {
        $metadata['learner_count'] = $learner_count;
        return $this->create_event('class.learners.loaded', $class_id, $actor_id, $metadata);
    }

    /**
     * Create agent order submitted event
     */
    public function create_agent_order_event($class_id, $actor_id, $order_details, $metadata = array())
    {
        $metadata['order_details'] = $order_details;
        return $this->create_event('class.agent_order.submitted', $class_id, $actor_id, $metadata);
    }

    /**
     * Create schedule set event
     */
    public function create_schedule_set_event($class_id, $actor_id, $schedule_data, $metadata = array())
    {
        $metadata['schedule_data'] = $schedule_data;
        return $this->create_event('class.schedule.set', $class_id, $actor_id, $metadata);
    }

    /**
     * Create material delivery confirmed event
     */
    public function create_material_delivery_event($class_id, $actor_id, $delivery_date, $metadata = array())
    {
        $metadata['delivery_date'] = $delivery_date;
        return $this->create_event('class.material.delivery.confirmed', $class_id, $actor_id, $metadata);
    }

    /**
     * Create agent paperwork submitted event
     */
    public function create_paperwork_submitted_event($class_id, $actor_id, $paperwork_details, $metadata = array())
    {
        $metadata['paperwork_details'] = $paperwork_details;
        return $this->create_event('class.agent.paperwork.submitted', $class_id, $actor_id, $metadata);
    }

    /**
     * Create class approved event
     */
    public function create_class_approved_event($class_id, $supervisor_id, $metadata = array())
    {
        $metadata['approved_by'] = $supervisor_id;
        $metadata['approval_date'] = current_time('mysql');
        return $this->create_event('class.approved', $class_id, $supervisor_id, $metadata);
    }

    /**
     * Create task reminder event
     */
    public function create_task_reminder_event($class_id, $task_type, $responsible_user_id, $due_date, $days_overdue = 0)
    {
        $metadata = array(
            'task_type' => $task_type,
            'responsible_user_id' => $responsible_user_id,
            'due_date' => $due_date,
            'days_overdue' => $days_overdue
        );

        return $this->create_event('task.reminder.due', $class_id, $responsible_user_id, $metadata);
    }

    /**
     * Log error
     */
    private function log_error($message, $context = array())
    {
        if (function_exists('error_log')) {
            $log_message = "WECOZA Notifications EventModel Error: {$message}";
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            error_log($log_message);
        }
    }
}