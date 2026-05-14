# Fix for "Call to undefined function exec()" Error

## Problem Summary
The order flow execution is failing with:
```
PHP exception: Call to undefined function exec()
```

This occurs because **`exec()` is disabled** in the PHP configuration on your staging server (dev02.stagingit.net), while it works fine locally on XAMPP.

---

## Root Cause

The `exec()` function is essential for running the Node.js order flow automation. On the staging server, this function is disabled in `php.ini` via the `disable_functions` directive—a common security practice on shared hosting.

---

## Solutions

### Solution 1: Check if exec() is Available (Recommended First Step)

Visit your server's configuration checker to confirm what's disabled:

**Access:**
```
https://dev02.stagingit.net/testing_portal/server-check.php?key=admin123
```

This will show:
- ✓ Which PHP functions are enabled/disabled
- ✓ Which functions you need enabled
- ✓ A template email to send to your hosting provider

---

### Solution 2: Contact Your Hosting Provider (Best Long-term)

Use this template to request `exec()` be enabled:

**Email Template:**
```
Subject: Enable PHP exec() Function

Hello,

I need to enable the exec() PHP function on my account for my web application.

Domain: dev02.stagingit.net
PHP Version: [shown on server-check.php page]

The application uses exec() to:
- Run automated Node.js testing scripts
- Execute order flow automation
- Generate test reports and screenshots

Could you please enable this function? All commands are properly escaped 
and validated for security.

Thank you
```

---

### Solution 3: Alternative if exec() Cannot Be Enabled

If your hosting provider won't enable `exec()`, you have options:

#### Option A: Use Node.js Direct Execution (If Node is available)
The order flow runner (`run-order-flow.mjs`) can be executed directly via Node instead of through PHP `exec()`. This requires:
- Node.js installed on the server
- A background job queue system (like cron or supervisor)

#### Option B: Implement Queue-Based Execution
Instead of running the order flow synchronously:
1. PHP receives the request and saves config to a file
2. A background Node.js process (running via cron or supervisor) picks up and executes
3. PHP queries the status instead of waiting for exec() to complete

#### Option C: Use exec() Alternatives
If some functions are enabled, try:
- `shell_exec()` - returns command output
- `system()` - executes and outputs
- `passthru()` - passes raw output
- `proc_open()` - more complex process control

Edit `actions/run_order_flow.php` to use an alternative:
```php
if (function_exists('shell_exec')) {
    // Use shell_exec instead of exec
    $output = shell_exec($nodeCommand);
} elseif (function_exists('system')) {
    // Fall back to system()
    ob_start();
    system($nodeCommand);
    $output = ob_get_clean();
}
```

---

## Quick Fix: Replace exec() Check

The code already has a check for `exec()` availability (line 142-148 in `run_order_flow.php`). 

**Verify the error message is helpful:**
```
If you see:
✓ "exec() is unavailable on this hosting plan"

Then the check worked, and you need to contact your hosting provider.

If you see:
✗ "Call to undefined function exec()"

Then the error handling may have an issue—check PHP error logs.
```

---

## Verification Steps

1. **Check server capabilities:**
   ```
   https://dev02.stagingit.net/testing_portal/server-check.php?key=admin123
   ```

2. **Review PHP error logs** on the staging server for detailed error messages

3. **Contact hosting provider** with the email template above

4. **After exec() is enabled:**
   - Clear any cached configuration: `service php-fpm restart` (if you have access)
   - Retry the order flow execution
   - Verify Node.js scripts run successfully

---

## Related Files

- **Order Flow Execution:** `actions/run_order_flow.php`
- **Server Checker:** `server-check.php`
- **Node.js Runner:** `order_placement/run-order-flow.mjs`
- **Helper Functions:** `helpers/order_flow_reports.php`

---

## Hosting Provider Quick Links

- **InfinityFree:** Support → File Manager → Edit php.ini (if available)
- **Bluehost/SiteGround:** cPanel → MultiPHP INI Editor
- **DigitalOcean/Linode:** SSH access → edit `/etc/php/X.X/fpm/php.ini`
- **AWS/GCP:** Modify PHP container configuration

---

## Prevention for Future Deployments

When deploying to a new server, **always verify** that critical functions are available:
```php
$criticalFunctions = ['exec', 'file_put_contents', 'file_get_contents'];
foreach ($criticalFunctions as $func) {
    if (!function_exists($func)) {
        die("Critical function '$func' is disabled on this server!");
    }
}
```

---

## Support

If issues persist after enabling `exec()`:
1. Check PHP error logs: `/var/log/php-errors.log` or cPanel error logs
2. Verify Node.js is installed: `which node` or `node --version`
3. Test manual Node execution: SSH and run `node /path/to/run-order-flow.mjs`
4. Check file permissions on report directories (must be 0777 or similar)

---

**Last Updated:** 2026-05-09
**Status:** exec() function disabled on staging server - awaiting hosting provider enablement
