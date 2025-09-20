<?php
/**
 * Email template: Agent Paperwork Submitted
 * Event: EVT-06 (class.agent.paperwork.submitted)
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
    <title>Agent Paperwork Submitted</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #25b003; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; margin: 20px 0; border-radius: 5px; color: #155724; }
        .ready-box { background: #cff4fc; border: 1px solid #9eeaf9; padding: 20px; margin: 20px 0; border-radius: 5px; color: #055160; }
        .btn { background: #25b003; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .btn-approve { background: #0097eb; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .checkmark { font-size: 24px; color: #25b003; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="checkmark">✅</span> Agent Paperwork Submitted</h1>
            <p>All Tasks Complete</p>
        </div>

        <div class="content">
            <p>Dear Supervisor,</p>

            <div class="success-box">
                <h3>✅ Final Task Completed: Agent Paperwork</h3>
                <p><strong>Class:</strong> {class_name}</p>
                <p><strong>Submitted By:</strong> {submitted_by}</p>
                <p><strong>Status:</strong> All documentation complete</p>
            </div>

            <div class="ready-box">
                <h3>🎉 Class Ready for Approval!</h3>
                <p><strong>All preparation tasks have been completed:</strong></p>
                <ul>
                    <li>✅ Learners loaded</li>
                    <li>✅ Agent order submitted</li>
                    <li>✅ Training schedule set</li>
                    <li>✅ Materials delivered</li>
                    <li>✅ Agent paperwork complete</li>
                </ul>
                <p><strong>The class is now ready for your approval to proceed.</strong></p>
            </div>

            <div class="paperwork-details">
                <h4>📄 Paperwork Details</h4>
                <div>{paperwork_details}</div>
            </div>

            <p style="text-align: center;">
                <a href="{class_url}" class="btn-approve">Review & Approve Class</a>
            </p>

            <p style="text-align: center;">
                <a href="{dashboard_url}" class="btn">Go to Dashboard</a>
            </p>

            <p><small><strong>Next Step:</strong> Once you approve this class, enrollment notifications will be sent to learners and assignment notifications to agents.</small></p>
        </div>

        <div class="footer">
            <p>Best regards,<br>
            <strong>WECOZA Training Management System</strong></p>
        </div>
    </div>
</body>
</html>