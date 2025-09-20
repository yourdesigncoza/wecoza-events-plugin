<?php
/**
 * Cron service for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include security service
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

class CronService
{
    private $db_service;
    private $email_service;

    public function __construct()
    {
        $this->db_service = PostgreSQLDatabaseService::get_instance();
        $this->email_service = new EmailService();
    }

    public function init()
    {
        $this->schedule_reminder_cron();
        add_action('wecoza_process_reminders', array($this, 'process_reminders'));
    }

    private function schedule_reminder_cron()
    {
        if (!wp_next_scheduled('wecoza_process_reminders')) {
            wp_schedule_event(time(), 'hourly', 'wecoza_process_reminders');
        }
    }

    public function process_reminders()
    {
        $current_time = current_time('mysql');
        $reminder_window = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Query pending tasks using PostgreSQL syntax
        $pending_tasks = $this->db_service->get_results("
            SELECT ds.*, c.class_code, c.project_supervisor_id, c.client_id
            FROM wecoza_events.dashboard_status ds
            LEFT JOIN public.classes c ON ds.class_id = c.class_id
            WHERE ds.task_status = 'pending'
            AND ds.due_date IS NOT NULL
            AND ds.due_date BETWEEN $1 AND $2
            AND (ds.last_reminder IS NULL OR ds.last_reminder < (CURRENT_TIMESTAMP - INTERVAL '24 hours'))
        ", array($current_time, $reminder_window));

        foreach ($pending_tasks as $task) {
            $this->send_reminder($task);
            $this->update_last_reminder($task['id']);
        }

        // Query overdue tasks using PostgreSQL syntax
        $overdue_tasks = $this->db_service->get_results("
            SELECT ds.*, c.class_code, c.project_supervisor_id, c.client_id
            FROM wecoza_events.dashboard_status ds
            LEFT JOIN public.classes c ON ds.class_id = c.class_id
            WHERE ds.task_status = 'pending'
            AND ds.due_date IS NOT NULL
            AND ds.due_date < $1
            AND (ds.overdue_notified = false OR ds.overdue_notified IS NULL)
        ", array($current_time));

        foreach ($overdue_tasks as $task) {
            $this->send_overdue_notice($task);
            $this->mark_overdue_notified($task['id']);
        }
    }

    private function send_reminder($task)
    {
        // Validate and sanitize task data
        $user_id = SecurityService::sanitize_int($task['responsible_user_id']);
        if ($user_id <= 0) {
            return false;
        }

        $responsible_user = get_userdata($user_id);
        if (!$responsible_user || !$responsible_user->user_email) {
            return false;
        }

        $template_data = array(
            'user_name' => SecurityService::escape_html($responsible_user->display_name),
            'task_name' => SecurityService::escape_html($this->get_task_display_name($task['task_type'])),
            'class_name' => SecurityService::escape_html($task['class_code'] ?: 'Class #' . $task['class_id']),
            'client_name' => SecurityService::escape_html('Client #' . $task['client_id']),
            'due_date' => SecurityService::escape_html(date('Y-m-d H:i', strtotime($task['due_date']))),
            'dashboard_url' => SecurityService::escape_url(home_url('/dashboard/')),
            'time_remaining' => SecurityService::escape_html($this->calculate_time_remaining($task['due_date'])),
            'urgency_level' => SecurityService::escape_html($this->calculate_urgency_level($task['due_date']))
        );

        $email_data = array(
            'to' => SecurityService::sanitize_email($responsible_user->user_email),
            'subject' => sprintf('[REMINDER] %s due soon for %s',
                SecurityService::sanitize_text($this->get_task_display_name($task['task_type'])),
                SecurityService::sanitize_text($task['class_code'] ?: 'Class #' . $task['class_id'])
            ),
            'template' => SecurityService::sanitize_text($this->get_reminder_template($task['task_type'])),
            'template_data' => $template_data,
            'event_type' => 'task.reminder',
            'class_id' => SecurityService::sanitize_int($task['class_id']),
            'user_id' => SecurityService::sanitize_int($task['responsible_user_id']),
            'priority' => $this->calculate_urgency_level($task['due_date']) === 'high' ? 'high' : 'normal'
        );

        return $this->email_service->queue_notification($email_data);
    }

    private function send_overdue_notice($task)
    {
        // Validate and sanitize user IDs
        $user_id = SecurityService::sanitize_int($task['responsible_user_id']);
        $supervisor_id = SecurityService::sanitize_int($task['project_supervisor_id']);

        $responsible_user = $user_id > 0 ? get_userdata($user_id) : null;
        $supervisor = $supervisor_id > 0 ? get_userdata($supervisor_id) : null;

        $template_data = array(
            'user_name' => SecurityService::escape_html($responsible_user ? $responsible_user->display_name : 'Unknown User'),
            'supervisor_name' => SecurityService::escape_html($supervisor ? $supervisor->display_name : 'Supervisor'),
            'task_name' => SecurityService::escape_html($this->get_task_display_name($task['task_type'])),
            'class_name' => SecurityService::escape_html($task['class_code'] ?: 'Class #' . $task['class_id']),
            'client_name' => SecurityService::escape_html('Client #' . $task['client_id']),
            'due_date' => SecurityService::escape_html(date('Y-m-d H:i', strtotime($task['due_date']))),
            'overdue_by' => SecurityService::escape_html($this->calculate_overdue_time($task['due_date'])),
            'dashboard_url' => SecurityService::escape_url(home_url('/dashboard/')),
            'escalation_date' => SecurityService::escape_html(date('Y-m-d H:i', strtotime('+24 hours')))
        );

        if ($responsible_user && $responsible_user->user_email) {
            $user_email_data = array(
                'to' => SecurityService::sanitize_email($responsible_user->user_email),
                'subject' => sprintf('[OVERDUE] %s is overdue for %s',
                    SecurityService::sanitize_text($this->get_task_display_name($task['task_type'])),
                    SecurityService::sanitize_text($task['class_code'] ?: 'Class #' . $task['class_id'])
                ),
                'template' => 'task_overdue_user',
                'template_data' => $template_data,
                'event_type' => 'task.overdue',
                'class_id' => SecurityService::sanitize_int($task['class_id']),
                'user_id' => SecurityService::sanitize_int($task['responsible_user_id']),
                'priority' => 'high'
            );
            $this->email_service->queue_notification($user_email_data);
        }

        if ($supervisor && $supervisor->user_email) {
            $supervisor_email_data = array(
                'to' => SecurityService::sanitize_email($supervisor->user_email),
                'subject' => sprintf('[ESCALATION] Overdue task for %s',
                    SecurityService::sanitize_text($task['class_code'] ?: 'Class #' . $task['class_id'])
                ),
                'template' => 'task_overdue_supervisor',
                'template_data' => $template_data,
                'event_type' => 'task.overdue.escalation',
                'class_id' => SecurityService::sanitize_int($task['class_id']),
                'user_id' => SecurityService::sanitize_int($task['project_supervisor_id']),
                'priority' => 'high'
            );
            $this->email_service->queue_notification($supervisor_email_data);
        }

        return true;
    }

    public function calculate_due_date($task_type, $event_date)
    {
        $config = include(WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/settings.php');
        $reminder_settings = $config['reminders'];

        $due_days = isset($reminder_settings['due_dates'][$task_type])
            ? $reminder_settings['due_dates'][$task_type]
            : $reminder_settings['default_due_days'];

        return date('Y-m-d H:i:s', strtotime($event_date . ' + ' . $due_days . ' days'));
    }

    public function calculate_reminder_time($due_date, $task_type)
    {
        $config = include(WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'config/settings.php');
        $reminder_settings = $config['reminders'];

        $reminder_hours = isset($reminder_settings['reminder_hours'][$task_type])
            ? $reminder_settings['reminder_hours'][$task_type]
            : $reminder_settings['default_reminder_hours'];

        return date('Y-m-d H:i:s', strtotime($due_date . ' - ' . $reminder_hours . ' hours'));
    }

    private function calculate_time_remaining($due_date)
    {
        $now = current_time('timestamp');
        $due = strtotime($due_date);
        $diff = $due - $now;

        if ($diff <= 0) {
            return __('Overdue', 'wecoza-notifications');
        }

        $days = floor($diff / (24 * 3600));
        $hours = floor(($diff % (24 * 3600)) / 3600);

        if ($days > 0) {
            return sprintf(_n('%d day remaining', '%d days remaining', $days, 'wecoza-notifications'), $days);
        } elseif ($hours > 0) {
            return sprintf(_n('%d hour remaining', '%d hours remaining', $hours, 'wecoza-notifications'), $hours);
        } else {
            return __('Due very soon', 'wecoza-notifications');
        }
    }

    private function calculate_overdue_time($due_date)
    {
        $now = current_time('timestamp');
        $due = strtotime($due_date);
        $diff = $now - $due;

        $days = floor($diff / (24 * 3600));
        $hours = floor(($diff % (24 * 3600)) / 3600);

        if ($days > 0) {
            return $days . ' day' . ($days > 1 ? 's' : '');
        } elseif ($hours > 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        } else {
            return 'less than 1 hour';
        }
    }

    private function calculate_urgency_level($due_date)
    {
        $now = current_time('timestamp');
        $due = strtotime($due_date);
        $hours_until_due = ($due - $now) / 3600;

        if ($hours_until_due <= 0) {
            return 'overdue';
        } elseif ($hours_until_due <= 6) {
            return 'critical';
        } elseif ($hours_until_due <= 24) {
            return 'high';
        } elseif ($hours_until_due <= 72) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function get_task_display_name($task_type)
    {
        $task_names = array(
            'load_learners' => __('Load Learners', 'wecoza-notifications'),
            'agent_order' => __('Submit Agent Order', 'wecoza-notifications'),
            'training_schedule' => __('Set Training Schedule', 'wecoza-notifications'),
            'material_delivery' => __('Confirm Material Delivery', 'wecoza-notifications'),
            'agent_paperwork' => __('Complete Agent Paperwork', 'wecoza-notifications'),
            'supervisor_approval' => __('Supervisor Approval Required', 'wecoza-notifications')
        );

        return isset($task_names[$task_type]) ? $task_names[$task_type] : ucfirst(str_replace('_', ' ', $task_type));
    }

    private function get_reminder_template($task_type)
    {
        $template_map = array(
            'load_learners' => 'reminder_load_learners',
            'agent_order' => 'reminder_agent_order',
            'training_schedule' => 'reminder_training_schedule',
            'material_delivery' => 'reminder_material_delivery',
            'agent_paperwork' => 'reminder_agent_paperwork',
            'supervisor_approval' => 'reminder_supervisor_approval'
        );

        return isset($template_map[$task_type]) ? $template_map[$task_type] : 'reminder_generic';
    }

    private function update_last_reminder($task_id)
    {
        $task_id = SecurityService::sanitize_int($task_id);
        if ($task_id <= 0) {
            return false;
        }

        return $this->db_service->update(
            'dashboard_status',
            array('last_reminder' => current_time('mysql')),
            array('id' => $task_id)
        );
    }

    private function mark_overdue_notified($task_id)
    {
        $task_id = SecurityService::sanitize_int($task_id);
        if ($task_id <= 0) {
            return false;
        }

        return $this->db_service->update(
            'dashboard_status',
            array('overdue_notified' => true),
            array('id' => $task_id)
        );
    }

    public function mark_task_complete($class_id, $task_type)
    {
        $class_id = SecurityService::sanitize_int($class_id);
        $task_type = SecurityService::sanitize_text($task_type);

        if ($class_id <= 0 || empty($task_type)) {
            return false;
        }

        $result = $this->db_service->update(
            'dashboard_status',
            array(
                'task_status' => 'completed',
                'completed_at' => current_time('mysql'),
                'overdue_notified' => false
            ),
            array(
                'class_id' => $class_id,
                'task_type' => $task_type
            )
        );

        if ($result) {
            do_action('wecoza_task_completed', $class_id, $task_type);
        }

        return $result;
    }

    public function create_dashboard_status($class_id, $task_type, $responsible_user_id, $due_date = null)
    {
        if (!$due_date) {
            $due_date = $this->calculate_due_date($task_type, current_time('mysql'));
        }

        $class_id = SecurityService::sanitize_int($class_id);
        $task_type = SecurityService::sanitize_text($task_type);
        $responsible_user_id = SecurityService::sanitize_int($responsible_user_id);

        if ($class_id <= 0 || empty($task_type) || $responsible_user_id <= 0) {
            return false;
        }

        $existing = $this->db_service->get_row(
            "SELECT id FROM wecoza_events.dashboard_status WHERE class_id = $1 AND task_type = $2",
            array($class_id, $task_type)
        );

        if ($existing) {
            return $this->db_service->update(
                'dashboard_status',
                array(
                    'responsible_user_id' => $responsible_user_id,
                    'due_date' => $due_date,
                    'task_status' => 'pending',
                    'completed_at' => null,
                    'last_reminder' => null,
                    'overdue_notified' => false
                ),
                array('id' => $existing['id'])
            );
        } else {
            return $this->db_service->insert(
                'dashboard_status',
                array(
                    'class_id' => $class_id,
                    'task_type' => $task_type,
                    'responsible_user_id' => $responsible_user_id,
                    'due_date' => $due_date,
                    'task_status' => 'pending'
                )
            );
        }
    }

    public function get_pending_tasks($user_id = null, $class_id = null)
    {
        $where_conditions = array('ds.task_status = $1');
        $params = array('pending');
        $param_index = 2;

        if ($user_id) {
            $user_id = SecurityService::sanitize_int($user_id);
            if ($user_id > 0) {
                $where_conditions[] = "ds.responsible_user_id = \${$param_index}";
                $params[] = $user_id;
                $param_index++;
            }
        }

        if ($class_id) {
            $class_id = SecurityService::sanitize_int($class_id);
            if ($class_id > 0) {
                $where_conditions[] = "ds.class_id = \${$param_index}";
                $params[] = $class_id;
                $param_index++;
            }
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "
            SELECT ds.*, c.class_code, c.project_supervisor_id, c.client_id,
                   u.display_name as responsible_user_name
            FROM wecoza_events.dashboard_status ds
            LEFT JOIN public.classes c ON ds.class_id = c.class_id
            LEFT JOIN public.users u ON ds.responsible_user_id = u.ID
            WHERE {$where_clause}
            ORDER BY ds.due_date ASC
        ";

        return $this->db_service->get_results($query, $params);
    }

    public function get_overdue_tasks($user_id = null)
    {
        $where_conditions = array(
            'ds.task_status = $1',
            'ds.due_date < $2'
        );
        $params = array('pending', current_time('mysql'));
        $param_index = 3;

        if ($user_id) {
            $user_id = SecurityService::sanitize_int($user_id);
            if ($user_id > 0) {
                $where_conditions[] = "ds.responsible_user_id = \${$param_index}";
                $params[] = $user_id;
                $param_index++;
            }
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "
            SELECT ds.*, c.class_code, c.project_supervisor_id, c.client_id,
                   u.display_name as responsible_user_name
            FROM wecoza_events.dashboard_status ds
            LEFT JOIN public.classes c ON ds.class_id = c.class_id
            LEFT JOIN public.users u ON ds.responsible_user_id = u.ID
            WHERE {$where_clause}
            ORDER BY ds.due_date ASC
        ";

        return $this->db_service->get_results($query, $params);
    }

    public function get_task_statistics($user_id = null)
    {
        $user_id = $user_id ? SecurityService::sanitize_int($user_id) : null;
        $stats = array();

        if ($user_id && $user_id > 0) {
            $result = $this->db_service->get_row("
                SELECT
                    COUNT(CASE WHEN task_status = 'pending' THEN 1 END) as total_pending,
                    COUNT(CASE WHEN task_status = 'pending' AND due_date < CURRENT_TIMESTAMP THEN 1 END) as overdue,
                    COUNT(CASE WHEN task_status = 'pending' AND DATE(due_date) = CURRENT_DATE THEN 1 END) as due_today,
                    COUNT(CASE WHEN task_status = 'completed' AND completed_at >= (CURRENT_TIMESTAMP - INTERVAL '7 days') THEN 1 END) as completed_this_week
                FROM wecoza_events.dashboard_status
                WHERE responsible_user_id = $1
            ", array($user_id));
        } else {
            $result = $this->db_service->get_row("
                SELECT
                    COUNT(CASE WHEN task_status = 'pending' THEN 1 END) as total_pending,
                    COUNT(CASE WHEN task_status = 'pending' AND due_date < CURRENT_TIMESTAMP THEN 1 END) as overdue,
                    COUNT(CASE WHEN task_status = 'pending' AND DATE(due_date) = CURRENT_DATE THEN 1 END) as due_today,
                    COUNT(CASE WHEN task_status = 'completed' AND completed_at >= (CURRENT_TIMESTAMP - INTERVAL '7 days') THEN 1 END) as completed_this_week
                FROM wecoza_events.dashboard_status
            ");
        }

        return $result ? $result : array(
            'total_pending' => 0,
            'overdue' => 0,
            'due_today' => 0,
            'completed_this_week' => 0
        );
    }
}