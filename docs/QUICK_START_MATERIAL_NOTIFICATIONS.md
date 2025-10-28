# 🚀 Quick Start: Material Delivery Notifications

**5-Minute Setup Guide**

---

## Step 1: Run SQL Migration ⚡

**Copy and paste this into your PostgreSQL SQL editor:**

📄 File location: `/docs/material_delivery_notification_migration.sql`

```bash
# Or use psql command line:
psql -U your_user -d your_database -f docs/material_delivery_notification_migration.sql
```

**Verify it worked:**
```sql
\d class_material_tracking
```

You should see the table structure with columns: `id`, `class_id`, `notification_type`, etc.

---

## Step 2: Verify Email Is Configured ✉️

The WordPress option `wecoza_notification_material_delivery` should already be set.

**Check via admin panel:**
1. Go to WordPress Admin
2. Navigate to **WeCoza Event Notifications**
3. Look for **Material Delivery notifications email** field
4. Ensure it has a valid email address
5. Click **Save Changes** if you made any changes

**Or check via command line:**
```bash
wp option get wecoza_notification_material_delivery
```

---

## Step 3: Activate Plugin (if needed) 🔌

The cron job will auto-register when the plugin loads. If you want to force it:

```bash
wp plugin deactivate wecoza-events-plugin
wp plugin activate wecoza-events-plugin
```

---

## Step 4: Test It! 🧪

**Run the cron job manually:**
```bash
wp cron event run wecoza_material_notification_check
```

**Check the logs:**
```bash
tail -f wp-content/debug.log | grep "Material"
```

**Expected output:**
```
WeCoza Material Notifications: Sent 0 Orange (7-day) notifications
WeCoza Material Notifications: Sent 0 Red (5-day) notifications
```

(Will be 0 if no classes are exactly 7 or 5 days away from starting)

---

## ✅ You're Done!

The system will now automatically:
- Check daily at midnight for classes needing material delivery
- Send Orange notification 7 days before class start
- Send Red notification 5 days before class start
- Prevent duplicate notifications

---

## 🧪 Testing With Real Data

To test with actual classes, you need classes where:
- `original_start_date` = TODAY + 7 days (for Orange)
- `original_start_date` = TODAY + 5 days (for Red)

**Create test data (optional):**
```sql
-- Backup first!
-- Then temporarily modify a class's start date for testing
UPDATE classes 
SET original_start_date = CURRENT_DATE + INTERVAL '7 days'
WHERE class_id = 123;  -- Replace with actual class_id

-- Run cron
-- wp cron event run wecoza_material_notification_check

-- Reset after testing
UPDATE classes 
SET original_start_date = '2025-11-05'  -- Original date
WHERE class_id = 123;
```

---

## 📊 Monitor System

**View all tracking records:**
```sql
SELECT 
    c.class_code,
    cmt.notification_type,
    cmt.delivery_status,
    cmt.notification_sent_at
FROM class_material_tracking cmt
JOIN classes c ON cmt.class_id = c.class_id
ORDER BY cmt.notification_sent_at DESC;
```

**Check scheduled cron jobs:**
```bash
wp cron event list | grep material
```

---

## 🆘 Troubleshooting

### No emails received?
1. Check email configuration: `wp option get wecoza_notification_material_delivery`
2. Verify WordPress mail works: Test from admin or use `wp mail test test@example.com`
3. Check logs for errors: `tail -100 wp-content/debug.log | grep -i error`

### Cron not running?
1. Check if scheduled: `wp cron event list | grep material`
2. Verify WP-Cron is enabled (not disabled in wp-config.php)
3. Test manual run: `wp cron event run wecoza_material_notification_check --debug`

### Database errors?
1. Verify table exists: `\d class_material_tracking`
2. Check foreign key: Ensure classes table exists and has matching IDs
3. Review migration log for any SQL errors

---

## 📚 Full Documentation

For complete details, see:
- **User Guide:** `/docs/MATERIAL_DELIVERY_NOTIFICATIONS.md`
- **Implementation:** `/docs/IMPLEMENTATION_SUMMARY_MATERIAL_NOTIFICATIONS.md`
- **SQL Migration:** `/docs/material_delivery_notification_migration.sql`

---

## 🎯 What Happens Next?

1. **Every day at midnight:**
   - System checks for classes 7 days away → sends Orange notification
   - System checks for classes 5 days away → sends Red notification

2. **Email sent to:** Address in `wecoza_notification_material_delivery` option

3. **Tracked in database:** Records created in `class_material_tracking` table

4. **Duplicates prevented:** Unique constraint ensures one notification per type per class

---

**Status:** ✅ Implementation Complete  
**Action Required:** Run SQL migration in PostgreSQL  
**Time to Complete:** ~5 minutes
