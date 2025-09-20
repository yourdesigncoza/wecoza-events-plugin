<?php
/**
 * Email template: Enrollment Confirmed - Learner Notification
 * Event: EVT-01a (class.approved)
 * Recipient: Learners
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
    <title>Training Class Enrollment Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #3874ff; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .enrollment-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .schedule-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .important-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 20px 0; border-radius: 5px; color: #856404; }
        .btn { background: #3874ff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .enrollment-icon { font-size: 24px; color: #3874ff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="enrollment-icon">📚</span> Training Enrollment Confirmed</h1>
            <p>Your training class is ready!</p>
        </div>

        <div class="content">
            <p>Dear Learner,</p>

            <p>Your enrollment in the following training class has been confirmed:</p>

            <div class="enrollment-box">
                <h3>📚 Class Information</h3>
                <ul>
                    <li><strong>Course:</strong> {class_name}</li>
                    <li><strong>Dates:</strong> {start_date} to {end_date}</li>
                    <li><strong>Location:</strong> {site_name}</li>
                </ul>
            </div>

            <div class="schedule-box">
                <h3>📅 Training Schedule</h3>
                <div class="schedule-details">
                    {schedule_details}
                </div>
            </div>

            <div class="important-box">
                <h3>⚠️ Important Information</h3>
                <ul>
                    <li>Please arrive <strong>15 minutes before</strong> the scheduled start time</li>
                    <li>Bring valid <strong>identification</strong></li>
                    <li>Course materials will be provided</li>
                    <li>Dress code: Business casual or as per company policy</li>
                </ul>
            </div>

            <div class="contact-info">
                <h4>📞 Contact Information</h4>
                <div>{contact_info}</div>
                <p>If you have any questions about this training, please use the contact details above.</p>
            </div>

            <p style="text-align: center;">
                <a href="{class_url}" class="btn">View Full Class Details</a>
            </p>

            <p><strong>We look forward to seeing you at the training!</strong></p>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            <strong>WECOZA Training Team</strong></p>
            <p><small>Please save this email for your records.</small></p>
        </div>
    </div>
</body>
</html>