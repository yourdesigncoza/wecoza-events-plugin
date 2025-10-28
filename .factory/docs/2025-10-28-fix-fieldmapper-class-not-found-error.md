## Problem
The `FieldMapper` class is being used by `DataObfuscator` trait in the AI summary service, but it's not being loaded in the main plugin bootstrap file (`wecoza-events-plugin.php`). This causes a fatal error when the notification processor runs via cron: `Class "WeCozaEvents\Support\FieldMapper" not found`.

## Root Cause
The `FieldMapper.php` file exists at `includes/Support/FieldMapper.php` and is used by `includes/Services/AISummaryService/Traits/DataObfuscator.php`, but there's no `require_once` statement for it in the main plugin file.

## Solution
Add the missing `require_once` statement for `FieldMapper.php` in `wecoza-events-plugin.php`, placing it before `AISummaryService.php` since that service uses the trait that depends on FieldMapper.

**Change Location:** `wecoza-events-plugin.php` line 27 (after OpenAIConfig, before AISummaryService)

```php
require_once WECOZA_EVENTS_PLUGIN_DIR . 'includes/Support/FieldMapper.php';
```

This ensures the FieldMapper class is available before any code tries to use it.