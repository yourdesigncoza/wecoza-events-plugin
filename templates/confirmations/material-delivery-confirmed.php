<?php
/**
 * Email template: Material Delivery Confirmed
 * Event: EVT-05 (class.material.delivery.confirmed)
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
    <title>Material Delivery Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #25b003; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #155724; }
        .delivery-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .btn { background: #25b003; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .checkmark { font-size: 24px; color: #25b003; }
        .delivery-date { font-size: 16px; font-weight: bold; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="checkmark">✅</span> Material Delivery Confirmed</h1>
            <p>Training Materials Ready</p>
        </div>

        <div class="content">
            <p>Dear Supervisor,</p>

            <div class="success-box">
                <h3>✅ Task Completed: Material Delivery</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Status:</strong> All materials delivered and ready</p>
            </div>

            <div class="delivery-box">
                <h3>📦 Delivery Confirmation</h3>
                <p class="delivery-date">Delivered: {delivery_date}</p>
                <div class="delivery-details">
                    {delivery_details}
                </div>
            </div>

            <p>All training materials have been successfully delivered and are ready for the upcoming class. The training location now has everything needed for the session.</p>

            <p><strong>Remaining Steps:</strong></p>
            <ul>
                <li>Complete agent paperwork</li>
            </ul>

            <p>You will be notified once the final task is completed and the class is ready for your approval.</p>

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