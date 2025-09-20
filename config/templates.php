<?php
/**
 * Email template definitions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return array(
    // Confirmation templates
    'class_created_supervisor' => array(
        'subject' => 'New Class Created: {class_name} / {client_name}',
        'template_file' => 'confirmations/class-created-supervisor.php',
        'variables' => array('class_name', 'client_name', 'site_name', 'created_by', 'class_url', 'dashboard_url')
    ),

    'learners_loaded_dashboard' => array(
        'subject' => 'Learners Loaded: {class_name}',
        'template_file' => 'confirmations/learners-loaded-dashboard.php',
        'variables' => array('class_name', 'learner_count', 'class_url', 'dashboard_url')
    ),

    'agent_order_submitted' => array(
        'subject' => 'Agent Order Submitted: {class_name}',
        'template_file' => 'confirmations/agent-order-submitted.php',
        'variables' => array('class_name', 'order_details', 'submitted_by', 'class_url', 'dashboard_url')
    ),

    'schedule_confirmed' => array(
        'subject' => 'Training Schedule Confirmed: {class_name}',
        'template_file' => 'confirmations/schedule-confirmed.php',
        'variables' => array('class_name', 'schedule_details', 'start_date', 'end_date', 'class_url', 'dashboard_url')
    ),

    'material_delivery_confirmed' => array(
        'subject' => 'Material Delivery Confirmed: {class_name}',
        'template_file' => 'confirmations/material-delivery-confirmed.php',
        'variables' => array('class_name', 'delivery_details', 'delivery_date', 'class_url', 'dashboard_url')
    ),

    'paperwork_submitted' => array(
        'subject' => 'Agent Paperwork Submitted: {class_name}',
        'template_file' => 'confirmations/paperwork-submitted.php',
        'variables' => array('class_name', 'paperwork_details', 'submitted_by', 'class_url', 'dashboard_url')
    ),

    'class_approved_supervisor' => array(
        'subject' => 'Class Approved: {class_name}',
        'template_file' => 'confirmations/class-approved-supervisor.php',
        'variables' => array('class_name', 'approval_date', 'class_url', 'dashboard_url')
    ),

    'enrollment_confirmed' => array(
        'subject' => 'Class Enrollment Confirmed: {class_name}',
        'template_file' => 'confirmations/enrollment-confirmed.php',
        'variables' => array('class_name', 'start_date', 'end_date', 'site_name', 'schedule_details', 'contact_info')
    ),

    'agent_assigned' => array(
        'subject' => 'Agent Assignment: {class_name}',
        'template_file' => 'confirmations/agent-assigned.php',
        'variables' => array('class_name', 'site_name', 'schedule_details', 'learner_count', 'contact_info')
    ),

    // Reminder templates
    'task_reminder' => array(
        'subject' => 'Reminder: Task Due - {task_name}',
        'template_file' => 'reminders/task-reminder.php',
        'variables' => array('task_name', 'class_name', 'due_date', 'days_overdue', 'task_url', 'dashboard_url')
    ),

    'reminder_load_learners' => array(
        'subject' => 'Reminder: Load Learners - {class_name}',
        'template_file' => 'reminders/load-learners.php',
        'variables' => array('class_name', 'due_date', 'days_overdue', 'class_url', 'dashboard_url')
    ),

    'reminder_agent_order' => array(
        'subject' => 'Reminder: Submit Agent Order - {class_name}',
        'template_file' => 'reminders/agent-order.php',
        'variables' => array('class_name', 'due_date', 'days_overdue', 'class_url', 'dashboard_url')
    ),

    'reminder_training_schedule' => array(
        'subject' => 'Reminder: Set Training Schedule - {class_name}',
        'template_file' => 'reminders/training-schedule.php',
        'variables' => array('class_name', 'due_date', 'days_overdue', 'class_url', 'dashboard_url')
    ),

    'reminder_material_delivery' => array(
        'subject' => 'Reminder: Arrange Material Delivery - {class_name}',
        'template_file' => 'reminders/material-delivery.php',
        'variables' => array('class_name', 'due_date', 'days_overdue', 'class_url', 'dashboard_url')
    ),

    'reminder_agent_paperwork' => array(
        'subject' => 'Reminder: Complete Agent Paperwork - {class_name}',
        'template_file' => 'reminders/agent-paperwork.php',
        'variables' => array('class_name', 'due_date', 'days_overdue', 'class_url', 'dashboard_url')
    )
);