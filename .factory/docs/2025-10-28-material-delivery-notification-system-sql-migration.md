# Material Delivery Notification System - Implementation Spec

## SQL Migration File

I'll create `/docs/material_delivery_notification_migration.sql` with the complete database schema for the `class_material_tracking` table. This file will include:

1. **Table creation** with all columns and constraints
2. **Indexes** for optimal query performance
3. **Foreign key** relationships to the `classes` table
4. **Trigger** for automatic `updated_at` timestamp management
5. **Comments** for documentation

## Implementation Components

### 1. Database Schema (`/docs/material_delivery_notification_migration.sql`)
- Creates `public.class_material_tracking` table
- Tracks Orange (7-day) and Red (5-day) notifications per class
- Prevents duplicate notifications via unique constraint
- Tracks delivery status: `pending` → `notified` → `delivered`

### 2. PHP Implementation Files (to be created after SQL migration)
- **Repository**: `includes/Models/MaterialTrackingRepository.php` - Database operations
- **Service**: `includes/Services/MaterialNotificationService.php` - Business logic
- **Cron Setup**: Add hooks to `wecoza-events-plugin.php` for daily checks
- **Settings**: Update `includes/Admin/SettingsPage.php` (email already configured)

### 3. Key Features
✅ Automatic notifications 7 days (Orange) and 5 days (Red) before class start  
✅ Duplicate prevention via database constraints  
✅ Audit trail with separate timestamps for notification sent vs materials delivered  
✅ Uses existing `wecoza_notification_material_delivery` WordPress option  
✅ Daily cron job for automated checking  

## Next Steps
1. Copy SQL from `/docs/material_delivery_notification_migration.sql` 
2. Execute in your PostgreSQL database
3. Confirm creation with `\d class_material_tracking`
4. Proceed with PHP implementation after SQL is applied