1. Update `AISummaryService::callOpenAI` to send `max_completion_tokens` in the payload instead of `max_tokens`, keeping the existing limit value.
2. Confirm no other references to `max_tokens` remain in the plugin code.
3. Run `php -l includes/Services/AISummaryService.php` to ensure the updated file passes a syntax check.