# Issue: Remove "create-class" Task Template from Frontend

## Problem Description
The "create-class" task template still appears in the frontend even after commenting it out in `TaskTemplateRegistry.php`.

## Current Status
- **Attempted Solution**: Commented out "create-class" entry in `includes/Services/TaskTemplateRegistry.php` line 30
- **Database Cleanup**: User manually removed all entries from the `class_change_logs` table (Option 2)
- **Result**: Task still appears in frontend

## Investigation Summary

### Architecture Flow
1. **Frontend Rendering**: `includes/Views/event-tasks/main.php` renders tasks from `$class['tasks']['open']`
2. **Task Loading**: `ClassTaskService::getClassTasks()` → `TaskManager::getTasksWithTemplate()`
3. **Template Resolution**: `TaskTemplateRegistry::getTemplateForOperation()` provides default templates
4. **Database Caching**: `TaskManager` stores/retrieves tasks from `class_change_logs.tasks` column

### Key Files Involved
- `includes/Services/TaskTemplateRegistry.php` - Template definitions
- `includes/Services/TaskManager.php` - Task persistence and retrieval
- `includes/Services/ClassTaskService.php` - Frontend data preparation
- `includes/Views/event-tasks/main.php` - Frontend rendering

### Current Code State
```php
// includes/Services/TaskTemplateRegistry.php:30
// ['id' => 'create-class', 'label' => 'Create new class'],  // COMMENTED OUT
```

## Possible Root Causes

### 1. Incomplete Database Cleanup
- Some entries may still exist in `class_change_logs.tasks` column
- Need to verify all rows were properly cleared

### 2. WordPress/Object Caching
- WordPress object cache may still hold old task data
- Server-side caching mechanisms could interfere

### 3. Template Registry Caching
- TaskTemplateRegistry instance might be cached somewhere
- Need to verify no static caching exists

### 4. Alternative Template Sources
- WordPress filter `wecoza_events_task_templates` might be adding "create-class" back
- Check for any filter hooks in other plugins or theme

## Troubleshooting Checklist for Later Resolution

### Step 1: Verify Database Cleanup
```sql
SELECT log_id, tasks FROM class_change_logs WHERE tasks IS NOT NULL AND tasks != '';
```

### Step 2: Check WordPress Filters
Search for any `add_filter('wecoza_events_task_templates', ...)` calls:
```bash
grep -r "wecoza_events_task_templates" /path/to/wordpress/
```

### Step 3: Clear Caches
- Clear WordPress object cache: `wp cache flush`
- Clear any server-side caches (Redis, Memcached, etc.)
- Restart web server if needed

### Step 4: Test Fresh Instance
- Create a new test class change operation
- Verify if "create-class" appears in new entries

### Step 5: Debug Template Resolution
Add temporary logging to `TaskTemplateRegistry::getDefaults()` to see actual templates being returned.

## Alternative Solutions

### Option A: WordPress Filter Hook (Recommended)
```php
add_filter('wecoza_events_task_templates', function($templates, $operation) {
    if ($operation === 'insert') {
        $templates['insert'] = array_filter($templates['insert'], function($task) {
            return $task['id'] !== 'create-class';
        });
    }
    return $templates;
}, 10, 2);
```

### Option B: Complete Template Removal
Remove entire "create-class" reference and any associated code logic.

## Investigation Notes
- No other code references "create-class" except documentation examples
- Task appears to be properly commented out in template registry
- Database cleanup approach should work if completed correctly
- Likely caching issue or incomplete cleanup

## Files Potentially Needing Changes
- `includes/Services/TaskTemplateRegistry.php` - Already modified
- Any custom filter implementations (to be investigated)
- Database cleanup verification required

---
**Status**: Pending resolution after other priority issues are addressed.  
**Created**: October 20, 2024  
**Priority**: Medium
