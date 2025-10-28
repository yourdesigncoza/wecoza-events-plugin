# Convert Material Tracking Dashboard to Compact Table Layout

## Overview
Transform the current card-based list layout into a compact, sortable table structure matching the example.html pattern, making the dashboard more space-efficient and scannable.

## Changes Required

### 1. **dashboard.php** - Main Container
- Replace list container with `<table>` structure
- Add `table-responsive` wrapper with Bootstrap table classes: `table table-hover table-sm fs-9 mb-0 overflow-hidden`
- Add `<thead>` with sortable column headers for:
  - **Class Code/Subject** (text sort)
  - **Client/Site** (text sort, no icons)
  - **Class Start Date** (date sort, no icons)
  - **Notification Type** (badge display, text sort)
  - **Status** (badge display, text sort, no icons)
  - **Actions** (non-sortable)
- Each header gets `data-sortable="true"`, `data-sort-key`, `data-sort-type` attributes with chevron indicators
- Convert record loop to render `<tbody>` with rows
- Keep statistics bar, search, and filters in header unchanged
- Keep footer with record counts

### 2. **list-item.php** - Table Row Rendering
- Convert from `<div class="material-tracking-list-item">` to `<tr>` element
- Create compact `<td>` cells for each column:
  - **Class Code/Subject**: Combined in single cell with `fw-medium` span
  - **Client/Site**: Plain text, site name appended if exists (e.g., "EduLearn Academy - Paarl Campus")
  - **Class Start Date**: Formatted date only
  - **Notification Type**: Badge HTML (orange/red)
  - **Status**: Badge HTML (pending/notified/delivered)
  - **Actions**: Checkbox input for "Mark as Delivered" (checked when delivered, disabled when checked)
- Apply `py-2 align-middle` classes to cells for vertical alignment
- Keep `data-status`, `data-notification-type`, `data-class-id` attributes on `<tr>`

### 3. **JavaScript Updates**
- Add column sorting logic:
  - Click handler for `[data-sortable="true"]` headers
  - Sort indicator chevron toggle (up/down)
  - Handle text, numeric, and date sorting types
  - Maintain sort state per column
- Update "Mark as Delivered" to work with checkbox:
  - On checkbox change, trigger AJAX call
  - Disable checkbox and show loading state
  - On success, keep checkbox checked and disabled
  - Update statistics counts
- Preserve existing search and filter functionality

### 4. **CSS Styling**
- Remove list-item hover styles
- Add table-specific styles:
  - Compact row spacing (`py-2` on cells)
  - Header cursor pointer for sortable columns
  - Sort indicator positioning and animations
  - Checkbox styling in Actions column
- Use existing Bootstrap table utilities: `table-hover`, `table-sm`, `fs-9`
- Keep scrollable body with max-height

## Key Implementation Details

```php
// Table header example
<th scope="col" class="border-0" data-sortable="true" data-sort-key="class_code" data-sort-type="text" style="cursor: pointer;">
    Class Code/Subject
    <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
</th>

// Table row example
<tr data-status="<?php echo esc_attr($record['delivery_status']); ?>" data-class-id="<?php echo esc_attr($record['class_id']); ?>">
    <td><span class="fw-medium"><?php echo $record['class_code']; ?> - <?php echo $record['class_subject']; ?></span></td>
    <td><?php echo $record['client_name']; ?><?php if ($record['site_name']) echo ' - ' . $record['site_name']; ?></td>
    <td><?php echo $record['original_start_date']; ?></td>
    <td><?php echo $record['notification_badge_html']; ?></td>
    <td><?php echo $record['status_badge_html']; ?></td>
    <td class="text-center">
        <input type="checkbox" class="form-check-input mark-delivered-checkbox" 
               data-class-id="<?php echo $record['class_id']; ?>"
               <?php echo $record['delivery_status'] === 'delivered' ? 'checked disabled' : ''; ?>>
    </td>
</tr>
```

## Benefits
- **More compact**: Fits more records on screen without scrolling
- **Better scannability**: Columnar layout makes data easier to compare
- **Sortable**: Users can sort by any column to find records quickly
- **Cleaner actions**: Simple checkbox replaces bulky button
- **Consistent UX**: Matches existing example.html pattern used elsewhere in the application