## Add Scrollable Container to Card View Layout

### Issue
The card bodies in the AI summary card view need a scrollable wrapper to handle overflow content, similar to the `timeline-scroll-wrapper` pattern shown in `docs/example.html`.

### Solution: Wrap Card Grid in Scrollable Container

**File**: `includes/Views/ai-summary/card.php`

Add a wrapper div around the card grid with:
- Class: `timeline-scroll-wrapper` (for consistency with existing pattern)
- Inline styles:
  - `max-height: 600px` - Limit vertical height
  - `overflow-y: auto` - Enable vertical scrolling
  - `overflow-x: hidden` - Prevent horizontal scrolling
  - `padding-right: 10px` - Space for scrollbar

### Updated Structure
```html
<div class="timeline-scroll-wrapper" style="max-height: 600px; overflow-y: auto; overflow-x: hidden; padding-right: 10px;">
    <div class="row g-3">
        <!-- Existing card loop -->
    </div>
</div>
```

### Benefits
1. **Consistent UI**: Matches the scrollable pattern used in timeline examples
2. **Fixed height**: Prevents page from becoming too long with many summaries
3. **Better UX**: Users can scroll within the component without scrolling entire page
4. **Responsive**: Works on all screen sizes

### Note
The timeline layout already has custom scrolling via inline styles in `timeline.php`, so only the card layout needs this update.