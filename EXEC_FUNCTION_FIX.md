# PHP exec() Function - Error Resolution Guide

## Problem
You're encountering the error:
```
Order flow execution failed.
PHP exception: Call to undefined function exec()
```

This occurs when running tests in the "88startech" section of the Testing Portal.

## Root Cause
The PHP `exec()` function is **disabled** on your hosting environment. This function is required to:
- Execute Node.js scripts for order flow automation
- Generate test reports with PDF output
- Run automated functional checks

The `exec()` function is often disabled on shared hosting for security reasons, but your application requires it to function properly.

## Solution Options

### Option 1: Contact Your Hosting Provider ⭐ RECOMMENDED
Contact your cPanel hosting provider with this message:

---
**Email Template:**
```
Subject: Enable PHP exec() Function

Hello,

I need to enable the exec() PHP function on my account for my web application.

The application uses exec() to:
1. Run Node.js automation scripts for order flow testing
2. Generate PDF reports from test results
3. Execute functional automation checks

Please enable the following PHP functions:
- exec()
- shell_exec()
- passthru()

The application is located at: [your domain]

Thank you!
```
---

### Option 2: Switch to a Hosting Plan That Supports exec()
If your current hosting provider cannot enable these functions, consider switching to:
- **InfinityFree** (with shell access enabled)
- **Heroku**
- **DigitalOcean**
- **Linode**
- **AWS Lightsail**
- Any VPS or dedicated server hosting

### Option 3: Use Alternative Hosting Features
Some hosting providers offer:
- **Upgraded accounts** with exec() enabled
- **Shell access** that allows running commands

## Changes Made to Application

Both the main and deployment versions of `run_order_flow.php` have been updated with:

1. **Error Detection**: The application now checks if `exec()` is available before attempting to use it
2. **Better Error Messages**: Instead of a generic "undefined function" error, you'll get:
   - A clear message that `exec()` is disabled
   - Suggestions to contact your hosting provider
3. **Graceful Failure**: The application won't crash; it returns a proper JSON error response

**Files Updated:**
- `./actions/run_order_flow.php`
- `./_deploy_infinityfree/actions/run_order_flow.php`

## Testing the Fix

1. **Local Testing** (if you have XAMPP/WAMP setup):
   - The application should work fine locally
   - `exec()` is typically enabled in local PHP installations

2. **Staging Server**:
   - Try running a test in the "88startech" section
   - You should now get a clear error message about `exec()` being disabled
   - This message can be shown to your hosting provider for reference

## Verification Checklist

- [ ] You've attempted to run a test in "88startech"
- [ ] You see a clear error message about `exec()` being disabled
- [ ] You've contacted your hosting provider OR
- [ ] You're exploring alternative hosting options

## Server Check Tool

Use the built-in server check tool to verify your hosting environment:
- Navigate to `./server-check.php`
- Look for the "exec() Function" section
- This will tell you if the function is enabled or disabled

## Next Steps

1. **Contact your hosting provider** with the email template above
2. **Wait for them to enable the function**
3. **Test again** in the "88startech" section
4. If they cannot enable it, **switch to a different hosting provider**

## Additional Resources

- PHP exec() Documentation: https://www.php.net/manual/en/function.exec.php
- Common hosting providers comparison: Check your hosting provider's support for exec() in their documentation

---

**Status**: ✅ Error handling added to application  
**Action Required**: Contact hosting provider or switch providers  
**Impact**: Cannot run order flow tests until exec() is enabled
