#!/bin/bash

# Material Delivery Notification System - Quick Test Script
# Run this after applying SQL migration

set -e

echo ""
echo "=========================================="
echo "Material Notification System - Quick Test"
echo "=========================================="
echo ""

# Get database credentials from WordPress config or environment
DB_USER="${PGUSER:-doadmin}"
DB_NAME="${PGDATABASE:-defaultdb}"
DB_HOST="${PGHOST:-localhost}"

echo "📋 Test Configuration"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo "   Host: $DB_HOST"
echo ""

# Test 1: Email Configuration
echo "🔍 Test 1: Email Configuration"
EMAIL=$(wp option get wecoza_notification_material_delivery 2>/dev/null || echo "")
if [ -z "$EMAIL" ]; then
    echo "   ❌ FAIL: Email not configured"
    echo "   Fix: wp option update wecoza_notification_material_delivery 'your-email@example.com'"
    exit 1
else
    echo "   ✅ PASS: Email configured as: $EMAIL"
fi
echo ""

# Test 2: Database Table Exists
echo "🔍 Test 2: Database Table Exists"
if PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -c "\d class_material_tracking" &>/dev/null; then
    echo "   ✅ PASS: Table class_material_tracking exists"
else
    echo "   ❌ FAIL: Table class_material_tracking not found"
    echo "   Fix: Run docs/material_delivery_notification_migration.sql"
    exit 1
fi
echo ""

# Test 3: Check for Classes 7 Days Away
echo "🔍 Test 3: Classes Needing Orange Notification (7 days)"
ORANGE_COUNT=$(PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -t -c "SELECT COUNT(*) FROM classes WHERE original_start_date = CURRENT_DATE + INTERVAL '7 days';" 2>/dev/null | xargs)
echo "   Found: $ORANGE_COUNT classes"
if [ "$ORANGE_COUNT" -gt 0 ]; then
    echo "   📊 Class details:"
    PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -c "SELECT class_id, class_code, class_subject, original_start_date FROM classes WHERE original_start_date = CURRENT_DATE + INTERVAL '7 days' LIMIT 3;"
fi
echo ""

# Test 4: Check for Classes 5 Days Away
echo "🔍 Test 4: Classes Needing Red Notification (5 days)"
RED_COUNT=$(PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -t -c "SELECT COUNT(*) FROM classes WHERE original_start_date = CURRENT_DATE + INTERVAL '5 days';" 2>/dev/null | xargs)
echo "   Found: $RED_COUNT classes"
if [ "$RED_COUNT" -gt 0 ]; then
    echo "   📊 Class details:"
    PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -c "SELECT class_id, class_code, class_subject, original_start_date FROM classes WHERE original_start_date = CURRENT_DATE + INTERVAL '5 days' LIMIT 3;"
fi
echo ""

# Test 5: Cron Job Scheduled
echo "🔍 Test 5: Cron Job Scheduled"
if wp cron event list 2>/dev/null | grep -q "wecoza_material_notification_check"; then
    echo "   ✅ PASS: Cron job is scheduled"
    wp cron event list | grep wecoza_material_notification_check
else
    echo "   ❌ FAIL: Cron job not scheduled"
    echo "   Fix: Deactivate and reactivate plugin"
fi
echo ""

# Test 6: Run Cron Manually
echo "🔍 Test 6: Running Cron Job Manually"
echo "   Executing: wp cron event run wecoza_material_notification_check"
if wp cron event run wecoza_material_notification_check 2>&1; then
    echo "   ✅ PASS: Cron executed successfully"
else
    echo "   ❌ FAIL: Cron execution failed"
fi
echo ""

# Test 7: Check Logs
echo "🔍 Test 7: Check Recent Logs"
if [ -f "wp-content/debug.log" ]; then
    echo "   Recent material notification logs:"
    tail -20 wp-content/debug.log | grep -i "material" || echo "   No material notification logs found"
else
    echo "   ⚠️  WARNING: debug.log not found"
    echo "   Enable debugging in wp-config.php to see logs"
fi
echo ""

# Test 8: Check Tracking Records
echo "🔍 Test 8: Check Tracking Records in Database"
TRACKING_COUNT=$(PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -t -c "SELECT COUNT(*) FROM class_material_tracking;" 2>/dev/null | xargs)
echo "   Total tracking records: $TRACKING_COUNT"
if [ "$TRACKING_COUNT" -gt 0 ]; then
    echo "   📊 Recent records:"
    PGPASSWORD=$PGPASSWORD psql -U $DB_USER -h $DB_HOST -d $DB_NAME -c "SELECT cmt.id, c.class_code, cmt.notification_type, cmt.delivery_status, cmt.notification_sent_at FROM class_material_tracking cmt JOIN classes c ON cmt.class_id = c.class_id ORDER BY cmt.notification_sent_at DESC LIMIT 5;"
fi
echo ""

# Summary
echo "=========================================="
echo "📊 Test Summary"
echo "=========================================="
echo "✅ Email configured: $EMAIL"
echo "✅ Database table exists"
echo "📧 Classes needing Orange notification: $ORANGE_COUNT"
echo "📧 Classes needing Red notification: $RED_COUNT"
echo "📝 Total tracking records: $TRACKING_COUNT"
echo ""

if [ "$ORANGE_COUNT" -gt 0 ] || [ "$RED_COUNT" -gt 0 ]; then
    echo "🎉 SUCCESS: Notifications should have been sent!"
    echo "   Check your email inbox: $EMAIL"
else
    echo "ℹ️  INFO: No classes found 7 or 5 days away"
    echo "   This is normal if no classes match the criteria"
    echo ""
    echo "💡 To test with sample data:"
    echo "   1. Create a test class with start date 7 days from today"
    echo "   2. Or temporarily modify an existing class's start date"
    echo "   3. Run: wp cron event run wecoza_material_notification_check"
fi

echo ""
echo "=========================================="
echo "✅ Testing Complete!"
echo "=========================================="
echo ""
