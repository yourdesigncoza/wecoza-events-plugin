# Material Tracking Dashboard

## Overview
The Material Tracking Dashboard provides a visual interface for monitoring and managing material delivery notifications across all classes. It is accessible via the WordPress shortcode `[wecoza_material_tracking]`.

## Shortcode Usage

### Basic Usage
```
[wecoza_material_tracking]
```

### With Attributes
```
[wecoza_material_tracking limit="100" status="notified" notification_type="red" days_range="14"]
```

### Attributes
- **limit**: Number of records to display (default: 50, max: 200)
- **status**: Filter by delivery status
  - `all` (default) - Show all records
  - `pending` - Show only pending records
  - `notified` - Show only notified records
  - `delivered` - Show only delivered records
- **notification_type**: Filter by notification type
  - `all` (default) - Show all types
  - `orange` - Show only 7-day notifications
  - `red` - Show only 5-day notifications
- **days_range**: Number of days to look back (default: 30)

## Features

### Dashboard Components

#### 1. Statistics Strip
A horizontal scrollable strip displaying key metrics:
- **Total Tracking**: Count of all records in the selected time range 📦
- **Pending**: Materials awaiting notification ⏳
- **Notified**: Emails sent, awaiting delivery confirmation 📧
- **Delivered**: Materials confirmed as delivered ✅

Click on any statistic to filter the list to that status. The strip is horizontally scrollable on mobile devices.

#### 2. Search and Filters
- **Search Box**: Real-time search by class code, subject, or client name
- **Status Filter**: Filter by delivery status
- **Notification Type Filter**: Filter by orange (7-day) or red (5-day) notifications
- **Refresh Button**: Reload dashboard data

#### 3. Tracking Records List
Each record displays:
- Class code and subject
- Client and site name
- Notification type badge (🟠 Orange 7d or 🔴 Red 5d)
- Delivery status badge (⏳ Pending, 📧 Notified, ✅ Delivered)
- Notification sent timestamp
- Delivery confirmation timestamp (when applicable)
- Action button to mark as delivered (for notified records)

#### 4. Interactive Actions
- **Mark as Delivered**: Click button to confirm material delivery
- **Search**: Type to filter records in real-time
- **Filter by Status**: Use dropdowns to narrow results
- **Click Statistics**: Click stat cards to filter by status

### User Permissions

#### View Dashboard
Users with either of these capabilities can view the dashboard:
- `view_material_tracking`
- `manage_options` (WordPress administrators)

#### Manage Materials
Users with either of these capabilities can mark materials as delivered:
- `manage_material_tracking`
- `manage_options` (WordPress administrators)

## Notification Suppression Logic

Once materials are marked as delivered for any notification type (orange or red):
- **All future notifications are suppressed** for that class
- The system checks `delivery_status = 'delivered'` before sending any notification
- This prevents duplicate notifications after delivery confirmation

Example:
1. Orange (7-day) notification sent → Status: Notified
2. User marks materials as delivered → Status: Delivered
3. Red (5-day) notification **will NOT be sent** because status is already "delivered"

## Technical Architecture

### MVC Structure

#### Models
- **MaterialTrackingRepository** (`includes/Models/MaterialTrackingRepository.php`)
  - `getTrackingDashboardData()` - Fetch records with joins
  - `getTrackingStatistics()` - Calculate statistics
  - `markDelivered()` - Update delivery status

#### Services
- **MaterialTrackingDashboardService** (`includes/Services/MaterialTrackingDashboardService.php`)
  - Business logic for data retrieval
  - Permission validation
  - Filter processing

- **MaterialNotificationService** (`includes/Services/MaterialNotificationService.php`)
  - Modified to check `delivery_status = 'delivered'`
  - Suppresses notifications when materials are delivered

#### Controllers
- **MaterialTrackingController** (`includes/Controllers/MaterialTrackingController.php`)
  - AJAX action: `wecoza_mark_material_delivered`
  - Handles delivery confirmation requests

#### Views
- **MaterialTrackingPresenter** (`includes/Views/Presenters/MaterialTrackingPresenter.php`)
  - Formats data for display
  - Generates badge HTML
  - Creates action buttons

- **Templates** (`includes/Views/material-tracking/`)
  - `dashboard.php` - Main container with JavaScript
  - `statistics.php` - Statistics cards
  - `list-item.php` - Individual tracking record
  - `empty-state.php` - No records message

#### Shortcodes
- **MaterialTrackingShortcode** (`includes/Shortcodes/MaterialTrackingShortcode.php`)
  - Parses shortcode attributes
  - Coordinates service, presenter, and renderer
  - Renders dashboard HTML

### Database Queries

#### Dashboard Data Query
```sql
SELECT cmt.*, c.class_code, c.class_subject, c.original_start_date,
       cl.client_name, s.site_name
FROM class_material_tracking cmt
LEFT JOIN classes c ON cmt.class_id = c.class_id
LEFT JOIN clients cl ON c.client_id = cl.client_id
LEFT JOIN sites s ON c.site_id = s.site_id
WHERE cmt.created_at >= (CURRENT_DATE - INTERVAL ':days_range days')
  AND (:status IS NULL OR cmt.delivery_status = :status)
  AND (:notification_type IS NULL OR cmt.notification_type = :notification_type)
ORDER BY 
    CASE cmt.delivery_status
        WHEN 'notified' THEN 1
        WHEN 'pending' THEN 2
        WHEN 'delivered' THEN 3
    END,
    cmt.notification_sent_at DESC
LIMIT :limit
```

#### Statistics Query
```sql
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN delivery_status = 'notified' THEN 1 ELSE 0 END) as notified,
    SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered
FROM class_material_tracking
WHERE created_at >= (CURRENT_DATE - INTERVAL ':days_range days')
```

### AJAX Communication

#### Mark as Delivered Request
```javascript
POST /wp-admin/admin-ajax.php
{
    action: 'wecoza_mark_material_delivered',
    class_id: 123,
    nonce: 'abc123...'
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "message": "Materials marked as delivered successfully.",
        "class_id": 123
    }
}
```

#### Error Response
```json
{
    "success": false,
    "data": {
        "message": "Failed to mark materials as delivered. Please try again."
    }
}
```

## Security

### Nonce Verification
All AJAX requests require a valid nonce:
- Nonce action: `wecoza_material_tracking_action`
- Generated via `wp_create_nonce()`
- Verified via `check_ajax_referer()`

### Input Sanitization
- `class_id`: Validated with `absint()`
- `status`: Whitelist validation (pending, notified, delivered)
- `notification_type`: Whitelist validation (orange, red)

### SQL Injection Prevention
- All queries use prepared statements with PDO
- Parameter binding for all user inputs
- No direct string concatenation in SQL

### Capability Checks
- View dashboard: `view_material_tracking` or `manage_options`
- Mark as delivered: `manage_material_tracking` or `manage_options`

## JavaScript Features

### Real-Time Search
- Filters records as you type
- Searches: class code, subject, client name
- Case-insensitive matching

### Client-Side Filtering
- Status filter (all, pending, notified, delivered)
- Notification type filter (all, orange, red)
- Updates visible count dynamically

### AJAX Actions
- Mark as delivered without page reload
- Shows loading spinner during request
- Updates UI on success

### Toast Notifications
- Success: Green background
- Error: Red background
- Auto-dismisses after 5 seconds

### Auto-Update Time Display
- Shows "Last updated: X minutes ago"
- Updates every 10 seconds
- Tracks time since last refresh

## Troubleshooting

### Dashboard Not Loading
1. Check user has required permissions
2. Verify PostgreSQL connection is working
3. Check error logs: `wp-content/debug.log`
4. Ensure shortcode is spelled correctly: `[wecoza_material_tracking]`

### "Mark as Delivered" Not Working
1. Check user has `manage_material_tracking` capability
2. Verify AJAX URL is correct (admin-ajax.php)
3. Check browser console for JavaScript errors
4. Verify nonce is being generated correctly

### Records Not Showing
1. Check `days_range` attribute (default is 30 days)
2. Verify records exist in `class_material_tracking` table
3. Check filter settings (status, notification_type)
4. Review database query in error logs

### Notifications Still Being Sent After Delivery
1. Verify `delivery_status` is set to 'delivered' in database
2. Check MaterialNotificationService query includes delivered check
3. Review error logs for notification processing errors

## Example Implementation

### Add to WordPress Page
1. Edit any page or post
2. Add shortcode block
3. Insert: `[wecoza_material_tracking]`
4. Publish page

### Custom Capability for Specific Users
```php
// Add to theme's functions.php
add_action('admin_init', function() {
    $role = get_role('shop_manager'); // Or any custom role
    $role->add_cap('view_material_tracking');
    $role->add_cap('manage_material_tracking');
});
```

### Filter Dashboard Data Programmatically
```php
// Filter by status before rendering
add_filter('wecoza_material_tracking_filters', function($filters) {
    $filters['status'] = 'notified'; // Only show notified items
    return $filters;
});
```

## Files Created

### PHP Classes (7 files)
1. `includes/Services/MaterialTrackingDashboardService.php`
2. `includes/Controllers/MaterialTrackingController.php`
3. `includes/Shortcodes/MaterialTrackingShortcode.php`
4. `includes/Views/Presenters/MaterialTrackingPresenter.php`

### View Templates (4 files)
5. `includes/Views/material-tracking/dashboard.php`
6. `includes/Views/material-tracking/statistics.php`
7. `includes/Views/material-tracking/list-item.php`
8. `includes/Views/material-tracking/empty-state.php`

### Modified Files (3 files)
1. `includes/Models/MaterialTrackingRepository.php` - Added 2 methods
2. `includes/Services/MaterialNotificationService.php` - Updated suppression logic
3. `wecoza-events-plugin.php` - Registered shortcode and controller

## Support

For issues or questions:
1. Check WordPress debug logs: `wp-content/debug.log`
2. Review browser console for JavaScript errors
3. Verify database connection and table structure
4. Consult PRD: `docs/material-tracking-dashboard-prd.md`
