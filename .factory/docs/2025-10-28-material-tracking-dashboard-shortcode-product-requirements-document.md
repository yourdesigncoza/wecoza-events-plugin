# Product Requirements Document: Material Tracking Dashboard Shortcode

**Document Version:** 1.0  
**Created:** 2025-10-28  
**Product:** WeCoza Events Plugin - Material Tracking Visualization  
**Target Audience:** WordPress administrators, project managers, material coordinators

---

## Executive Summary

Create a WordPress shortcode `[wecoza_material_tracking]` that displays a visual dashboard of the `class_material_tracking` database table. The shortcode will provide real-time visibility into material delivery status across all classes, with summary statistics and the ability to mark materials as delivered directly from the dashboard.

---

## 1. Product Overview

### 1.1 Purpose
Provide a centralized, user-friendly dashboard for monitoring material delivery notifications and their fulfillment status across all classes in the WeCoza Events system.

### 1.2 Goals
- **Visibility:** Clear overview of pending, notified, and delivered materials
- **Actionability:** Quick access to mark materials as delivered
- **Efficiency:** Reduce time spent checking material status via database queries
- **Integration:** Seamless integration with existing notification system

### 1.3 Success Metrics
- Reduction in manual database queries for material status
- Faster material delivery confirmation (< 30 seconds to mark delivered)
- 100% accuracy in suppressing notifications after delivery confirmation
- User adoption: 80%+ of material coordinators use the dashboard weekly

---

## 2. User Stories

### 2.1 Primary User: Material Coordinator

**As a** material coordinator  
**I want to** see all upcoming material deliveries in one dashboard  
**So that** I can prioritize and plan delivery logistics

**As a** material coordinator  
**I want to** mark materials as delivered with one click  
**So that** I can prevent duplicate notifications from being sent

**As a** material coordinator  
**I want to** filter and search tracking records  
**So that** I can quickly find specific classes or date ranges

**As a** material coordinator  
**I want to** see summary statistics (pending/notified/delivered counts)  
**So that** I can gauge workload at a glance

### 2.2 Secondary User: Project Manager

**As a** project manager  
**I want to** view material delivery status across all classes  
**So that** I can identify bottlenecks or delays

---

## 3. Functional Requirements

### 3.1 Shortcode Implementation

#### 3.1.1 Shortcode Tag
```
[wecoza_material_tracking]
```

#### 3.1.2 Optional Attributes
```
[wecoza_material_tracking 
    limit="50" 
    status="all|pending|notified|delivered"
    notification_type="all|orange|red"
    days_range="7|14|30|all"
]
```

**Attribute Specifications:**
- `limit`: Number of records to display (default: 50, max: 200)
- `status`: Filter by delivery status (default: "all")
- `notification_type`: Filter by notification type (default: "all")
- `days_range`: Show records from last N days (default: "30")

### 3.2 Dashboard Layout (Based on example.html)

#### 3.2.1 Header Section
```
┌─────────────────────────────────────────────────────────┐
│ Material Delivery Tracking                              │
│ Monitor and manage class material deliveries            │
│                                                          │
│ [Search Box]  [Filter: All Statuses ▾]  [🔄 Refresh]   │
└─────────────────────────────────────────────────────────┘
```

**Components:**
- Title: "Material Delivery Tracking"
- Subtitle: "Monitor and manage class material deliveries"
- Search input: Filter by class code, class subject, client name
- Status dropdown: All / Pending / Notified / Delivered
- Refresh button: Reload data without page refresh

#### 3.2.2 Summary Statistics Cards
```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│ 📦 Total     │ ⏳ Pending   │ 📧 Notified  │ ✅ Delivered │
│    45        │    12        │    18        │    15        │
│ tracking     │ awaiting     │ emails sent  │ confirmed    │
│ records      │ notification │              │              │
└──────────────┴──────────────┴──────────────┴──────────────┘
```

**Statistics:**
1. **Total Tracking Records** - Count of all records in table
2. **Pending** - Count where `delivery_status = 'pending'`
3. **Notified** - Count where `delivery_status = 'notified'`
4. **Delivered** - Count where `delivery_status = 'delivered'`

**Visual Design:**
- Bootstrap 5 cards with icon indicators
- Color coding: Grey (total), Yellow (pending), Orange (notified), Green (delivered)
- Click on card to filter list to that status

#### 3.2.3 Detailed List View (Scrollable Card Body)

```
┌─────────────────────────────────────────────────────────────┐
│ Class: ABC123 - Mathematics Grade 10        [🟠 Orange 7d] │
│ Client: Springfield High School                             │
│ Notification Sent: 2025-10-21 09:30 AM                     │
│ Status: Notified         [✓ Mark as Delivered]             │
├─────────────────────────────────────────────────────────────┤
│ Class: XYZ789 - English Grade 9             [🔴 Red 5d]    │
│ Client: Oakwood Academy                                     │
│ Notification Sent: 2025-10-23 10:15 AM                     │
│ Status: Notified         [✓ Mark as Delivered]             │
├─────────────────────────────────────────────────────────────┤
│ Class: DEF456 - Science Grade 11             [✅ Delivered] │
│ Client: Riverside School                                    │
│ Delivered: 2025-10-20 02:45 PM                             │
│ Status: Delivered        [Confirmed]                        │
└─────────────────────────────────────────────────────────────┘
```

**Each Row Contains:**
- **Class Code & Subject** - Linked to class details (if applicable)
- **Client Name** - From joined `clients` table
- **Notification Type Badge** - Orange (7 days) or Red (5 days) with visual color
- **Notification Sent Timestamp** - Date/time when email was sent
- **Delivery Status Badge** - Pending / Notified / Delivered with appropriate styling
- **Action Button** - "Mark as Delivered" (only shown for notified status)
- **Delivered Timestamp** - Shown when status = delivered

**Badge Styling (Bootstrap 5):**
- **Orange (7-day):** `badge-phoenix-warning` with 🟠 icon
- **Red (5-day):** `badge-phoenix-danger` with 🔴 icon
- **Pending:** `badge-phoenix-secondary` with ⏳ icon
- **Notified:** `badge-phoenix-info` with 📧 icon
- **Delivered:** `badge-phoenix-success` with ✅ icon

#### 3.2.4 Empty State
When no records exist:
```
┌─────────────────────────────────────────────┐
│              📦                              │
│   No Material Tracking Records Found        │
│                                              │
│   Material notifications will appear here   │
│   when classes are 7 or 5 days away from    │
│   their start date.                          │
└─────────────────────────────────────────────┘
```

### 3.3 User Actions

#### 3.3.1 Mark as Delivered (Primary Action)

**Trigger:** User clicks "Mark as Delivered" button

**Process:**
1. AJAX call to `wp-admin/admin-ajax.php`
2. Action: `wecoza_mark_material_delivered`
3. Pass parameters: `tracking_id`, `class_id`
4. Verify nonce for security
5. Update database: `MaterialTrackingRepository::markDelivered($classId)`
6. Return success/error response
7. Update UI: Change badge to "Delivered", remove button, show timestamp

**Database Update:**
```sql
UPDATE class_material_tracking 
SET delivery_status = 'delivered',
    materials_delivered_at = NOW(),
    updated_at = NOW()
WHERE class_id = :class_id
  AND delivery_status != 'delivered';
```

**Notification Suppression Logic:**
Once marked as delivered:
- **Orange (7-day) delivered:** Red (5-day) notification will NOT be sent
- **Red (5-day) delivered:** No additional notifications
- System checks `delivery_status = 'delivered'` before sending any notification

**User Feedback:**
- **Success:** Toast notification "✅ Materials marked as delivered for [Class Code]"
- **Error:** Toast notification "❌ Failed to update delivery status. Please try again."

#### 3.3.2 Search/Filter

**Search Box:**
- Real-time filter (client-side JavaScript)
- Searches: class_code, class_subject, client_name
- Case-insensitive
- Highlights matching text

**Status Filter Dropdown:**
- All Statuses (default)
- Pending Only
- Notified Only
- Delivered Only

**Notification Type Filter:**
- All Types (default)
- Orange (7-day) Only
- Red (5-day) Only

**Date Range Filter:**
- Last 7 days
- Last 14 days
- Last 30 days (default)
- All Time

#### 3.3.3 Refresh Data
- Manual refresh button in header
- Auto-refresh every 60 seconds (configurable)
- Show "Last updated: X seconds ago" indicator

### 3.4 Data Requirements

#### 3.4.1 Database Query
```sql
SELECT 
    cmt.id,
    cmt.class_id,
    cmt.notification_type,
    cmt.notification_sent_at,
    cmt.materials_delivered_at,
    cmt.delivery_status,
    cmt.created_at,
    cmt.updated_at,
    c.class_code,
    c.class_subject,
    c.original_start_date,
    cl.client_name,
    s.site_name
FROM class_material_tracking cmt
LEFT JOIN classes c ON cmt.class_id = c.class_id
LEFT JOIN clients cl ON c.client_id = cl.client_id
LEFT JOIN sites s ON c.site_id = s.site_id
WHERE cmt.created_at >= (CURRENT_DATE - INTERVAL ':days_range days')
  AND (:status = 'all' OR cmt.delivery_status = :status)
  AND (:notification_type = 'all' OR cmt.notification_type = :notification_type)
ORDER BY 
    CASE cmt.delivery_status
        WHEN 'notified' THEN 1
        WHEN 'pending' THEN 2
        WHEN 'delivered' THEN 3
    END,
    cmt.notification_sent_at DESC,
    cmt.created_at DESC
LIMIT :limit;
```

**Sort Priority:**
1. Notified (action required) - shown first
2. Pending (upcoming) - shown second
3. Delivered (archived) - shown last
4. Within each group: Most recent first

#### 3.4.2 Statistics Query
```sql
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN delivery_status = 'notified' THEN 1 ELSE 0 END) as notified,
    SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered
FROM class_material_tracking
WHERE created_at >= (CURRENT_DATE - INTERVAL ':days_range days');
```

---

## 4. Technical Architecture

### 4.1 MVC Components

#### 4.1.1 Model Layer
**File:** `includes/Models/MaterialTrackingRepository.php` (already exists)

**New Methods Needed:**
```php
public function getTrackingDashboardData(
    int $limit = 50, 
    ?string $status = null,
    ?string $notificationType = null,
    int $daysRange = 30
): array;

public function getTrackingStatistics(int $daysRange = 30): array;
```

#### 4.1.2 Service Layer
**File:** `includes/Services/MaterialTrackingDashboardService.php` (new)

**Responsibilities:**
- Fetch dashboard data from repository
- Calculate statistics
- Handle business logic for delivery confirmation
- Validate user permissions

**Methods:**
```php
public function getDashboardData(array $filters): array;
public function getStatistics(int $daysRange): array;
public function markAsDelivered(int $classId, int $userId): bool;
public function suppressFutureNotifications(int $classId): void;
```

#### 4.1.3 View Layer

**Presenter:** `includes/Views/Presenters/MaterialTrackingPresenter.php` (new)

**Responsibilities:**
- Format dates for display
- Generate badge HTML
- Prepare data for template rendering
- Create action button markup

**Template:** `includes/Views/material-tracking/dashboard.php` (new)

**Structure:**
- Header with search and filters
- Statistics cards
- Scrollable list of tracking records
- Empty state
- JavaScript for interactions

#### 4.1.4 Controller Layer
**File:** `includes/Controllers/MaterialTrackingController.php` (new)

**AJAX Actions:**
1. `wecoza_mark_material_delivered` - Handle delivery confirmation
2. `wecoza_refresh_material_tracking` - Reload dashboard data

**Methods:**
```php
public function handleMarkDelivered(): void;
public function handleRefreshData(): void;
```

#### 4.1.5 Shortcode
**File:** `includes/Shortcodes/MaterialTrackingShortcode.php` (new)

```php
<?php
declare(strict_types=1);

namespace WeCozaEvents\Shortcodes;

final class MaterialTrackingShortcode
{
    public static function register(): void;
    public function render(array $atts = []): string;
    private function parseAttributes(array $atts): array;
    private function renderDashboard(array $data, array $stats): string;
}
```

### 4.2 File Structure

```
includes/
├── Controllers/
│   └── MaterialTrackingController.php         [NEW]
├── Models/
│   └── MaterialTrackingRepository.php         [EXISTS - ADD METHODS]
├── Services/
│   └── MaterialTrackingDashboardService.php   [NEW]
├── Shortcodes/
│   └── MaterialTrackingShortcode.php          [NEW]
└── Views/
    ├── Presenters/
    │   └── MaterialTrackingPresenter.php      [NEW]
    └── material-tracking/
        ├── dashboard.php                       [NEW]
        ├── header.php                          [NEW]
        ├── statistics.php                      [NEW]
        ├── list-item.php                       [NEW]
        └── empty-state.php                     [NEW]
```

### 4.3 JavaScript Components

**File:** `includes/Views/material-tracking/dashboard.php` (inline or external)

**Functionality:**
1. **Search Filter:** Real-time filtering of list items
2. **AJAX Handler:** Submit delivery confirmation
3. **UI Updates:** Toast notifications, loading states
4. **Auto-refresh:** Periodic data reload
5. **Statistics Click:** Filter by status when card clicked

**Dependencies:**
- jQuery (WordPress default)
- Bootstrap 5 JavaScript (already loaded)
- WordPress AJAX API

**Example:**
```javascript
jQuery(document).ready(function($) {
    // Mark as delivered
    $('.mark-delivered-btn').on('click', function() {
        const classId = $(this).data('class-id');
        const nonce = $(this).data('nonce');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wecoza_mark_material_delivered',
                class_id: classId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    showToast('success', response.data.message);
                    refreshDashboard();
                } else {
                    showToast('error', response.data.message);
                }
            }
        });
    });
});
```

---

## 5. UI/UX Design Specifications

### 5.1 Visual Design (Based on example.html)

**Framework:** Bootstrap 5 (Phoenix theme style)

**Color Palette:**
- **Primary:** `#0066cc` (Links, primary actions)
- **Warning (Orange):** `#ff9800` (7-day notifications)
- **Danger (Red):** `#dc3545` (5-day notifications)
- **Success (Green):** `#28a745` (Delivered status)
- **Info (Blue):** `#17a2b8` (Notified status)
- **Secondary (Grey):** `#6c757d` (Pending status)

**Typography:**
- **Title:** `h3.text-body-emphasis` (Material Delivery Tracking)
- **Subtitle:** `p.text-body-tertiary` (Monitor and manage...)
- **List Items:** `fs-8` (Font size 8)
- **Timestamps:** `fs-10` (Font size 10)

**Spacing:**
- Card padding: `p-4`
- List item padding: `py-3`
- Border between items: `border-translucent border-top`

**Responsive Breakpoints:**
- Desktop: Full layout with all columns
- Tablet: Stacked statistics cards, condensed list
- Mobile: Single column, essential info only

### 5.2 Interactive States

**Buttons:**
- **Default:** `btn btn-phoenix-primary btn-sm`
- **Hover:** Slight shadow lift
- **Loading:** Spinner + disabled state
- **Success:** Green checkmark animation

**List Items:**
- **Default:** White background
- **Hover:** Light grey background `hover-actions-trigger`
- **Selected:** Highlighted border (if multi-select added later)

**Badges:**
- **Static:** No interaction
- **Tooltip:** Show full status on hover

---

## 6. Security & Permissions

### 6.1 Access Control

**Who Can View Dashboard:**
- WordPress admin users (`manage_options` capability)
- Custom capability: `view_material_tracking`

**Who Can Mark as Delivered:**
- WordPress admin users (`manage_options` capability)
- Custom capability: `manage_material_tracking`

**Implementation:**
```php
if (!current_user_can('manage_material_tracking')) {
    return '<p>You do not have permission to manage material tracking.</p>';
}
```

### 6.2 AJAX Security

**Nonce Verification:**
```php
check_ajax_referer('wecoza_material_tracking_action', 'nonce');
```

**Input Sanitization:**
- `class_id`: `absint()`
- `status`: Whitelist validation
- `notification_type`: Whitelist validation

**SQL Injection Prevention:**
- All queries use prepared statements via PDO
- Parameter binding for all user inputs

---

## 7. Error Handling

### 7.1 Database Errors
**Scenario:** PostgreSQL connection failure

**User Message:** "Unable to load material tracking data. Please try again later."

**Log:** Error details to `wp-content/debug.log`

### 7.2 Permission Errors
**Scenario:** Unauthorized AJAX request

**Response:** HTTP 403 Forbidden

**User Message:** "You do not have permission to perform this action."

### 7.3 Invalid Data
**Scenario:** Invalid class_id in AJAX request

**Response:** JSON error response

**User Message:** "Invalid request. Please refresh the page and try again."

---

## 8. Performance Considerations

### 8.1 Query Optimization
- **Indexes:** Already exist on `class_material_tracking` (from migration)
- **Limit Results:** Default 50, max 200
- **Pagination:** Not required initially (fits within limit)

### 8.2 Caching Strategy
**Option 1:** Transient caching (WordPress)
```php
$cacheKey = 'wecoza_material_stats_' . $daysRange;
$stats = get_transient($cacheKey);
if ($stats === false) {
    $stats = $this->repository->getTrackingStatistics($daysRange);
    set_transient($cacheKey, $stats, 300); // 5 minutes
}
```

**Option 2:** No caching (real-time data)
- More accurate
- Queries are fast enough with indexes

**Recommendation:** Start without caching, add if needed

### 8.3 Asset Loading
**CSS:** Inline in shortcode or enqueued stylesheet
**JavaScript:** Inline in shortcode (< 100 lines)
**Dependencies:** Bootstrap 5 (already loaded by plugin)

---

## 9. Testing Requirements

### 9.1 Unit Tests
**Repository:**
- Test `getTrackingDashboardData()` with various filters
- Test `getTrackingStatistics()` accuracy
- Test `markAsDelivered()` updates correct records

**Service:**
- Test permission checks
- Test notification suppression logic
- Test data transformation

### 9.2 Integration Tests
**AJAX:**
- Test delivery confirmation with valid/invalid data
- Test nonce verification
- Test permission checks

**Shortcode:**
- Test attribute parsing
- Test rendering with various data sets
- Test empty state display

### 9.3 Manual Testing Checklist
- [ ] Shortcode renders correctly on page
- [ ] Statistics cards show accurate counts
- [ ] Search filter works in real-time
- [ ] Status filter updates list correctly
- [ ] "Mark as Delivered" button updates status
- [ ] Toast notifications appear on success/error
- [ ] Auto-refresh works every 60 seconds
- [ ] Empty state displays when no records
- [ ] Responsive design works on mobile
- [ ] AJAX requests complete successfully
- [ ] Database updates persist correctly
- [ ] Future notifications are suppressed after delivery
- [ ] Permissions are enforced correctly

---

## 10. Documentation Requirements

### 10.1 User Documentation
**File:** `docs/MATERIAL_TRACKING_DASHBOARD.md`

**Contents:**
- How to add shortcode to page
- Dashboard features overview
- How to mark materials as delivered
- Filtering and search tips
- Troubleshooting common issues

### 10.2 Developer Documentation
**File:** `docs/MATERIAL_TRACKING_DASHBOARD_API.md`

**Contents:**
- Architecture overview
- API endpoints
- Database schema reference
- Customization hooks
- Testing procedures

### 10.3 Inline Code Documentation
- PHPDoc blocks for all classes and methods
- JSDoc for JavaScript functions
- Code comments for complex logic

---

## 11. Future Enhancements (Out of Scope for v1)

### 11.1 Phase 2 Features
- **Bulk Actions:** Mark multiple items as delivered at once
- **Export:** Download tracking data as CSV/Excel
- **Email Notifications:** Alert coordinators of pending deliveries
- **Custom Columns:** Allow users to show/hide columns
- **Advanced Sorting:** Multi-column sort support

### 11.2 Phase 3 Features
- **Delivery Notes:** Add comments/notes to tracking records
- **File Attachments:** Upload delivery receipts or photos
- **History Timeline:** Show full delivery lifecycle per class
- **Mobile App:** Dedicated mobile interface for field staff
- **Reporting:** Generate PDF reports for management

---

## 12. Success Criteria & Acceptance Testing

### 12.1 Functional Acceptance
✅ Shortcode can be added to any WordPress page/post  
✅ Dashboard displays real-time data from `class_material_tracking`  
✅ Statistics cards show accurate counts  
✅ List is sortable and filterable  
✅ "Mark as Delivered" button updates database  
✅ Future notifications are suppressed after delivery confirmation  
✅ UI matches example.html design patterns  
✅ All AJAX actions work without page refresh  
✅ Permissions are enforced correctly  

### 12.2 Performance Acceptance
✅ Dashboard loads in < 2 seconds  
✅ AJAX requests complete in < 1 second  
✅ Search filter responds instantly (< 100ms)  
✅ No memory leaks or console errors  
✅ Works on Chrome, Firefox, Safari, Edge  

### 12.3 Security Acceptance
✅ All AJAX actions require valid nonce  
✅ Capabilities are checked before actions  
✅ SQL injection is prevented via prepared statements  
✅ XSS is prevented via proper escaping  
✅ No sensitive data exposed in client-side code  

---

## 13. Implementation Phases

### Phase 1: Core Dashboard (Week 1)
- [ ] Create repository methods for dashboard data
- [ ] Create service layer
- [ ] Create presenter
- [ ] Create shortcode
- [ ] Create basic template (no interactions)
- [ ] Manual testing

### Phase 2: Interactivity (Week 1-2)
- [ ] Create AJAX controller
- [ ] Add "Mark as Delivered" functionality
- [ ] Add search/filter JavaScript
- [ ] Add auto-refresh
- [ ] Add toast notifications
- [ ] Integration testing

### Phase 3: Polish & Documentation (Week 2)
- [ ] Refine UI styling
- [ ] Add empty states
- [ ] Add loading indicators
- [ ] Write user documentation
- [ ] Write developer documentation
- [ ] UAT with material coordinators

---

## 14. Appendices

### Appendix A: Database Schema Reference
```sql
CREATE TABLE public.class_material_tracking (
    id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL,
    notification_type VARCHAR(20) NOT NULL CHECK (notification_type IN ('orange', 'red')),
    notification_sent_at TIMESTAMP WITHOUT TIME ZONE,
    materials_delivered_at TIMESTAMP WITHOUT TIME ZONE,
    delivery_status VARCHAR(20) DEFAULT 'pending' CHECK (delivery_status IN ('pending', 'notified', 'delivered')),
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);
```

### Appendix B: Notification Suppression Logic
```
IF delivery_status = 'delivered' for ANY notification_type for class_id:
    → Do NOT send future notifications for this class_id
    → Skip in MaterialNotificationService::findOrangeStatusClasses()
    → Skip in MaterialNotificationService::findRedStatusClasses()
    
Query modification:
WHERE c.original_start_date = CURRENT_DATE + INTERVAL 'X days'
  AND NOT EXISTS (
      SELECT 1 FROM class_material_tracking
      WHERE class_id = c.class_id
        AND delivery_status = 'delivered'  ← KEY CHANGE
  )
```

### Appendix C: Wireframe
```
┌────────────────────────────────────────────────────────────────┐
│  Material Delivery Tracking                                    │
│  Monitor and manage class material deliveries                  │
│                                                                 │
│  [🔍 Search classes...]  [All Statuses ▾]  [🔄 Refresh]       │
├────────────────────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │ 📦 Total │  │ ⏳ Pending│  │ 📧 Notif │  │ ✅ Deliv │      │
│  │   45     │  │    12     │  │    18    │  │    15    │      │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘      │
├────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ ABC123 - Math Grade 10          [🟠 Orange 7d] Notified │ │
│  │ Springfield High School                                  │ │
│  │ Sent: 2025-10-21 09:30 AM    [✓ Mark as Delivered]     │ │
│  ├──────────────────────────────────────────────────────────┤ │
│  │ XYZ789 - English Grade 9         [🔴 Red 5d] Notified  │ │
│  │ Oakwood Academy                                          │ │
│  │ Sent: 2025-10-23 10:15 AM    [✓ Mark as Delivered]     │ │
│  ├──────────────────────────────────────────────────────────┤ │
│  │ DEF456 - Science Grade 11          [✅ Delivered]       │ │
│  │ Riverside School                                         │ │
│  │ Delivered: 2025-10-20 02:45 PM                          │ │
│  └──────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
```

---

## 15. Sign-off

**Product Owner:** _________________  
**Technical Lead:** _________________  
**Date:** _________________

---

**END OF PRD**