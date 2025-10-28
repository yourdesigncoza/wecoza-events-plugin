## Goal
Resolve the PHP parse error in `wecoza-events-plugin.php` and confirm the plugin loads correctly.

## Root Cause
The bootstrap file registers the WP-CLI command with an erroneously escaped namespace: `\WeCozaEvents\CLI\AISummaryStatusCommand::register();`, which causes a syntax error outside of string context.

## Remediation Steps
1. **Adjust Namespace Invocation**
   - Replace the double backslash namespace qualifier with the correct single backslash version: `\WeCozaEvents\CLI\AISummaryStatusCommand::register();` → `\` should become `\`? Wait; hold. Need to clarify: double slash to single ??? We'll specify: change to `\WeCozaEvents\CLI\...`? Wait final plan should state: replace double with single?**
2. **Static Analysis**
   - Run `php -l wecoza-events-plugin.php` to ensure no syntax errors remain.
3. **Regression Check**
   - (Optional) Trigger WP-CLI command in staging to verify registration: `wp help wecoza ai-summary status`.

## Validation
- Confirm the fatal error no longer appears in `debug.log` after reloading WordPress.
- Ensure plugin bootstrap sequence completes without additional warnings.

Let me know if you approve this plan so I can apply the fix.