<?php
/**
 * Email template: Load Learners Reminder
 * Event: task.reminder.due (task_type: load_learners)
 * Recipient: Responsible User
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder: Load Learners</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #e5780b; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .reminder-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 20px 0; border-radius: 5px; color: #856404; }
        .overdue-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #721c24; }
        .instructions-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .btn { background: #e5780b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; font-weight: bold; }
        .btn-urgent { background: #fa3b1d; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .reminder-icon { font-size: 24px; color: #e5780b; }
        .urgent-icon { font-size: 24px; color: #fa3b1d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="reminder-icon">👥</span> Load Learners Required</h1>
            <p>Action Required for Class Setup</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p>The following training class is waiting for learners to be loaded into the system:</p>

            <?php if (isset($days_overdue) && $days_overdue > 0): ?>
            <div class="overdue-box">
                <h3><span class="urgent-icon">🚨</span> OVERDUE: Load Learners</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Due Date:</strong> {due_date}</p>
                <p><strong>Days Overdue:</strong> {days_overdue} days</p>
                <p><strong>Status:</strong> Urgent - blocking class progression</p>
            </div>
            <?php else: ?>
            <div class="reminder-box">
                <h3>👥 Task: Load Learners</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Due Date:</strong> {due_date}</p>
                <p><strong>Status:</strong> Awaiting learner roster</p>
            </div>
            <?php endif; ?>

            <div class="instructions-box">
                <h3>📋 What you need to do:</h3>
                <ol>
                    <li>Access the class management system</li>
                    <li>Upload or enter the learner roster</li>
                    <li>Verify all learner information is correct</li>
                    <li>Confirm the learner list</li>
                </ol>
                <p><strong>Note:</strong> The class cannot proceed to agent assignment and scheduling until learners are loaded.</p>
            </div>

            <p>Other team members are waiting for this step to be completed so they can continue with agent assignment, scheduling, and material preparation.</p>

            <p style="text-align: center;">
                <?php if (isset($days_overdue) && $days_overdue > 0): ?>
                <a href="{class_url}" class="btn-urgent">Load Learners Now</a>
                <?php else: ?>
                <a href="{class_url}" class="btn">Load Learners</a>
                <?php endif; ?>
            </p>

            <p style="text-align: center;">
                <a href="{dashboard_url}" class="btn">View Dashboard</a>
            </p>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            <strong>WECOZA Training Management System</strong></p>
        </div>
    </div>
</body>
</html>