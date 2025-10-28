# Testing Guide: Material Delivery Notifications

## Prerequisites ✅

Before testing, ensure:
- [ ] SQL migration has been applied
- [ ] Email address is configured in WordPress settings
- [ ] Plugin is activated

---

## Method 1: Quick Test (No Real Data Needed)

### Step 1: Apply SQL Migration

```bash
# Copy the SQL from docs/material_delivery_notification_migration.sql
# Paste into your PostgreSQL editor and execute

# Or use psql:
psql -U your_user -d your_database -f docs/material_delivery_notification_migration.sql
```

**Verify table exists:**
```sql
\d class_material_tracking
```

### Step 2: Verify Email Configuration

```bash
# Check email is set
wp option get wecoza_notification_material_delivery
```

**Should output:** `your-email@example.com`

If empty, set it:
```bash
wp option update wecoza_notification_material_delivery "your-email@example.com"
```

### Step 3: Run Cron Manually

```bash
# This will check for classes and send notifications
wp cron event run wecoza_material_notification_check
```

**Expected output if no classes match:**
```
Success: Executed the cron event 'wecoza_material_notification_check'
```

### Step 4: Check Logs

```bash
# View recent material notification logs
tail -100 /opt/lampp/htdocs/wecoza/wp-content/debug.log | grep -i "material"
```

**Expected log entries:**
```
WeCoza Material Notifications: Sent 0 Orange (7-day) notifications
WeCoza Material Notifications: Sent 0 Red (5-day) notifications
```

*(Will be 0 if no classes are exactly 7 or 5 days away)*

---

## Method 2: Test With Real Class Data

### Option A: Use Existing Classes

**Check if you have classes at the right dates:**

```sql
-- Classes 7 days away (Orange notification)
SELECT 
    class_id,
    class_code,
    class_subject,
    original_start_date,
    (original_start_date - CURRENT_DATE) as days_until_start
FROM classes
WHERE original_start_date = CURRENT_DATE + INTERVAL '7 days';

-- Classes 5 days away (Red notification)
SELECT 
    class_id,
    class_code,
    class_subject,
    original_start_date,
    (original_start_date - CURRENT_DATE) as days_until_start
FROM classes
WHERE original_start_date = CURRENT_DATE + INTERVAL '5 days';
```

If you get results, run the cron:
```bash
wp cron event run wecoza_material_notification_check
```

You should receive emails! ✉️

### Option B: Create Test Classes

**Create a test class 7 days in the future:**

```sql
-- BACKUP YOUR DATA FIRST!

INSERT INTO classes (
    client_id,
    class_code,
    class_subject,
    original_start_date,
    created_at,
    updated_at
) VALUES (
    1,  -- Replace with valid client_id
    'TEST-ORANGE-7DAY',
    'Test Material Notification - Orange',
    CURRENT_DATE + INTERVAL '7 days',
    NOW(),
    NOW()
);

-- Get the class_id of the newly created class
SELECT class_id, class_code, original_start_date 
FROM classes 
WHERE class_code = 'TEST-ORANGE-7DAY';
```

**Create a test class 5 days in the future:**

```sql
INSERT INTO classes (
    client_id,
    class_code,
    class_subject,
    original_start_date,
    created_at,
    updated_at
) VALUES (
    1,  -- Replace with valid client_id
    'TEST-RED-5DAY',
    'Test Material Notification - Red',
    CURRENT_DATE + INTERVAL '5 days',
    NOW(),
    NOW()
);
```

**Run the cron:**
```bash
wp cron event run wecoza_material_notification_check
```

**Check your email inbox!** You should receive 2 emails:
- 1 Orange notification (7 days)
- 1 Red notification (5 days)

**Clean up test data:**
```sql
DELETE FROM classes WHERE class_code LIKE 'TEST-%DAY';
```

### Option C: Temporarily Modify Existing Class

**Safest method - modify then restore:**

```sql
-- Step 1: Backup the original date
SELECT class_id, class_code, original_start_date 
FROM classes 
WHERE class_id = 123;  -- Replace with actual class_id

-- Step 2: Change to 7 days from today
UPDATE classes 
SET original_start_date = CURRENT_DATE + INTERVAL '7 days'
WHERE class_id = 123;

-- Step 3: Run cron and check email
-- wp cron event run wecoza_material_notification_check

-- Step 4: Restore original date
UPDATE classes 
SET original_start_date = '2025-11-05'  -- Use the original date from Step 1
WHERE class_id = 123;

-- Step 5: Clean up test notification record
DELETE FROM class_material_tracking WHERE class_id = 123;
```

---

## Method 3: Test Duplicate Prevention

This verifies that notifications are sent only once per type.

**After sending an Orange notification:**

```bash
# First run - should send notification
wp cron event run wecoza_material_notification_check
```

Check logs:
```
WeCoza Material Notifications: Sent 1 Orange (7-day) notifications
```

**Run again immediately:**

```bash
# Second run - should NOT send again
wp cron event run wecoza_material_notification_check
```

Check logs:
```
WeCoza Material Notifications: Sent 0 Orange (7-day) notifications
```

**Verify in database:**
```sql
SELECT 
    class_id,
    notification_type,
    delivery_status,
    notification_sent_at
FROM class_material_tracking
WHERE notification_type = 'orange'
ORDER BY notification_sent_at DESC;
```

Should show the record with `delivery_status = 'notified'` and `notification_sent_at` populated.

---

## Method 4: Test Email Content

**View what the email looks like:**

After running the cron and receiving an email, verify it contains:

✅ **Header:** Orange or Red color-coded banner  
✅ **Class Details Table:**
- Class Code
- Subject
- Client Name
- Site Name
- Class Start Date
- Expected Delivery Date (if set)
- Days Until Start (7 or 5)

✅ **Footer:** Notification type and timestamp

---

## Verification Commands

### Check Cron Schedule
```bash
# Verify cron job is scheduled
wp cron event list | grep material
```

**Expected output:**
```
wecoza_material_notification_check  2025-10-29 00:00:00  wecoza_events_daily
```

### Check Database Records
```sql
-- View all tracking records
SELECT 
    cmt.id,
    c.class_code,
    cmt.notification_type,
    cmt.delivery_status,
    cmt.notification_sent_at,
    c.original_start_date
FROM class_material_tracking cmt
JOIN classes c ON cmt.class_id = c.class_id
ORDER BY cmt.notification_sent_at DESC;
```

### Check WordPress Option
```bash
# Verify email address
wp option get wecoza_notification_material_delivery
```

### Check Plugin Files
```bash
# Verify files exist
ls -lh includes/Models/MaterialTrackingRepository.php
ls -lh includes/Services/MaterialNotificationService.php
```

### Check PHP Syntax
```bash
php -l includes/Models/MaterialTrackingRepository.php
php -l includes/Services/MaterialNotificationService.php
```

---

## Troubleshooting Tests

### Test 1: Is WordPress Mail Working?

```bash
# Test WordPress mail function
wp shell
```

Then in the shell:
```php
wp_mail('your-email@example.com', 'Test Email', 'This is a test.');
exit
```

Check if you received the email.

### Test 2: Is Database Connection Working?

```php
<?php
// Create test file: test-db-connection.php
require_once('wp-load.php');

try {
    $pdo = \WeCozaEvents\Database\Connection::getPdo();
    echo "✅ Database connection successful\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM class_material_tracking");
    $count = $stmt->fetchColumn();
    echo "✅ Found {$count} tracking records\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
```

Run:
```bash
php test-db-connection.php
```

### Test 3: Check Cron Function Directly

```bash
wp shell
```

Then:
```php
wecoza_events_run_material_notification_check();
exit
```

Check debug.log for output.

### Test 4: Verify Service Finds Classes

```bash
wp shell
```

```php
$pdo = \WeCozaEvents\Database\Connection::getPdo();
$schema = \WeCozaEvents\Database\Connection::getSchema();
$trackingRepo = new \WeCozaEvents\Models\MaterialTrackingRepository($pdo, $schema);
$service = new \WeCozaEvents\Services\MaterialNotificationService($pdo, $schema, $trackingRepo);

$orangeClasses = $service->findOrangeStatusClasses();
echo "Found " . count($orangeClasses) . " Orange classes\n";
print_r($orangeClasses);

$redClasses = $service->findRedStatusClasses();
echo "Found " . count($redClasses) . " Red classes\n";
print_r($redClasses);

exit
```

---

## Expected Test Results

### ✅ Success Indicators

1. **Cron runs without errors**
   ```bash
   wp cron event run wecoza_material_notification_check
   # Exit code: 0 (success)
   ```

2. **Logs show notifications sent**
   ```
   WeCoza Material Notifications: Sent X Orange (7-day) notifications
   WeCoza Material Notifications: Sent Y Red (5-day) notifications
   ```

3. **Emails received in inbox**
   - Proper HTML formatting
   - Correct class details
   - Color-coded headers

4. **Database records created**
   ```sql
   SELECT * FROM class_material_tracking;
   -- Shows records with notification_sent_at populated
   ```

5. **Duplicates prevented**
   - Second cron run sends 0 notifications for same classes

### ❌ Failure Indicators

1. **No email configured:**
   ```
   WeCoza Material Notification: No valid recipient email configured
   ```
   **Fix:** Set email in WordPress settings

2. **Database error:**
   ```
   WeCoza material notification check failed: relation "class_material_tracking" does not exist
   ```
   **Fix:** Run SQL migration

3. **No classes found:**
   ```
   WeCoza Material Notifications: Sent 0 Orange (7-day) notifications
   WeCoza Material Notifications: Sent 0 Red (5-day) notifications
   ```
   **This is OK if no classes match the date criteria**

4. **Mail function fails:**
   ```
   WeCoza Material Notification: Failed to send orange notification for class ABC123
   ```
   **Fix:** Check WordPress mail configuration

---

## Full Testing Workflow (Step-by-Step)

```bash
# 1. Apply SQL migration
psql -U user -d database -f docs/material_delivery_notification_migration.sql

# 2. Verify table
psql -U user -d database -c "\d class_material_tracking"

# 3. Set email address
wp option update wecoza_notification_material_delivery "your-email@example.com"

# 4. Verify email set
wp option get wecoza_notification_material_delivery

# 5. Check for matching classes
psql -U user -d database -c "SELECT class_id, class_code, original_start_date FROM classes WHERE original_start_date IN (CURRENT_DATE + INTERVAL '7 days', CURRENT_DATE + INTERVAL '5 days');"

# 6. Run cron manually
wp cron event run wecoza_material_notification_check

# 7. Check logs
tail -20 wp-content/debug.log | grep Material

# 8. Check email inbox
# Should have received email(s)

# 9. Verify database records
psql -U user -d database -c "SELECT * FROM class_material_tracking ORDER BY notification_sent_at DESC;"

# 10. Test duplicate prevention
wp cron event run wecoza_material_notification_check
tail -5 wp-content/debug.log | grep Material
# Should show 0 notifications sent (already sent)
```

---

## Automated Test Script

Create this file: `test-material-notifications.sh`

```bash
#!/bin/bash

echo "=========================================="
echo "Material Notification System Test"
echo "=========================================="
echo ""

echo "1. Checking email configuration..."
EMAIL=$(wp option get wecoza_notification_material_delivery)
if [ -z "$EMAIL" ]; then
    echo "❌ Email not configured"
    exit 1
else
    echo "✅ Email configured: $EMAIL"
fi

echo ""
echo "2. Checking table exists..."
if psql -U your_user -d your_database -c "\d class_material_tracking" &>/dev/null; then
    echo "✅ Table exists"
else
    echo "❌ Table not found - run migration first"
    exit 1
fi

echo ""
echo "3. Checking for classes 7 days away..."
COUNT=$(psql -U your_user -d your_database -t -c "SELECT COUNT(*) FROM classes WHERE original_start_date = CURRENT_DATE + INTERVAL '7 days';")
echo "Found $COUNT classes for Orange notification"

echo ""
echo "4. Checking for classes 5 days away..."
COUNT=$(psql -U your_user -d your_database -t -c "SELECT COUNT(*) FROM classes WHERE original_start_date = CURRENT_DATE + INTERVAL '5 days';")
echo "Found $COUNT classes for Red notification"

echo ""
echo "5. Running cron job..."
wp cron event run wecoza_material_notification_check

echo ""
echo "6. Checking logs..."
tail -5 wp-content/debug.log | grep Material

echo ""
echo "7. Checking tracking records..."
psql -U your_user -d your_database -c "SELECT COUNT(*) as total_records FROM class_material_tracking;"

echo ""
echo "=========================================="
echo "Test Complete!"
echo "Check your email inbox for notifications"
echo "=========================================="
```

Run:
```bash
chmod +x test-material-notifications.sh
./test-material-notifications.sh
```

---

## Production Monitoring

Once deployed, monitor with:

```bash
# Check cron runs
wp cron event list | grep material

# Check recent notifications (last 7 days)
psql -c "SELECT COUNT(*), notification_type FROM class_material_tracking WHERE notification_sent_at > NOW() - INTERVAL '7 days' GROUP BY notification_type;"

# Check pending deliveries
psql -c "SELECT COUNT(*) FROM class_material_tracking WHERE delivery_status = 'notified';"

# View failed attempts (check logs)
tail -100 wp-content/debug.log | grep "Failed to send"
```

---

**Need Help?** Refer to:
- Full documentation: `/docs/MATERIAL_DELIVERY_NOTIFICATIONS.md`
- Quick start: `/docs/QUICK_START_MATERIAL_NOTIFICATIONS.md`
