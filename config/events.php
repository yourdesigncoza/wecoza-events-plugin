<?php
/**
 * Event definitions configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return array(
    // EVT-01: Class Created
    'class.created' => array(
        'name' => 'Class Created',
        'description' => 'Triggered when a new class is created',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'supervisor',
                'template' => 'class_created_supervisor',
                'channels' => array('email', 'dashboard')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'class_created',
            'status' => 'open'
        )
    ),

    // EVT-02: Load Learners
    'class.learners.loaded' => array(
        'name' => 'Learners Loaded',
        'description' => 'Triggered when learners are loaded into a class',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'internal',
                'template' => 'learners_loaded_dashboard',
                'channels' => array('dashboard')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'load_learners',
            'status' => 'completed'
        )
    ),

    // EVT-03: Agent Order
    'class.agent_order.submitted' => array(
        'name' => 'Agent Order Submitted',
        'description' => 'Triggered when agent order is submitted',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'supervisor',
                'template' => 'agent_order_submitted',
                'channels' => array('email', 'dashboard')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'agent_order',
            'status' => 'completed'
        )
    ),

    // EVT-04: Training Schedule
    'class.schedule.set' => array(
        'name' => 'Training Schedule Set',
        'description' => 'Triggered when training schedule is confirmed',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'supervisor',
                'template' => 'schedule_confirmed',
                'channels' => array('email', 'dashboard')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'training_schedule',
            'status' => 'completed'
        )
    ),

    // EVT-05: Material Delivery
    'class.material.delivery.confirmed' => array(
        'name' => 'Material Delivery Confirmed',
        'description' => 'Triggered when material delivery is confirmed',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'supervisor',
                'template' => 'material_delivery_confirmed',
                'channels' => array('email', 'dashboard')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'material_delivery',
            'status' => 'completed'
        )
    ),

    // EVT-06: Agent Paperwork
    'class.agent.paperwork.submitted' => array(
        'name' => 'Agent Paperwork Submitted',
        'description' => 'Triggered when agent paperwork is submitted',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'supervisor',
                'template' => 'paperwork_submitted',
                'channels' => array('email', 'dashboard')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'agent_paperwork',
            'status' => 'completed'
        )
    ),

    // EVT-01a: Supervisor Approval
    'class.approved' => array(
        'name' => 'Class Approved',
        'description' => 'Triggered when supervisor approves the class',
        'notifications' => array(
            array(
                'type' => 'confirmation',
                'recipients' => 'supervisor',
                'template' => 'class_approved_supervisor',
                'channels' => array('email', 'dashboard')
            ),
            array(
                'type' => 'confirmation',
                'recipients' => 'learners',
                'template' => 'enrollment_confirmed',
                'channels' => array('email')
            ),
            array(
                'type' => 'confirmation',
                'recipients' => 'agents',
                'template' => 'agent_assigned',
                'channels' => array('email')
            )
        ),
        'dashboard_update' => array(
            'task_type' => 'supervisor_approval',
            'status' => 'completed'
        )
    ),

    // System-generated reminder events
    'task.reminder.due' => array(
        'name' => 'Task Reminder Due',
        'description' => 'System-generated reminder for overdue tasks',
        'notifications' => array(
            array(
                'type' => 'reminder',
                'recipients' => 'responsible_user',
                'template' => 'task_reminder',
                'channels' => array('email', 'dashboard')
            )
        )
    ),

    // Task-specific reminder events
    'task.reminder.load_learners' => array(
        'name' => 'Load Learners Reminder',
        'description' => 'Reminder to load learners',
        'notifications' => array(
            array(
                'type' => 'reminder',
                'recipients' => 'responsible_user',
                'template' => 'reminder_load_learners',
                'channels' => array('email', 'dashboard')
            )
        )
    ),

    'task.reminder.agent_order' => array(
        'name' => 'Agent Order Reminder',
        'description' => 'Reminder to submit agent order',
        'notifications' => array(
            array(
                'type' => 'reminder',
                'recipients' => 'responsible_user',
                'template' => 'reminder_agent_order',
                'channels' => array('email', 'dashboard')
            )
        )
    ),

    'task.reminder.training_schedule' => array(
        'name' => 'Training Schedule Reminder',
        'description' => 'Reminder to set training schedule',
        'notifications' => array(
            array(
                'type' => 'reminder',
                'recipients' => 'responsible_user',
                'template' => 'reminder_training_schedule',
                'channels' => array('email', 'dashboard')
            )
        )
    ),

    'task.reminder.material_delivery' => array(
        'name' => 'Material Delivery Reminder',
        'description' => 'Reminder to arrange material delivery',
        'notifications' => array(
            array(
                'type' => 'reminder',
                'recipients' => 'responsible_user',
                'template' => 'reminder_material_delivery',
                'channels' => array('email', 'dashboard')
            )
        )
    ),

    'task.reminder.agent_paperwork' => array(
        'name' => 'Agent Paperwork Reminder',
        'description' => 'Reminder to complete agent paperwork',
        'notifications' => array(
            array(
                'type' => 'reminder',
                'recipients' => 'responsible_user',
                'template' => 'reminder_agent_paperwork',
                'channels' => array('email', 'dashboard')
            )
        )
    )
);
