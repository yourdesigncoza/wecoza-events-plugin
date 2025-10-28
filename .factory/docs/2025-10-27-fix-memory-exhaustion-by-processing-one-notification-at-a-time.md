## Fix Memory Exhaustion by Processing One Notification at a Time

### Problem
Fatal error: `Allowed memory size of 536870912 bytes exhausted` during batch notification processing.

### Solution
Process notifications one at a time instead of batching 50 at once. This provides:
- **Predictable memory usage** (~10-20MB per notification)
- **Isolated failures** (one bad record won't crash the entire batch)
- **Simple implementation** (one-line change)
- **Sufficient throughput** (5-minute cron = 12 runs/hour = 12 notifications/hour minimum)

### Changes Required

**File: `includes/Services/NotificationProcessor.php`**

Change the batch limit from 50 to 1:

```php
// Line 42: Change from
private const BATCH_LIMIT = 50;

// To
private const BATCH_LIMIT = 1;
```

### Why This Works

1. **Memory Containment**: Each cron run processes exactly 1 notification then exits, releasing all memory
2. **Reliability**: If one notification has corrupted/huge data, it fails in isolation
3. **5-Minute Cron Schedule**: Runs every 5 minutes = 288 times per day = 288 notifications/day capacity
4. **Natural Rate Limiting**: Prevents overwhelming email servers or OpenAI API
5. **Simpler Debugging**: Logs show exact row that caused any issues

### Trade-offs
- **Slower processing**: 1 notification per 5 minutes vs 50 per 5 minutes
- **More cron executions**: Each execution has WordPress bootstrap overhead (~1-2 seconds)
- **Acceptable for typical workload**: Class changes are not high-frequency events

If you need faster processing in the future, we can:
- Reduce cron interval to 1-2 minutes
- Add memory monitoring and process 3-5 at a time safely

### Testing
After deployment, monitor:
1. Error logs for memory exhaustion errors (should disappear)
2. Notification delivery latency (should be under 5 minutes per notification)
3. Database `wecoza_last_notified_log_id` option advancing steadily