#!/usr/bin/env php
<?php
/**
 * Manual test script for Material Delivery Notifications
 * Run: php test-material-notification.php
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../../wp-load.php');

echo "\n";
echo "==========================================\n";
echo "Material Notification System - Manual Test\n";
echo "==========================================\n\n";

// Test 1: Check email configuration
echo "🔍 Test 1: Email Configuration\n";
$email = get_option('wecoza_notification_material_delivery', '');
if (empty($email)) {
    echo "   ❌ FAIL: Email not configured in WordPress options\n";
    echo "   Configure via: WordPress Admin → WeCoza Event Notifications\n";
    exit(1);
}
echo "   ✅ PASS: Email configured as: {$email}\n\n";

// Test 2: Check database connection
echo "🔍 Test 2: PostgreSQL Database Connection\n";
try {
    $pdo = \WeCozaEvents\Database\Connection::getPdo();
    $schema = \WeCozaEvents\Database\Connection::getSchema();
    echo "   ✅ PASS: Database connection successful\n";
    echo "   Schema: {$schema}\n\n";
} catch (Exception $e) {
    echo "   ❌ FAIL: Database connection error\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check if table exists
echo "🔍 Test 3: Check class_material_tracking Table\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM \"{$schema}\".class_material_tracking");
    $count = $stmt->fetchColumn();
    echo "   ✅ PASS: Table exists\n";
    echo "   Current tracking records: {$count}\n\n";
} catch (Exception $e) {
    echo "   ❌ FAIL: Table class_material_tracking not found\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Fix: Run docs/material_delivery_notification_migration.sql\n";
    exit(1);
}

// Test 4: Check for classes 7 days away (Orange)
echo "🔍 Test 4: Find Classes Needing Orange Notification (7 days)\n";
try {
    $sql = sprintf(
        "SELECT 
            class_id,
            class_code,
            class_subject,
            original_start_date,
            (original_start_date - CURRENT_DATE) as days_until_start
         FROM \"%s\".classes
         WHERE original_start_date = CURRENT_DATE + INTERVAL '7 days'
         LIMIT 5",
        $schema
    );
    $stmt = $pdo->query($sql);
    $orangeClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Found: " . count($orangeClasses) . " classes\n";
    if (!empty($orangeClasses)) {
        foreach ($orangeClasses as $class) {
            echo "   - {$class['class_code']} ({$class['class_subject']}) - Start: {$class['original_start_date']}\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error querying classes: " . $e->getMessage() . "\n\n";
}

// Test 5: Check for classes 5 days away (Red)
echo "🔍 Test 5: Find Classes Needing Red Notification (5 days)\n";
try {
    $sql = sprintf(
        "SELECT 
            class_id,
            class_code,
            class_subject,
            original_start_date,
            (original_start_date - CURRENT_DATE) as days_until_start
         FROM \"%s\".classes
         WHERE original_start_date = CURRENT_DATE + INTERVAL '5 days'
         LIMIT 5",
        $schema
    );
    $stmt = $pdo->query($sql);
    $redClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Found: " . count($redClasses) . " classes\n";
    if (!empty($redClasses)) {
        foreach ($redClasses as $class) {
            echo "   - {$class['class_code']} ({$class['class_subject']}) - Start: {$class['original_start_date']}\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error querying classes: " . $e->getMessage() . "\n\n";
}

// Test 6: Initialize services and run notification check
echo "🔍 Test 6: Run Material Notification Service\n";
try {
    $trackingRepo = new \WeCozaEvents\Models\MaterialTrackingRepository($pdo, $schema);
    $service = new \WeCozaEvents\Services\MaterialNotificationService($pdo, $schema, $trackingRepo);
    
    // Find Orange classes
    $orangeClasses = $service->findOrangeStatusClasses();
    echo "   Orange notification candidates: " . count($orangeClasses) . "\n";
    
    if (!empty($orangeClasses)) {
        echo "   Sending Orange notifications...\n";
        $sentOrange = $service->sendMaterialNotifications($orangeClasses, 'orange');
        echo "   ✅ Sent {$sentOrange} Orange notifications\n";
    }
    
    // Find Red classes
    $redClasses = $service->findRedStatusClasses();
    echo "   Red notification candidates: " . count($redClasses) . "\n";
    
    if (!empty($redClasses)) {
        echo "   Sending Red notifications...\n";
        $sentRed = $service->sendMaterialNotifications($redClasses, 'red');
        echo "   ✅ Sent {$sentRed} Red notifications\n";
    }
    
    if (empty($orangeClasses) && empty($redClasses)) {
        echo "   ℹ️  No classes found 7 or 5 days away from start\n";
        echo "   This is normal if no classes match the criteria\n";
    }
    
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error running notification service: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Test 7: Check tracking records
echo "🔍 Test 7: View Recent Tracking Records\n";
try {
    $sql = sprintf(
        "SELECT 
            cmt.id,
            cmt.class_id,
            c.class_code,
            cmt.notification_type,
            cmt.delivery_status,
            cmt.notification_sent_at
         FROM \"%s\".class_material_tracking cmt
         LEFT JOIN \"%s\".classes c ON cmt.class_id = c.class_id
         ORDER BY cmt.notification_sent_at DESC
         LIMIT 10",
        $schema,
        $schema
    );
    $stmt = $pdo->query($sql);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($records)) {
        echo "   Recent notifications sent:\n";
        foreach ($records as $record) {
            $sentAt = $record['notification_sent_at'] ?? 'Not sent yet';
            echo sprintf(
                "   - Class: %s | Type: %s | Status: %s | Sent: %s\n",
                $record['class_code'] ?? "ID {$record['class_id']}",
                $record['notification_type'],
                $record['delivery_status'],
                $sentAt
            );
        }
    } else {
        echo "   No tracking records found yet\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error querying tracking records: " . $e->getMessage() . "\n\n";
}

// Summary
echo "==========================================\n";
echo "📊 Test Summary\n";
echo "==========================================\n";
echo "✅ Email configured: {$email}\n";
echo "✅ Database connection: Working\n";
echo "✅ Table exists: class_material_tracking\n";

$totalOrange = count($orangeClasses ?? []);
$totalRed = count($redClasses ?? []);
$sentOrange = $sentOrange ?? 0;
$sentRed = $sentRed ?? 0;

echo "📧 Orange notifications sent: {$sentOrange} / {$totalOrange}\n";
echo "📧 Red notifications sent: {$sentRed} / {$totalRed}\n";

if ($sentOrange > 0 || $sentRed > 0) {
    echo "\n🎉 SUCCESS! Check your email inbox: {$email}\n";
} else {
    echo "\nℹ️  INFO: No notifications sent\n";
    echo "   Reason: No classes found 7 or 5 days away from start\n";
    echo "\n💡 To test with sample data:\n";
    echo "   1. Create a test class with start date 7 days from today\n";
    echo "   2. Or run SQL:\n";
    echo "      UPDATE classes SET original_start_date = CURRENT_DATE + INTERVAL '7 days' WHERE class_id = 123;\n";
    echo "   3. Run this test again: php test-material-notification.php\n";
    echo "   4. Don't forget to restore: UPDATE classes SET original_start_date = 'original-date' WHERE class_id = 123;\n";
}

echo "\n==========================================\n";
echo "✅ Testing Complete!\n";
echo "==========================================\n\n";
