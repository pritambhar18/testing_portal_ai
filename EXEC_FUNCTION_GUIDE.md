# PHP exec() Function Disabled - Deployment Guide

## Problem
On shared hosting and some staging servers, the `exec()` PHP function is disabled for security reasons. This causes errors when trying to run test reports.

**Error Message:**
```
PHP exception: Call to undefined function exec()
```

## Solution

### Option 1: Request Function Enablement (Recommended)
Contact your hosting provider and request to enable the `exec()` function. Provide them with this information:

- **Required Functions**: `exec()`, `shell_exec()` (for command execution)
- **Purpose**: Running automated testing scripts and generating screenshots/PDFs
- **Security**: All commands are properly escaped using `escapeshellarg()`

### Option 2: Disable Advanced Features (If exec() Cannot Be Enabled)
If your hosting doesn't allow `exec()`, the application will work with limitations:

**Features that require exec():**
- ❌ Automated form functional checks (Node.js automation)
- ❌ Screenshot capture (wkhtmltoimage)
- ❌ PDF generation (wkhtmltopdf)

**Features that still work:**
- ✓ Basic HTML report generation
- ✓ URL testing and validation
- ✓ Database report storage
- ✓ Report viewing and management

### Option 3: Use Alternative Hosting
If exec() is critical for your use case, consider:
- **VPS Hosting** (Linode, DigitalOcean, AWS)
- **Dedicated Servers**
- **Docker Containers** (complete control)

These provide full PHP functionality without restrictions.

## Testing If exec() Is Enabled

Create a test file at `test-exec.php`:

```php
<?php
if (function_exists('exec')) {
    echo "exec() is ENABLED";
    @exec('echo "test"', $output);
    print_r($output);
} else {
    echo "exec() is DISABLED";
}
?>
```

Access it in your browser: `https://yourdomain.com/test-exec.php`

## Hosting Restrictions

| Hosting Type | exec() | Screenshots | PDF | Automation |
|---|---|---|---|---|
| **Shared Hosting** | Usually No | No | No | No |
| **Managed WordPress** | Usually No | No | No | No |
| **Standard cPanel** | Yes | Yes | Yes | Yes |
| **Staging/Dev** | Sometimes | No | No | No |
| **VPS** | Yes | Yes | Yes | Yes |
| **Dedicated Server** | Yes | Yes | Yes | Yes |

## Current Fix Applied

The codebase has been updated to:

1. **Check for exec() availability** before calling it
2. **Gracefully handle missing exec()** with informative error messages
3. **Provide warnings** instead of fatal errors
4. **Allow basic report generation** even without exec()

## Error Handling

When `exec()` is disabled, the system will:

```json
{
  "success": true,
  "report": { ... },
  "warnings": [
    "Node automation is unavailable - exec() is disabled",
    "Screenshot capture is unavailable - exec() is disabled",
    "PDF generation is unavailable - exec() is disabled"
  ]
}
```

Reports can still be generated and viewed, but without advanced features.

## Check Your Hosting Plan

### InfinityFree / Free Hosting
- **exec()**: ❌ Disabled
- **shell_exec()**: ❌ Disabled
- **Workaround**: Use the HTML report without automation
- **Recommendation**: Upgrade to paid plan or move to VPS

### 88startech (Current Hosting)
- **exec()**: ✓ Should be Enabled (check with support)
- **Solution**: Contact 88startech support to verify exec() is enabled

### cPanel Hosting (General)
- **exec()**: Usually ✓ Enabled
- **Check**: Run the test-exec.php file above

## How to Request Support from Hosting Provider

**Email Template:**

```
Subject: Request to Enable PHP exec() Function

Hello,

I need to enable the PHP exec() function on my hosting account for my web application.

Account Details:
- Domain: yourdomain.com
- Reason: Running automated testing and report generation
- Security: All commands are properly escaped and validated

Could you please:
1. Enable the exec() function in PHP
2. Verify it works on my hosting
3. Confirm which other functions are restricted

Thank you,
[Your Name]
```

## Files Modified

The following files have been updated with proper error handling:

- `actions/run_test_report.php` - Try-catch wrapper for exec()
- `reports/generate_report.php` - Safe exec() calls with fallback

## Testing After Fix

1. Go to test report generation
2. Enter a URL to test
3. Submit the form

**Expected Results:**
- If exec() is enabled: Full report with all features ✓
- If exec() is disabled: Basic report with warnings ⚠️

## Additional Resources

- [PHP exec() documentation](https://www.php.net/manual/en/function.exec.php)
- [Common Hosting Restrictions](https://www.php.net/manual/en/security.filesystem-access.php)
- [Security Best Practices](https://owasp.org/www-project-php-top-5/)

---

**Version**: 1.0  
**Updated**: May 9, 2026  
**Status**: Deployed to Staging
