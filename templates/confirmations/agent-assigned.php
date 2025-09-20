<?php
/**
 * Email template: Agent Assignment Notification
 * Event: EVT-01a (class.approved)
 * Recipient: Training Agents
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
    <title>Training Assignment Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #e5780b; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .assignment-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 20px 0; border-radius: 5px; color: #856404; }
        .class-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .schedule-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .btn { background: #e5780b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .assignment-icon { font-size: 24px; color: #e5780b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="assignment-icon">👨‍🏫</span> Training Assignment Confirmed</h1>
            <p>You have a new training to deliver</p>
        </div>

        <div class="content">
            <p>Dear Training Agent,</p>

            <p>You have been assigned to deliver a new training class. All preparations have been completed and the class is ready to proceed.</p>

            <div class="assignment-box">
                <h3>🎯 Assignment Details</h3>
                <ul>
                    <li><strong>Course:</strong> {class_name}</li>
                    <li><strong>Location:</strong> {site_name}</li>
                    <li><strong>Learners:</strong> {learner_count} participants</li>
                    <li><strong>Status:</strong> Ready to deliver</li>
                </ul>
            </div>

            <div class="schedule-box">
                <h3>📅 Training Schedule</h3>
                <div class="schedule-details">
                    {schedule_details}
                </div>
            </div>

            <div class="class-box">
                <h3>📋 Preparation Status</h3>
                <ul>
                    <li>✅ Learners enrolled and confirmed</li>
                    <li>✅ Training materials delivered to site</li>
                    <li>✅ Venue prepared and ready</li>
                    <li>✅ All documentation complete</li>
                </ul>
            </div>

            <div class="contact-info">
                <h4>📞 Contact Information</h4>
                <div>{contact_info}</div>
                <p>For any questions or issues, please contact the course coordinator using the details above.</p>
            </div>

            <p style="text-align: center;">
                <a href="{class_url}" class="btn">View Class Details</a>
            </p>

            <p><strong>Thank you for your service in delivering quality training!</strong></p>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            <strong>WECOZA Training Coordination Team</strong></p>
        </div>
    </div>
</body>
</html>