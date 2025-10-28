# Update Statistics Layout to Horizontal Strip

## Overview
Replace the current 4-column card grid layout with a horizontal scrollable strip layout (similar to the example.html) for a more compact and consistent design.

## Changes Required

### 1. Update `statistics.php` Template
**File:** `includes/Views/material-tracking/statistics.php`

**Current Layout:**
- 4 separate cards in a grid (col-6 col-md-3)
- Each card has icon, count, label, sublabel
- Cards hover with transform effect

**New Layout:**
- Single horizontal strip with borders between items
- Scrollable on mobile (flex-nowrap)
- Text-based with inline badges for icons/indicators
- Format: `Label : Count 🔢`

**Structure:**
```html
<div class="col-12">
  <div class="scrollbar">
    <div class="row g-0 flex-nowrap">
      <div class="col-auto border-end pe-4">
        <h6 class="text-body-tertiary">Total Tracking : 45 📦</h6>
      </div>
      <div class="col-auto px-4 border-end">
        <h6 class="text-body-tertiary">Pending : 12 ⏳</h6>
      </div>
      <div class="col-auto px-4 border-end">
        <h6 class="text-body-tertiary">Notified : 18 📧</h6>
      </div>
      <div class="col-auto px-4">
        <h6 class="text-body-tertiary">Delivered : 15 ✅</h6>
      </div>
    </div>
  </div>
</div>
```

### 2. Update Dashboard Header Layout
**File:** `includes/Views/material-tracking/dashboard.php`

**Changes:**
- Move statistics strip inside the header section
- Integrate with search/filter layout
- Use `card-header p-3 border-bottom` (matching example.html)
- Statistics appear below the title/search row

### 3. Visual Design Updates
- Remove card-based stat containers
- Remove hover transform effects
- Use `text-body-tertiary` for consistent styling
- Icons as inline emojis (not large centered icons)
- Clickable items maintain cursor pointer
- Border separators between stats

### 4. Responsive Behavior
- Desktop: All stats visible in a single row
- Mobile/Tablet: Horizontal scroll enabled via `flex-nowrap`
- Use `scrollbar` class for smooth scrolling

## Benefits
1. **More Compact**: Takes less vertical space
2. **Consistent**: Matches existing example.html design pattern
3. **Mobile-Friendly**: Horizontal scroll instead of wrapping
4. **Professional**: Cleaner, less card-heavy interface
5. **Scannable**: Easier to quickly scan statistics

## Files to Modify
1. `includes/Views/material-tracking/statistics.php` - Replace card grid with horizontal strip
2. `includes/Views/material-tracking/dashboard.php` - Update header structure to match example.html

## JavaScript Impact
- Update selectors if needed (stat cards → stat items)
- Click handler for filtering by status still works (add to h6 elements)
- Stat count updates work the same way (same IDs maintained)

Ready to implement?