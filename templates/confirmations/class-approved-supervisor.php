<?php
/**
 * Email template: Class Approved - Supervisor Confirmation
 * Event: EVT-01a (class.approved)
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
    <title>Class Approved</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #0097eb; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .approved-box { background: #cff4fc; border: 1px solid #9eeaf9; padding: 20px; margin: 20px 0; border-radius: 5px; color: #055160; }
        .btn { background: #0097eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .approval-icon { font-size: 24px; color: #0097eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="approval-icon">👍</span> Class Approved</h1>
            <p>Enrollment Process Initiated</p>
        </div>

        <div class="content">
            <p>Dear Supervisor,</p>

            <div class="approved-box">
                <h3>✅ Class Approval Confirmed</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Approval Date:</strong> {approval_date}</p>
                <p><strong>Status:</strong> Approved and active</p>
            </div>

            <p>The training class has been successfully approved and the enrollment process has been initiated.</p>

            <p><strong>What happens next:</strong></p>
            <ul>
                <li>Enrollment notifications sent to all learners</li>
                <li>Assignment notifications sent to training agents</li>
                <li>Class details and schedules shared with participants</li>
                <li>Final preparations coordinated</li>
            </ul>

            <p>You will continue to receive updates on the class progress and can monitor everything through the dashboard.</p>

            <p style="text-align: center;">
                <a href="{class_url}" class="btn">View Class Details</a>
                <a href="{dashboard_url}" class="btn">Go to Dashboard</a>
            </p>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            <strong>WECOZA Training Management System</strong></p>
        </div>
    </div>
</body>
</html>