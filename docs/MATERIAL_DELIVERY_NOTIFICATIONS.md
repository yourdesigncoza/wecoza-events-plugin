# Material Delivery Notification System

## Overview

The Material Delivery Notification System automatically sends email reminders to notify stakeholders when class materials need to be delivered, based on class start dates.

## Notification Schedule

| Status | Days Before Start | Color Code | Notification Type |
|--------|------------------|------------|-------------------|
| Orange | 7 days           | #ff9800    | `orange`          |
| Red    | 5 days           | #dc3545    | `red`             |

## Features

- ✅ **Automatic Daily Checks**: Cron job runs once daily to check for classes requiring notifications
- ✅ **Duplicate Prevention**: Database constraints ensure each notification type is sent only once per class
- ✅ **Status Tracking**: Tracks progression from `pending` → `notified` → `delivered`
- ✅ **Audit Trail**: Separate timestamps for notification sent vs actual material delivery
- ✅ **Configurable Recipient**: Email address managed via WordPress admin settings

## Database Schema

### Table: `class_material_tracking`

```sql
CREATE TABLE public.class_material_tracking (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    notification_type VARCHAR(20) NOT NULL CHECK (notification_type IN ('orange', 'red')),
    notification_sent_at TIMESTAMP WITHOUT TIME ZONE,
    materials_delivered_at TIMESTAMP WITHOUT TIME ZONE,
    delivery_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);
```

**Key Constraints:**
- `UNIQUE (class_id, notification_type)` - Prevents duplicate notifications
- `CHECK (notification_type IN ('orange', 'red'))` - Enforces valid types
- `CHECK (delivery_status IN ('pending', 'notified', 'delivered'))` - Valid statuses
- Foreign key to `classes.class_id` with `ON DELETE CASCADE`

## Installation

### 1. Apply Database Migration

Run the SQL migration file in your PostgreSQL database:

```bash
psql -U your_user -d your_database -f docs/material_delivery_notification_migration.sql
```

Or copy and paste the contents of `docs/material_delivery_notification_migration.sql` into your SQL editor.

### 2. Configure Email Recipient

1. Navigate to WordPress Admin → **WeCoza Event Notifications**
2. Scroll to **Notification Recipients** section
3. Set **Material Delivery notifications email** field
4. Click **Save Changes**

The email is stored in WordPress option: `wecoza_notification_material_delivery`

### 3. Activate Cron Job

The cron job is automatically scheduled when the plugin is activated. To manually verify:

```bash
wp cron event list | grep material
```

Expected output:
```
wecoza_material_notification_check  2025-10-29 00:00:00  wecoza_events_daily
```

## Usage

### Manual Testing

Trigger the material notification check manually:

```bash
wp cron event run wecoza_material_notification_check
```

Check the logs:
```bash
tail -f wp-content/debug.log | grep "WeCoza Material"
```

### Monitoring

The system logs the following events:

**Success:**
```
WeCoza Material Notifications: Sent 3 Orange (7-day) notifications
WeCoza Material Notifications: Sent 2 Red (5-day) notifications
```

**Individual Notifications:**
```
WeCoza Material Notification: Sent orange notification for class ABC123 (ID: 456) to materials@example.com
```

**Errors:**
```
WeCoza Material Notification: No valid recipient email configured (option: wecoza_notification_material_delivery)
WeCoza Material Notification: Failed to send orange notification for class ABC123 (ID: 456)
WeCoza material notification check failed: Database connection error
```

## Architecture

### Components

1. **MaterialTrackingRepository** (`includes/Models/MaterialTrackingRepository.php`)
   - Database operations for tracking records
   - CRUD operations for material delivery status

2. **MaterialNotificationService** (`includes/Services/MaterialNotificationService.php`)
   - Business logic for finding classes needing notifications
   - Email composition and sending
   - Integration with MaterialTrackingRepository

3. **Cron Job** (`wecoza-events-plugin.php`)
   - Daily schedule: `wecoza_events_daily` (every 24 hours)
   - Hook: `wecoza_material_notification_check`
   - Handler: `wecoza_events_run_material_notification_check()`

### Workflow

```
┌─────────────────────┐
│   WordPress Cron    │
│   (Daily at 00:00)  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────────────┐
│ MaterialNotificationService         │
│ ├─ findOrangeStatusClasses()       │
│ │  (7 days before start)            │
│ └─ findRedStatusClasses()           │
│    (5 days before start)            │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Query: classes WHERE                │
│  start_date = TODAY + N days         │
│  AND NOT EXISTS notification_sent    │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  For each class:                     │
│  ├─ Build HTML email                 │
│  ├─ Send via wp_mail()               │
│  └─ Mark notification sent in DB     │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│ MaterialTrackingRepository          │
│ markNotificationSent()               │
│ (Inserts/updates tracking record)   │
└─────────────────────────────────────┘
```

## Email Template

Notifications are sent as HTML emails with:

- **Header**: Color-coded by status (Orange #ff9800, Red #dc3545)
- **Class Details Table**:
  - Class Code
  - Subject
  - Client Name
  - Site Name
  - Class Start Date
  - Expected Delivery Date
  - Days Until Start (highlighted)
- **Footer**: Timestamp and notification type

### Sample Email

```
┌─────────────────────────────────────┐
│ Material Delivery Notification      │
│ Status: Orange (7 days)             │ 🟠
└─────────────────────────────────────┘

⚠️ Action Required: Materials need to be delivered for the following class.

┌────────────────────┬────────────────┐
│ Class Code         │ MAT101-2025    │
│ Subject            │ Mathematics    │
│ Client             │ ABC Corp       │
│ Site               │ Main Campus    │
│ Class Start Date   │ 2025-11-05     │
│ Days Until Start   │ 7 days         │
└────────────────────┴────────────────┘
```

## API Reference

### MaterialTrackingRepository

#### `markNotificationSent(int $classId, string $notificationType): void`

Records that a notification was sent for a class.

**Parameters:**
- `$classId` - The class ID
- `$notificationType` - Either `'orange'` or `'red'`

**Example:**
```php
$repo->markNotificationSent(123, 'orange');
```

#### `markDelivered(int $classId): void`

Marks materials as delivered for a class (all notification types).

**Parameters:**
- `$classId` - The class ID

**Example:**
```php
$repo->markDelivered(123);
```

#### `wasNotificationSent(int $classId, string $notificationType): bool`

Checks if a notification was already sent.

**Parameters:**
- `$classId` - The class ID
- `$notificationType` - Either `'orange'` or `'red'`

**Returns:**
- `true` if notification was sent, `false` otherwise

**Example:**
```php
if (!$repo->wasNotificationSent(123, 'orange')) {
    // Send notification
}
```

#### `getDeliveryStatus(int $classId): array`

Gets the delivery status for a class.

**Parameters:**
- `$classId` - The class ID

**Returns:**
```php
[
    'orange_status' => 'notified',  // or null
    'red_status' => 'delivered',    // or null
    'overall_status' => 'delivered' // pending, notified, or delivered
]
```

### MaterialNotificationService

#### `findOrangeStatusClasses(): array`

Finds classes needing Orange notifications (7 days before start).

**Returns:**
- Array of class data matching the criteria

#### `findRedStatusClasses(): array`

Finds classes needing Red notifications (5 days before start).

**Returns:**
- Array of class data matching the criteria

#### `sendMaterialNotifications(array $classes, string $notificationType): int`

Sends material delivery notifications for a list of classes.

**Parameters:**
- `$classes` - Array of class data
- `$notificationType` - Either `'orange'` or `'red'`

**Returns:**
- Number of successfully sent notifications

## Troubleshooting

### No emails being sent

1. **Check recipient configuration:**
   ```php
   echo get_option('wecoza_notification_material_delivery');
   ```

2. **Verify cron is scheduled:**
   ```bash
   wp cron event list | grep material
   ```

3. **Run cron manually and check logs:**
   ```bash
   wp cron event run wecoza_material_notification_check
   tail -f wp-content/debug.log
   ```

### Duplicate notifications

Check for unique constraint violations:
```sql
SELECT class_id, notification_type, COUNT(*) 
FROM class_material_tracking 
GROUP BY class_id, notification_type 
HAVING COUNT(*) > 1;
```

If duplicates exist, clean them up:
```sql
DELETE FROM class_material_tracking 
WHERE id NOT IN (
    SELECT MIN(id) 
    FROM class_material_tracking 
    GROUP BY class_id, notification_type
);
```

### Database connection errors

Verify database credentials in `includes/class-wecoza-events-database.php` and WordPress options.

## Maintenance

### Manual Delivery Marking

Mark materials as delivered for a specific class:

```php
$pdo = \WeCozaEvents\Database\Connection::getPdo();
$schema = \WeCozaEvents\Database\Connection::getSchema();
$repo = new \WeCozaEvents\Models\MaterialTrackingRepository($pdo, $schema);

$repo->markDelivered(123); // Replace 123 with actual class_id
```

### View Tracking Records

```sql
SELECT 
    cmt.id,
    c.class_code,
    cmt.notification_type,
    cmt.delivery_status,
    cmt.notification_sent_at,
    cmt.materials_delivered_at
FROM class_material_tracking cmt
JOIN classes c ON cmt.class_id = c.class_id
WHERE cmt.notification_sent_at IS NOT NULL
ORDER BY cmt.notification_sent_at DESC
LIMIT 20;
```

### Reset Notification for Testing

```sql
DELETE FROM class_material_tracking 
WHERE class_id = 123 AND notification_type = 'orange';
```

## Future Enhancements

Potential improvements for future versions:

- [ ] Admin UI for viewing delivery status
- [ ] Manual trigger button in admin panel
- [ ] SMS notifications support
- [ ] Customizable notification timing (not just 7 and 5 days)
- [ ] Email template customization via admin
- [ ] Delivery confirmation workflow
- [ ] Material checklist per class
- [ ] Integration with existing delivery tracking systems
- [ ] Notification history report/export
- [ ] Dashboard widget showing upcoming deliveries
