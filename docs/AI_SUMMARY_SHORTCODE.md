# AI Summary Display Shortcode

## Overview
The `[wecoza_insert_update_ai_summary]` shortcode displays AI-generated summaries of class changes (INSERT and UPDATE operations) from the PostgreSQL `class_change_logs` table.

## Shortcode Usage

### Basic Usage
```
[wecoza_insert_update_ai_summary]
```
Displays up to 20 recent AI summaries in card layout.

### With Parameters
```
[wecoza_insert_update_ai_summary layout="timeline" limit="10"]
```

```
[wecoza_insert_update_ai_summary class_id="123" operation="INSERT"]
```

## Parameters

| Parameter   | Type    | Default | Description                                        |
|-------------|---------|---------|---------------------------------------------------|
| `layout`    | string  | `card`  | Display layout: `card` or `timeline`              |
| `limit`     | integer | `20`    | Number of summaries to display (max recommended)  |
| `class_id`  | integer | `null`  | Filter by specific class ID                       |
| `operation` | string  | `null`  | Filter by operation type: `INSERT` or `UPDATE`    |

## Layouts

### Card Layout (Default)
- Responsive grid layout (3 columns on desktop, 2 on tablet, 1 on mobile)
- Each card displays:
  - Class code and subject
  - Operation badge (NEW CLASS or UPDATED)
  - Formatted AI summary with bullet points
  - Metadata: timestamp, AI model, tokens used, status

### Timeline Layout
- Vertical timeline with chronological display
- Timeline markers color-coded by operation:
  - Green for INSERT operations
  - Blue for UPDATE operations
- Compact view showing all details in order

## Features

### Search & Filter
- **Search box**: Real-time search across class code, subject, and summary text
- **Operation filter**: Dropdown to filter by INSERT or UPDATE operations
- **Result counter**: Shows filtered results count

### UI Components
- Bootstrap 5 styling with Phoenix theme
- Responsive design for all screen sizes
- Empty state messaging
- Status badges for AI summary generation status
- Icon indicators for metadata

## Technical Implementation

### Architecture
```
includes/
├── Models/
│   └── ClassChangeLogRepository.php      # Database queries
├── Services/
│   └── AISummaryDisplayService.php       # Business logic
├── Views/
│   ├── Presenters/
│   │   └── AISummaryPresenter.php        # Data formatting
│   └── ai-summary/
│       ├── main.php                       # Main wrapper
│       ├── card.php                       # Card layout template
│       └── timeline.php                   # Timeline layout template
└── Shortcodes/
    └── AISummaryShortcode.php            # Shortcode handler
```

### Database Query
Fetches from `public.class_change_logs`:
- Columns: `log_id`, `operation`, `changed_at`, `class_id`, `class_code`, `class_subject`, `ai_summary`
- Filters: Optional by `class_id` and/or `operation`
- Order: `changed_at DESC` (most recent first)

### AI Summary JSON Structure
```json
{
  "model": "gpt-5-mini",
  "status": "success",
  "summary": "- Schedule change: ...\n- Learners: ...",
  "tokens_used": 2516,
  "generated_at": "2025-10-28T03:46:04+00:00"
}
```

## Styling

### CSS Classes Used
- **Phoenix badges**: `badge-phoenix-success`, `badge-phoenix-primary`, `badge-phoenix-warning`, `badge-phoenix-danger`
- **Cards**: Standard Bootstrap 5 card components
- **Icons**: Bootstrap Icons (bi-*)
- **Typography**: Phoenix font size utilities (fs-9, fs-10)

### Custom Timeline CSS
- Timeline connector line and markers
- Responsive adjustments
- Color-coded operation indicators

## JavaScript Functionality
- Client-side search and filtering (no AJAX)
- Real-time updates to visible items
- Result counter updates
- Form submission handling

## Error Handling
- Database connection errors display warning alert
- Missing AI summaries show graceful fallback message
- Invalid parameters default to safe values

## Performance
- No pagination (limit parameter controls load)
- Client-side filtering for fast response
- Optimized SQL queries with prepared statements

## Examples

### Display only new class summaries
```
[wecoza_insert_update_ai_summary operation="INSERT" limit="15"]
```

### Show timeline of specific class changes
```
[wecoza_insert_update_ai_summary layout="timeline" class_id="456"]
```

### Large dashboard view
```
[wecoza_insert_update_ai_summary limit="50"]
```

## WordPress Integration
- Registered in `wecoza-events-plugin.php`
- Uses existing database connection helper
- Follows plugin coding standards
- Internationalization ready (text domain: `wecoza-events`)

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Responsive design for mobile devices
- Progressive enhancement approach
