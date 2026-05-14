# View Reports Module - Deployment & Verification Guide

**Last Updated**: March 15, 2026  
**Module Status**: ✅ Ready for Production

---

## 📋 Pre-Deployment Checklist

### 1. System Requirements
- [ ] PHP 7.4 or higher installed
- [ ] MySQL 5.7 or higher running
- [ ] Apache/web server configured
- [ ] testing_portal database created
- [ ] test_reports table exists with correct schema

### 2. Configuration Check
```php
// config/db.php should have:
$db_host = 'localhost';      ✓
$db_user = 'root';           ✓
$db_pass = '';               ✓
$db_name = 'testing_portal'; ✓
```

### 3. File System Setup
```
testing_portal/
├── admin/
│   ├── view_reports.php           ✓ MUST exist
│   └── view_report_details.php    ✓ MUST exist
├── api/
│   └── get_reports.php            ✓ MUST exist
├── actions/
│   └── download_report.php        ✓ MUST exist
├── config/
│   └── db.php                      ✓ MUST exist
├── reports/
│   └── generated/                 ✓ MUST be writable
└── helpers/
    └── TestReportLogger.php       ✓ USED by test runner
```

### 4. Directory Permissions
```bash
# On Linux/Unix servers:
chmod 755 admin/
chmod 755 api/
chmod 755 actions/
chmod 755 reports/
chmod 777 reports/generated/  # Must be writable
```

### 5. Database Verification
```sql
-- Login to MySQL and verify:
USE testing_portal;

-- Check table exists
SHOW TABLES LIKE 'test_reports';

-- Check structure
DESCRIBE test_reports;

-- Should show:
-- +----------------+-----------+------+-----+---------+----------------+
-- | Field          | Type      | Null | Key | Default | Extra          |
-- +----------------+-----------+------+-----+---------+----------------+
-- | id             | int       | NO   | PRI | NULL    | auto_increment |
-- | test_link      | text      | NO   |     | NULL    |                |
-- | execution_date | datetime  | NO   |     | NULL    |                |
-- | pdf_path       | varchar   | YES  |     | NULL    |                |
-- | created_at     | timestamp | YES  |     | CURR... |                |
-- +----------------+-----------+------+-----+---------+----------------+
```

---

## 🚀 Step-by-Step Deployment

### Step 1: Run Diagnostic Tests
```
1. Open browser: http://localhost/xampp/htdocs/testing_portal/setup/test_view_reports_module.php
2. Verify all tests pass (green checkmarks)
3. Note any failures and fix them before proceeding
```

**Expected Results**:
- ✓ Database Connection
- ✓ Table Exists
- ✓ Required Columns
- ✓ Record Count
- ✓ API Response
- ✓ View Reports Page
- ✓ API File
- ✓ Supporting Files

### Step 2: Insert Sample Data (Optional)
```
1. Open: http://localhost/xampp/htdocs/testing_portal/setup/insert_sample_reports.php
2. Click "Insert Sample Reports"
3. Verify success message
```

This creates 5 test reports for immediate testing.

### Step 3: Access View Reports
```
1. Login to admin dashboard
2. Navigate to: View Reports (sidebar menu)
3. Should see table with sample data (if Step 2 completed)
4. Or "No Reports Available" (if starting fresh)
```

### Step 4: Test Core Features
```
Feature 1: View Report Details
- Click eye icon on any report
- Should open details page
- Verify all fields display correctly

Feature 2: Download Report
- Click download icon
- Should download PDF file to computer
- File name: test_report_<id>_<date>.pdf

Feature 3: Navigate Back
- Click "Back to Reports" button
- Should return to reports list
```

### Step 5: Test Integration with Test Runner
```
1. Go to Test Configuration page
2. Enter test URL (e.g., https://example.com)
3. Click "Run Test"
4. Wait for test to complete
5. Go to View Reports
6. Verify new report appears in table
7. Click icons to verify they work
```

---

## ✅ Verification Tests

### Test A: Database Connectivity
```bash
curl -s http://localhost/xampp/htdocs/testing_portal/setup/test_view_reports_module.php | grep -i "database connection"

# Should show: ✓ Database Connection - green checkmark
```

### Test B: API Response
```bash
curl http://localhost/xampp/htdocs/testing_portal/api/get_reports.php

# Should return JSON like:
# {"success":true,"reports":[...],"count":N}
```

### Test C: Page Load
```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost/xampp/htdocs/testing_portal/admin/view_reports.php

# Should return: 200
```

### Test D: Error Handling
```
1. Temporarily stop MySQL service
2. Reload View Reports page
3. Should show error alert but not crash
4. Restart MySQL
5. Page should still be functional
```

---

## 🆘 Troubleshooting Guide

### Issue: "Database connection failed"
**Diagnosis**:
```bash
# Check if MySQL is running
ps aux | grep mysql

# Or in Windows:
tasklist | findstr mysql
```

**Solution**:
1. Start MySQL service (XAMPP control panel)
2. Verify credentials in config/db.php
3. Test with: mysql -u root -p testing_portal
4. Reload View Reports page

**Prevention**: Set MySQL to auto-start

---

### Issue: "test_reports table not found"
**Diagnosis**:
```sql
SHOW TABLES LIKE 'test_reports';
-- Returns: Empty set (0 rows)
```

**Solution**:
1. Create table manually using:
```sql
USE testing_portal;
CREATE TABLE test_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_link TEXT NOT NULL,
    execution_date DATETIME NOT NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

2. Or run: setup/create_test_reports_table.php

**Prevention**: Run setup once at initial deployment

---

### Issue: "No Reports Available" (but records exist)
**Diagnosis**:
```sql
SELECT COUNT(*) FROM test_reports;
-- Returns: 5 (but UI shows empty)
```

**Solution**:
1. Check browser console for JavaScript errors (F12)
2. Check network tab - did API call succeed?
3. Verify api/get_reports.php returns proper JSON
4. Test: curl http://localhost/.../api/get_reports.php

**Debug Step**:
```javascript
// In browser console:
fetch('../api/get_reports.php')
  .then(r => r.json())
  .then(d => console.log(d))
```

---

### Issue: Download button not working
**Diagnosis**:
- Click download icon
- Nothing happens or error appears

**Solution**:
1. Check if PDF file exists:
```bash
ls -la reports/generated/
# Check if file has correct permissions (644 or 755)
```

2. Verify path in database:
```sql
SELECT pdf_path FROM test_reports WHERE id = 1;
```

3. Test download directly:
```
http://localhost/.../actions/download_report.php?id=1
```

**Prevention**:
- Ensure reports/generated/ is writable
- Check file permissions after test execution

---

### Issue: Page loads slow
**Diagnosis**:
- Measure page load time (should be <2 seconds)

**Solution**:
1. Check database response time:
```sql
SELECT COUNT(*) FROM test_reports;  -- Should be <5ms
```

2. Reduce number of reports (future: add pagination)
3. Check server CPU/memory usage
4. Verify network connectivity

---

### Issue: API returns error instead of data
**Diagnosis**:
```bash
curl http://localhost/.../api/get_reports.php
# Returns: {"success":false,"error":"Query failed"}
```

**Solution**:
1. Check PHP error logs:
```bash
tail -f /var/log/php-errors.log
# Or check: logs/php-errors.log in project
```

2. Verify SQL query directly:
```sql
SELECT id, test_link, execution_date, pdf_path FROM test_reports;
```

3. Check database user permissions:
```sql
GRANT ALL PRIVILEGES ON testing_portal.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

---

## 📊 Performance Verification

### Expected Performance
```
Metric              | Expected  | Acceptable
────────────────────┼───────────┼──────────
Page load           | <1 sec    | <2 sec
API response        | <50 ms    | <200 ms
Database query      | <5 ms     | <50 ms
PDF download start  | <1 sec    | <3 sec
────────────────────┴───────────┴──────────
```

### Performance Testing
```bash
# Measure API response time:
time curl http://localhost/.../api/get_reports.php > /dev/null

# Should show: real 0m0.05s (50ms) or faster

# Load test with sample data:
1. Insert 1000 sample records
2. Reload View Reports
3. Should still load <2 seconds
4. If slower, add pagination feature (future enhancement)
```

---

## 🔒 Security Verification

### SQL Injection Test
```
URL: admin/view_report_details.php?id=1' OR '1'='1
Expected: Should NOT work (prepared statements prevent it)
Result: Either shows valid report or "Report not found"
Status: ✓ PASS if safe
```

### Path Traversal Test
```
URL: actions/download_report.php?id=1
Modify php_path in DB to: ../../../../etc/passwd
Expected: Should NOT work (path validation prevents it)
Result: "Access denied" error message
Status: ✓ PASS if safe
```

### XSS Test
```
URL: Insert report with test_link = "<img src=x onerror=alert('XSS')>"
Expected: Should NOT execute JavaScript
Result: Link displayed as text or escaped HTML
Status: ✓ PASS if safe
```

### Session Test
```
1. Logout from admin
2. Try to access: admin/view_reports.php directly
Expected: Should redirect to login page
Result: Redirected to login.php
Status: ✓ PASS if secure
```

---

## 🔄 Continuous Monitoring

### Daily Checks
```
☐ View Reports page loads
☐ At least one report displays
☐ Download button works
☐ No error messages in console
☐ All navigation links work
```

### Weekly Checks
```
☐ Run diagnostic tool
☐ Verify database backups exist
☐ Check server error logs
☐ Monitor storage space (reports/generated/)
☐ Test with increasing number of reports
```

### Monthly Checks
```
☐ Full system test (all features)
☐ Performance baseline check
☐ Security scan (SQL injection, XSS, etc.)
☐ Database optimization
☐ Clean up old reports (optional)
```

---

## 📞 Emergency Recovery

### If Everything Fails
```
1. Check database connection:
   mysql -u root testing_portal
   SELECT 1;  -- Should return 1

2. Verify table exists:
   SHOW TABLES LIKE 'test_reports';

3. Check file permissions:
   ls -la reports/generated/

4. Review error logs:
   tail -f logs/php-errors.log

5. Run diagnostics:
   http://localhost/.../setup/test_view_reports_module.php

6. If still failing: Restore from backup
   mysql testing_portal < backup.sql
```

---

## 🎓 Post-Deployment Training

### For Administrators
- How to navigate View Reports page
- How to download PDF reports
- How to troubleshoot "no reports" issue
- When to contact developer

### For Developers
- Module architecture (see ARCHITECTURE.md)
- API endpoint documentation
- Database schema
- Common issues and solutions
- How to extend functionality

### For DevOps
- Backup strategy for test_reports table
- Monitoring setup
- Performance baseline
- Disaster recovery plan

---

## 📁 Documentation Reference

| Document | Purpose |
|----------|---------|
| QUICK_REFERENCE.md | Quick start guide |
| VIEW_REPORTS_REBUILD.md | Detailed rebuild info |
| ARCHITECTURE.md | System design |
| RESTRUCTURE_SUMMARY.md | What was changed |
| This file | Deployment & verification |

---

## ✅ Final Sign-Off

```
Deployment Completed: ___________
Verified By: ___________
Date: ___________

All tests passing: [ ]
All features working: [ ]
Documentation complete: [ ]
Team trained: [ ]
Ready for production: [ ]
```

---

## 🎯 Next Steps

### Immediate (Today)
- [ ] Run diagnostic tests
- [ ] Insert sample data if needed
- [ ] Test all features
- [ ] Verify error handling

### Short-term (This Week)
- [ ] Run real tests and verify reports appear
- [ ] Monitor performance
- [ ] Gather user feedback
- [ ] Document any issues

### Long-term (This Month)
- [ ] Add pagination (if 1000+ reports)
- [ ] Add search/filter functionality
- [ ] Add report export feature
- [ ] Set up automated backups

---

## 💡 Tips & Best Practices

### Best Practices
1. **Regular Backups**: Backup test_reports table weekly
2. **Monitor Growth**: Check reports/generated/ disk usage
3. **Performance**: Add pagination when > 1000 reports
4. **Security**: Run security scan monthly
5. **Logs**: Review error logs weekly

### Quick Wins (Easy Enhancements)
- Add report count to sidebar
- Show "time since test execution"
- Add bulk download feature
- Add email notifications

### Future Roadmap
- Advanced filtering and search
- Report comparison feature
- Automated cleanup of old reports
- PDF annotation/markup tools
- Report scheduling

---

*Built with ❤️ for reliable, maintainable testing infrastructure*

**Status**: ✅ PRODUCTION READY  
**Last Verified**: March 15, 2026  
**Next Review**: April 15, 2026

