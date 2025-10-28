## Implementation Plan: `[wecoza_insert_update_ai_summary]` Shortcode

### 1. Create Repository Method
**File**: `includes/Models/ClassChangeLogRepository.php`
- Add `getLogsWithAISummary()` method to fetch records with ai_summary JSON data
- Support filtering by class_id, operation (INSERT/UPDATE), and limit
- Query columns: log_id, operation, changed_at, class_id, class_code, class_subject, ai_summary

### 2. Create Service Layer
**File**: `includes/Services/AISummaryDisplayService.php`
- New service class following strict typing patterns
- Method `getSummaries(int $limit, ?int $classId, ?string $operation): array`
- Connects to PostgreSQL via existing database helper
- Handles exceptions and returns structured data

### 3. Create Presenter
**File**: `includes/Views/Presenters/AISummaryPresenter.php`
- Transform raw DB records into view-ready arrays
- Parse ai_summary JSON field (model, status, summary, tokens_used, generated_at)
- Format operation badges (INSERT=success, UPDATE=primary)
- Format dates using WordPress date functions
- Extract and format bullet-pointed summary text
- Handle null/invalid summaries gracefully

### 4. Create Shortcode Handler
**File**: `includes/Shortcodes/AISummaryShortcode.php`
- Class: `AISummaryShortcode`
- Shortcode tag: `wecoza_insert_update_ai_summary`
- Attributes: `layout` (card|timeline, default: card), `limit` (default: 20), `class_id`, `operation`
- Dependency injection: service, presenter, renderer, request
- Static `register()` method following existing pattern

### 5. Create View Templates

**File**: `includes/Views/ai-summary/main.php`
- Main wrapper with layout switcher
- Search box and operation filter (INSERT/UPDATE/All)
- Filter status badge
- Conditionally include card.php or timeline.php
- Bootstrap 5 structure with Phoenix classes

**File**: `includes/Views/ai-summary/card.php`
- Responsive card grid (col-12 col-md-6 col-lg-4)
- Each card displays:
  - Header: Class code/subject + operation badge
  - Body: Formatted AI summary (parse bullet points)
  - Footer: Date, model, tokens, status indicator
- Empty state handling

**File**: `includes/Views/ai-summary/timeline.php`
- Vertical timeline layout (Phoenix timeline pattern)
- Timeline items with operation icons
- Chronological order with connecting lines
- Each item: date, class info, operation badge, summary content
- Responsive design for mobile

### 6. Add JavaScript Functionality
- Inline in shortcode's `getAssets()` method
- Client-side search/filter (by class code, subject, summary text)
- Operation filter dropdown handling
- Show/hide cards based on filters
- Update status badge with result count
- Escape HTML utility functions

### 7. Integration
**File**: `wecoza-events-plugin.php`
- Add require_once statements for new files:
  - AISummaryDisplayService.php
  - AISummaryPresenter.php
  - AISummaryShortcode.php
- Register shortcode with Container dependencies
- Create directory: `includes/Views/ai-summary/`

### 8. Container Updates
**File**: `includes/Support/Container.php` (if needed)
- Add factory methods for new service and presenter
- Follow existing singleton pattern

### Key Design Decisions
- **Two layouts**: Card view (default, grid) and Timeline view (chronological)
- **Phoenix UI**: Use existing Bootstrap 5 + Phoenix theme classes
- **No AJAX**: Static display with client-side filtering
- **Accessibility**: Proper ARIA labels, semantic HTML
- **Error handling**: Display "No summary available" for null ai_summary
- **Responsive**: Mobile-first design with responsive breakpoints

### Usage Examples
```php
// Default card view
[wecoza_insert_update_ai_summary]

// Timeline view, 10 recent
[wecoza_insert_update_ai_summary layout="timeline" limit="10"]

// Specific class, INSERT operations only
[wecoza_insert_update_ai_summary class_id="123" operation="INSERT"]
```