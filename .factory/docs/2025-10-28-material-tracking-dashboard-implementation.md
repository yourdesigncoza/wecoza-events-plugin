# Material Tracking Dashboard Implementation Plan

## Overview
Implement a WordPress shortcode `[wecoza_material_tracking]` that displays a visual dashboard for monitoring material delivery status with the ability to mark materials as delivered.

## Architecture Following MVC Pattern

### 1. **Repository Layer** (Update existing)
- **File:** `includes/Models/MaterialTrackingRepository.php`
- Add methods:
  - `getTrackingDashboardData()` - Fetch tracking records with joins to classes, clients, sites
  - `getTrackingStatistics()` - Get counts by delivery status
  - `markDelivered()` - Already exists ✓

### 2. **Service Layer** (New)
- **File:** `includes/Services/MaterialTrackingDashboardService.php`
- Responsibilities:
  - Business logic for dashboard data retrieval
  - Permission validation
  - Statistics calculations
  - Coordinate between repository and presentation layers

### 3. **Controller Layer** (New)
- **File:** `includes/Controllers/MaterialTrackingController.php`
- AJAX handlers:
  - `wecoza_mark_material_delivered` - Handle "Mark as Delivered" action
  - `wecoza_refresh_material_tracking` - Refresh dashboard data
- Security: nonce verification, capability checks, input sanitization

### 4. **Presenter Layer** (New)
- **File:** `includes/Views/Presenters/MaterialTrackingPresenter.php`
- Responsibilities:
  - Format timestamps for display
  - Generate badge HTML (orange/red notification types, delivery statuses)
  - Create action button markup
  - Prepare data for template rendering

### 5. **Shortcode** (New)
- **File:** `includes/Shortcodes/MaterialTrackingShortcode.php`
- Parse attributes: `limit`, `status`, `notification_type`, `days_range`
- Coordinate service, presenter, renderer
- Enqueue inline JavaScript for interactivity

### 6. **View Templates** (New)
- **Directory:** `includes/Views/material-tracking/`
- Files:
  - `dashboard.php` - Main container with header, stats, list
  - `header.php` - Search box and filters
  - `statistics.php` - 4 summary cards
  - `list-item.php` - Individual tracking record row
  - `empty-state.php` - No records message

### 7. **JavaScript Functionality** (Inline)
- Search/filter (client-side)
- AJAX handler for "Mark as Delivered"
- Toast notifications (success/error feedback)
- Auto-refresh every 60 seconds
- Statistics card click filtering

## Key Implementation Details

### Database Query Structure
```sql
SELECT cmt.*, c.class_code, c.class_subject, c.original_start_date,
       cl.client_name, s.site_name
FROM class_material_tracking cmt
LEFT JOIN classes c ON cmt.class_id = c.class_id
LEFT JOIN clients cl ON c.client_id = cl.client_id
LEFT JOIN sites s ON c.site_id = s.site_id
WHERE cmt.created_at >= (CURRENT_DATE - INTERVAL ':days_range days')
ORDER BY (priority by notified first), notification_sent_at DESC
```

### Notification Suppression Update
- **File:** `includes/Services/MaterialNotificationService.php`
- Modify `findClassesByDaysUntilStart()` to check:
  ```sql
  AND NOT EXISTS (
      SELECT 1 FROM class_material_tracking
      WHERE class_id = c.class_id
        AND delivery_status = 'delivered'  -- KEY CHANGE
  )
  ```
- This ensures once materials are marked delivered, no future notifications (orange OR red) are sent.

### Security
- Capability check: `manage_material_tracking` (or `manage_options` fallback)
- Nonce: `wecoza_material_tracking_action`
- Input sanitization: `absint()` for IDs, whitelist for status/type filters

### UI Design (Bootstrap 5 Phoenix Theme)
- 4 statistics cards: Total, Pending, Notified, Delivered
- Color-coded badges: 🟠 Orange, 🔴 Red, ✅ Delivered
- Scrollable list with hover actions
- Responsive layout

## Integration Points
1. **Main Plugin File:** Register shortcode, controller, include new files
2. **Container:** Add service factory methods if using dependency injection
3. **WordPress Hooks:** Register AJAX actions for authenticated users

## Testing Checklist
- [ ] Shortcode renders with various attributes
- [ ] Statistics show accurate counts
- [ ] Search filters work in real-time
- [ ] "Mark as Delivered" updates database and UI
- [ ] Future notifications suppressed after delivery
- [ ] Permissions enforced
- [ ] Responsive design works on mobile

## Files to Create (10 new files)
1. `includes/Services/MaterialTrackingDashboardService.php`
2. `includes/Controllers/MaterialTrackingController.php`
3. `includes/Shortcodes/MaterialTrackingShortcode.php`
4. `includes/Views/Presenters/MaterialTrackingPresenter.php`
5. `includes/Views/material-tracking/dashboard.php`
6. `includes/Views/material-tracking/header.php`
7. `includes/Views/material-tracking/statistics.php`
8. `includes/Views/material-tracking/list-item.php`
9. `includes/Views/material-tracking/empty-state.php`

## Files to Modify (2 existing files)
1. `includes/Models/MaterialTrackingRepository.php` - Add 2 methods
2. `includes/Services/MaterialNotificationService.php` - Update suppression logic
3. `wecoza-events-plugin.php` - Register shortcode and controller

Ready to proceed?