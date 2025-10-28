# Remove Top Border from First List Item

## Problem
Currently, all list items have `border-top` class, which creates a double border between the card header and the first tracking record. This looks visually cluttered.

## Solution
Only apply `border-top` to items after the first one, so the first item has no top border.

## Implementation Approach

### Option 1: CSS-based (Recommended)
Add a CSS rule to hide the border on the first child:

```css
.material-tracking-list-item:first-child {
    border-top: none !important;
}
```

**Pros:**
- Simple, no PHP changes needed
- Works automatically with filtered results
- No performance impact

### Option 2: PHP Loop Index
Track the loop index and conditionally add border class:

```php
<?php foreach ($records as $index => $record): ?>
    <?php 
    $borderClass = $index === 0 ? '' : 'border-top';
    echo $this->render('material-tracking/list-item', [
        'record' => $record, 
        'can_manage' => $can_manage,
        'border_class' => $borderClass
    ]); 
    ?>
<?php endforeach; ?>
```

Then in `list-item.php`:
```php
<div class="material-tracking-list-item py-3 border-translucent <?php echo $border_class ?? 'border-top'; ?>">
```

**Pros:**
- Explicit control
- No CSS override needed

**Cons:**
- Requires passing extra variable
- More complex template logic

### Option 3: Hybrid Approach
Keep the existing structure but add CSS rule. If JavaScript filtering hides the first item, the next visible item becomes `:first-child` automatically.

## Recommended Solution: **Option 1 (CSS)**

### File to Modify
`includes/Views/material-tracking/dashboard.php`

### Change Location
Add CSS rule in the existing `<style>` block (around line 100):

```css
.material-tracking-list-item:first-child {
    border-top: none !important;
}
```

This will automatically handle:
- Initial page load
- Filtered results (first visible item has no border)
- Dynamic updates after marking as delivered

## Visual Result
**Before:** Double border between header and first item  
**After:** Clean transition from header to first item

Ready to implement?