## Fix OpenAI API Timeout Issues

### Problem
OpenAI API requests are timing out after 10 seconds with error: `cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received`

### Root Cause
The `TIMEOUT_SECONDS` constant in `AISummaryService.php` is set to 10 seconds, which is insufficient for OpenAI's Chat Completions API to process and return summarization responses.

### Solution

**1. Increase Timeout to 60 Seconds** (High Priority)
- Update `AISummaryService::TIMEOUT_SECONDS` from `10` to `60`
- This aligns with OpenAI's recommended timeout values for chat completions
- Accounts for network latency, API processing time, and response transmission

**2. Verify Retry Logic** (Already Implemented)
- The service already has 3-attempt retry with exponential backoff (0s, 1s, 2s, 4s delays)
- Timeout errors are correctly classified as retryable via `mapErrorCode()` method
- No changes needed here

**3. Consider NotificationProcessor Runtime Constraints**
- `NotificationProcessor::MAX_RUNTIME_SECONDS` is 20 seconds
- With 60-second timeout, ensure the processor doesn't get stuck on a single request
- Current logic already handles this: it checks `shouldStop()` between iterations
- If a request takes too long, subsequent iterations won't run, but the current request won't be interrupted

### Changes Required

**File: `includes/Services/AISummaryService.php`**
```php
// Line 29: Change from
private const TIMEOUT_SECONDS = 10;
// To
private const TIMEOUT_SECONDS = 60;
```

### Testing
- Monitor error logs for timeout errors after deployment
- Check AI summary status in database (`ai_summary->status` field)
- Verify successful summary generation for complex class changes