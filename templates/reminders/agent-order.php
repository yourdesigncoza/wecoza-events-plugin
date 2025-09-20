<?php
/**
 * Email template: Agent Order Reminder
 * Event: task.reminder.due (task_type: agent_order)
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
    <title>Reminder: Submit Agent Order</title>
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
            <h1><span class="reminder-icon">👨‍🏫</span> Agent Order Required</h1>
            <p>Training Instructor Assignment Needed</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p>The following training class requires an agent order to be submitted for instructor assignment:</p>

            <?php if (isset($days_overdue) && $days_overdue > 0): ?>
            <div class="overdue-box">
                <h3><span class="urgent-icon">🚨</span> OVERDUE: Agent Order</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Due Date:</strong> {due_date}</p>
                <p><strong>Days Overdue:</strong> {days_overdue} days</p>
                <p><strong>Status:</strong> Urgent - no instructor assigned</p>
            </div>
            <?php else: ?>
            <div class="reminder-box">
                <h3>👨‍🏫 Task: Submit Agent Order</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Due Date:</strong> {due_date}</p>
                <p><strong>Status:</strong> Awaiting agent assignment</p>
            </div>
            <?php endif; ?>

            <div class="instructions-box">
                <h3>📋 What you need to do:</h3>
                <ol>
                    <li>Review the class requirements and schedule</li>
                    <li>Submit an agent order request</li>
                    <li>Specify any special requirements or preferences</li>
                    <li>Confirm the order submission</li>
                </ol>
                <p><strong>Note:</strong> Without an assigned agent, the class cannot proceed to scheduling and final preparation phases.</p>
            </div>

            <p>The training cannot be scheduled or delivered without a qualified instructor. Please submit the agent order as soon as possible to keep the class on track.</p>

            <p style="text-align: center;">
                <?php if (isset($days_overdue) && $days_overdue > 0): ?>
                <a href="{class_url}" class="btn-urgent">Submit Agent Order Now</a>
                <?php else: ?>
                <a href="{class_url}" class="btn">Submit Agent Order</a>
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