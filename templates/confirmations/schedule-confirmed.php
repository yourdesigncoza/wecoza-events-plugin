<?php
/**
 * Email template: Training Schedule Confirmed
 * Event: EVT-04 (class.schedule.set)
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
    <title>Training Schedule Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #25b003; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #155724; }
        .schedule-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .btn { background: #25b003; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .checkmark { font-size: 24px; color: #25b003; }
        .date-range { font-size: 18px; font-weight: bold; color: #0097eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="checkmark">✅</span> Training Schedule Confirmed</h1>
            <p>Class Timing Set</p>
        </div>

        <div class="content">
            <p>Dear Supervisor,</p>

            <div class="success-box">
                <h3>✅ Task Completed: Training Schedule</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Status:</strong> Schedule confirmed and ready</p>
            </div>

            <div class="schedule-box">
                <h3>📅 Confirmed Schedule</h3>
                <p class="date-range">{start_date} to {end_date}</p>
                <div class="schedule-details">
                    {schedule_details}
                </div>
            </div>

            <p>The training schedule has been successfully set and confirmed. All participants will be notified of the schedule once the class receives final approval.</p>

            <p><strong>Remaining Steps:</strong></p>
            <ul>
                <li>Arrange material delivery</li>
                <li>Complete agent paperwork</li>
            </ul>

            <p>You will be notified once all remaining tasks are completed and the class is ready for your final approval.</p>

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