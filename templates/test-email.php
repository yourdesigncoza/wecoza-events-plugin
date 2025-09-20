<?php
/**
 * Test Email Template
 * Used for testing email delivery and template rendering
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
    <title>WECOZA Notifications Test Email</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #ddd; }
        .header { background: #3874ff; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .test-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .variables-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
        .success { color: #25b003; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 WECOZA Notifications Test Email</h1>
            <p>Email System Verification</p>
        </div>

        <div class="content">
            <div class="test-box">
                <h3>✅ Email Delivery Test</h3>
                <p class="success">If you're reading this email, the WECOZA Notifications system is successfully delivering emails!</p>
                <p><strong>Test Date:</strong> {test_date}</p>
                <p><strong>System Status:</strong> Operational</p>
            </div>

            <div class="variables-box">
                <h3>🔧 Variable Replacement Test</h3>
                <p>The following variables should be replaced with actual values:</p>
                <ul>
                    <li><strong>Test Variable 1:</strong> {test_variable_1}</li>
                    <li><strong>Test Variable 2:</strong> {test_variable_2}</li>
                    <li><strong>Current User:</strong> {current_user}</li>
                    <li><strong>Site URL:</strong> {site_url}</li>
                </ul>
            </div>

            <p>This is a test email from the WECOZA Notifications Core plugin. If you received this email unexpectedly, please contact your system administrator.</p>

            <h4>System Information:</h4>
            <ul>
                <li><strong>Plugin:</strong> WECOZA Notifications Core</li>
                <li><strong>Template:</strong> test-email.php</li>
                <li><strong>WordPress Version:</strong> {wp_version}</li>
                <li><strong>PHP Version:</strong> {php_version}</li>
            </ul>
        </div>

        <div class="footer">
            <p><strong>WECOZA Training Management System</strong><br>
            Email Testing & Verification</p>
            <p><small>This is an automated test email.</small></p>
        </div>
    </div>
</body>
</html>