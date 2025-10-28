## Add Scrollable Content Area to Individual Cards

### Issue
The AI summary content within each card can be very long, causing cards to have inconsistent heights and making the grid layout unbalanced. Need to limit the height of individual card bodies so each card has a consistent size.

### Solution: Add Max Height to Card Body

**File**: `includes/Views/ai-summary/card.php`

Update the `.card-body` div to include scrollable overflow:

**Current**:
```html
<div class="card-body">
    <!-- content -->
</div>
```

**Updated**:
```html
<div class="card-body" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
    <!-- content -->
</div>
```

### Styling Details
- `max-height: 300px` - Limit content area to reasonable height
- `overflow-y: auto` - Enable vertical scrolling within card when content exceeds max height
- `overflow-x: hidden` - Prevent horizontal overflow
- Header and footer remain fixed height
- Content area becomes scrollable independently

### Benefits
1. **Consistent card heights**: All cards roughly same size regardless of content length
2. **Better grid layout**: Even card grid without awkward spacing
3. **Improved scannability**: Users can see all cards at once, then scroll individual card content if interested
4. **Two-level scrolling**: 
   - Outer wrapper scrolls through all cards (600px max)
   - Individual card bodies scroll through long summaries (300px max)

### Visual Result
Each card will have:
- Fixed header (class code/subject + operation badge)
- Scrollable body (AI summary content, max 300px)
- Fixed footer (metadata: date, model, tokens, status)

This creates a clean, consistent grid where users can quickly scan summaries and drill into details by scrolling individual cards.