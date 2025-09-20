<?php
/**
 * Email template: Class Created - Supervisor Confirmation
 * Event: EVT-01 (class.created)
 * Recipient: Supervisors
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
    <title>New Class Created</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #3874ff; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .info-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .btn { background: #3874ff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .tasks-list { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .tasks-list h4 { color: #856404; margin-top: 0; }
        .tasks-list ul { margin: 10px 0; padding-left: 20px; }
        .tasks-list li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Training Class Created</h1>
            <p>Requires Your Oversight</p>
        </div>

        <div class="content">
            <p>Dear Supervisor,</p>

            <p>A new training class has been created and requires your oversight:</p>

            <div class="info-box">
                <h3>Class Details</h3>
                <ul>
                    <li><strong>Class Name:</strong> {class_name}</li>
                    <li><strong>Client:</strong> {client_name}</li>
                    <li><strong>Site:</strong> {site_name}</li>
                    <li><strong>Created By:</strong> {created_by}</li>
                </ul>
            </div>

            <div class="tasks-list">
                <h4>📋 Next Steps Required</h4>
                <p>The following tasks need to be completed before the class can begin:</p>
                <ul>
                    <li>Load learners into the class</li>
                    <li>Submit agent order</li>
                    <li>Set training schedule</li>
                    <li>Arrange material delivery</li>
                    <li>Complete agent paperwork</li>
                </ul>
                <p><strong>You will need to approve the class once all tasks are complete.</strong></p>
            </div>

            <p>You can monitor progress and manage this class using the links below:</p>

            <p style="text-align: center;">
                <a href="{class_url}" class="btn">View Class Details</a>
                <a href="{dashboard_url}" class="btn">Go to Dashboard</a>
            </p>

            <p>You will receive confirmation emails as each task is completed, and a final notification when the class is ready for your approval.</p>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            <strong>WECOZA Training Management System</strong></p>
            <p><small>This is an automated notification. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>