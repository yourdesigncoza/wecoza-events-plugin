<?php
/**
 * Email template: Agent Paperwork Reminder
 * Event: task.reminder.due (task_type: agent_paperwork)
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
    <title>Reminder: Complete Agent Paperwork</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #e5780b; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .reminder-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 20px 0; border-radius: 5px; color: #856404; }
        .overdue-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #721c24; }
        .instructions-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .final-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #155724; }
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
            <h1><span class="reminder-icon">📄</span> Agent Paperwork Required</h1>
            <p>Final Step Before Class Approval</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p>The following training class requires agent paperwork to be completed - this is the final step before supervisor approval:</p>

            <?php if (isset($days_overdue) && $days_overdue > 0): ?>
            <div class="overdue-box">
                <h3><span class="urgent-icon">🚨</span> OVERDUE: Agent Paperwork</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Due Date:</strong> {due_date}</p>
                <p><strong>Days Overdue:</strong> {days_overdue} days</p>
                <p><strong>Status:</strong> Urgent - final step incomplete</p>
            </div>
            <?php else: ?>
            <div class="reminder-box">
                <h3>📄 Final Task: Complete Agent Paperwork</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Due Date:</strong> {due_date}</p>
                <p><strong>Status:</strong> Last step before approval</p>
            </div>
            <?php endif; ?>

            <div class="final-box">
                <h3>🏁 Almost Ready!</h3>
                <p><strong>Completed tasks:</strong></p>
                <ul>
                    <li>✅ Learners loaded</li>
                    <li>✅ Agent order submitted</li>
                    <li>✅ Training schedule set</li>
                    <li>✅ Materials delivered</li>
                </ul>
                <p><strong>Remaining:</strong> Agent paperwork (this task)</p>
            </div>

            <div class="instructions-box">
                <h3>📋 What you need to do:</h3>
                <ol>
                    <li>Complete all required agent documentation</li>
                    <li>Verify agent credentials and certifications</li>
                    <li>Submit signed agreements and contracts</li>
                    <li>Upload all documents to the system</li>
                    <li>Confirm paperwork completion</li>
                </ol>
                <p><strong>Note:</strong> Once this is complete, the class will be ready for supervisor approval and learner enrollment can begin.</p>
            </div>

            <p>This is the final administrative step! Once you complete the agent paperwork, the supervisor will be notified that the class is ready for approval, and the enrollment process can begin.</p>

            <p style="text-align: center;">
                <?php if (isset($days_overdue) && $days_overdue > 0): ?>
                <a href="{class_url}" class="btn-urgent">Complete Paperwork Now</a>
                <?php else: ?>
                <a href="{class_url}" class="btn">Complete Agent Paperwork</a>
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