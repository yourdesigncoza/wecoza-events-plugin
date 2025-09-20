<?php
/**
 * Email template: Learners Loaded - Dashboard Update
 * Event: EVT-02 (class.learners.loaded)
 * Recipient: Dashboard Status Update
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
    <title>Learners Loaded</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #25b003; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #155724; }
        .btn { background: #25b003; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .checkmark { font-size: 24px; color: #25b003; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="checkmark">✅</span> Learners Successfully Loaded</h1>
            <p>Class Setup Progress Update</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <div class="success-box">
                <h3>✅ Task Completed: Load Learners</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Learners Loaded:</strong> {learner_count} participants</p>
                <p><strong>Status:</strong> Ready for next phase</p>
            </div>

            <p>The learner roster has been successfully loaded into the system. The class can now proceed to the next phase of preparation.</p>

            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Submit agent order</li>
                <li>Set training schedule</li>
                <li>Arrange material delivery</li>
                <li>Complete agent paperwork</li>
            </ul>

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