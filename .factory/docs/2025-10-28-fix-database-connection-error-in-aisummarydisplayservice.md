## Fix: Replace Non-existent Class with Correct Connection Class

### Issue
`AISummaryDisplayService.php` references `\WeCoza_Events_Database::get_instance()` which doesn't exist in the codebase. The correct approach is to use `WeCozaEvents\Database\Connection` class.

### Solution
Update `includes/Services/AISummaryDisplayService.php`:

1. **Add missing use statement**:
   ```php
   use WeCozaEvents\Database\Connection;
   ```

2. **Replace getDatabaseConnection() method**:
   - Remove the incorrect `\WeCoza_Events_Database::get_instance()` call
   - Use `Connection::getPdo()` directly (same pattern as NotificationProcessor, TaskManager, etc.)

3. **Replace getSchemaName() method**:
   - Remove manual schema resolution logic
   - Use `Connection::getSchema()` directly

4. **Simplify getSummaries() method**:
   - Call `Connection::getPdo()` and `Connection::getSchema()` directly
   - Follows the exact pattern used in:
     - `NotificationProcessor::boot()`
     - `TaskManager::__construct()`
     - `ClassTaskRepository::__construct()`
     - `Container` class

### Updated Code Pattern
```php
public function getSummaries(int $limit, ?int $classId, ?string $operation): array
{
    $pdo = Connection::getPdo();
    $schema = Connection::getSchema();
    return $this->repository->getLogsWithAISummary($pdo, $schema, $limit, $classId, $operation);
}
```

This matches the established pattern throughout the codebase and eliminates the custom database helper methods.