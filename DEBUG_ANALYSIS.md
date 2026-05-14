# 🔍 View Reports Module - Debug Analysis & Fix

## **Root Cause Analysis**

### **The Problem**
Reports were not appearing in the "View Reports" section even after running tests successfully.

### **Why It Happened**
The test execution pipeline was broken at the database persistence layer:

```
Test Execution Flow:
┌─────────────────────────────────────────────────────────────┐
│ 1. Frontend (test_report.js)                               │
│    ✓ Sends POST request to run_test_report.php             │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│ 2. Backend (actions/run_test_report.php)                   │
│    ✓ Receives test URL                                     │
│    ✓ Calls generate_test_report()                          │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│ 3. Report Generation (reports/generate_report.php)         │
│    ✓ Fetches webpage content                               │
│    ✓ Analyzes SEO, SSL, Functionality                      │
│    ✓ Generates HTML report                                 │
│    ✓ Converts to PDF                                       │
│    ✓ Returns report data (success=true, pdf_path, etc.)    │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│ 4. [MISSING] Database Persistence                          │
│    ✗ NO CODE TO SAVE TO test_reports TABLE!               │
│    ✗ Report data not persisted to database                │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│ 5. View Reports (admin/view_reports.php)                   │
│    ✗ Calls api/get_reports.php                             │
│    ✗ Database is empty (no test_reports records)          │
│    ✗ Displays empty "No reports available" message         │
└─────────────────────────────────────────────────────────────┘
```

## **The Fix Applied**

### **File: actions/run_test_report.php**

**Before:** (Missing database integration)
```php
$result = generate_test_report($baseUrl, $pages, $testedBy);

if (!empty($result['success']) && $result['success'] === true) {
    send_json($result, 200);  // ✗ Just returns success, no DB saving
}
```

**After:** (Integrated TestReportLogger)
```php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/TestReportLogger.php';

$result = generate_test_report($baseUrl, $pages, $testedBy);

if (!empty($result['success']) && $result['success'] === true) {
    // ✓ NEW: Save report to database
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $reporter = new TestReportLogger($conn);
            
            $pdf_path = $result['report_html'] ?? $result['view_url'] ?? '';
            
            // Insert into test_reports table
            $report_id = $reporter->logTestReport($baseUrl, $pdf_path, 'Completed');
            
            if ($report_id) {
                $result['report_id'] = $report_id;
                $result['message'] = 'Test report generated and saved successfully.';
            }
        }
    } catch (Exception $e) {
        error_log("Error saving report: " . $e->getMessage());
    }
    
    send_json($result, 200);  // ✓ Now includes database save
}
```

## **What This Fix Does**

1. **Loads Database Connection** - Requires `config/db.php` to get MySQLi connection
2. **Loads TestReportLogger** - Requires `helpers/TestReportLogger.php` helper class
3. **After Report Generation** - Extracts PDF path from generated report
4. **Saves to Database** - Calls `logTestReport()` to insert into `test_reports` table with:
   - `test_link` - The URL that was tested
   - `pdf_path` - Path to generated report
   - `status` - Set to 'Completed' after successful generation
   - `execution_date` - Automatically set to NOW()
   - `created_at` - Automatically set to NOW()
5. **Error Handling** - Gracefully handles DB errors without failing the test

## **Database Schema**

The `test_reports` table structure:

```sql
CREATE TABLE test_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_link VARCHAR(500) NOT NULL,
    execution_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    pdf_path VARCHAR(500),
    status VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_execution_date (execution_date),
    INDEX idx_test_link (test_link),
    INDEX idx_status (status)
);
```

## **Verification Steps**

### **1. Setup Database**
```
Visit: http://localhost/xampp/htdocs/testing_portal/setup/create_test_reports_table.php
```
Should show: "✓ test_reports table created successfully!"

### **2. Run Diagnostics**
```
Visit: http://localhost/xampp/htdocs/testing_portal/setup/verify_and_fix.php
```
Should show all checks passing

### **3. Test Complete Flow**
1. Go to: `http://localhost/xampp/htdocs/testing_portal/admin/test_configuration.php`
2. Enter a test URL (e.g., `https://www.google.com`)
3. Click "Test" button
4. Wait 30-60 seconds for report generation
5. Go to: `http://localhost/xampp/htdocs/testing_portal/admin/view_reports.php`
6. Should see your test in the table with status "Completed"

## **API Flow After Fix**

### **Step 1: User Runs Test**
```
POST /actions/run_test_report.php
Body: { "url": "https://example.com" }
```

### **Step 2: Test Report Generated**
- Creates HTML report
- Converts to PDF
- Saves files to disk

### **Step 3: Report Saved to Database** (NEW)
```php
INSERT INTO test_reports (test_link, execution_date, pdf_path, status, created_at)
VALUES ('https://example.com', NOW(), '../reports/generated/test_report_20260315_120000.html', 'Completed', NOW());
```

### **Step 4: View Reports Fetches Data**
```
GET /api/get_reports.php
Returns: [
    {
        "id": 1,
        "test_link": "https://example.com",
        "execution_date": "2026-03-15 12:00:00",
        "pdf_path": "../reports/generated/test_report_20260315_120000.html",
        "status": "Completed"
    }
]
```

### **Step 5: Frontend Displays Reports**
View Reports page fetches from API and displays in table with status badge

## **Troubleshooting**

### **Reports Still Not Appearing?**

**Issue:** Database connection error
- Check `config/db.php` credentials
- Verify MySQL is running
- Test connection via `verify_and_fix.php`

**Issue:** `test_reports` table doesn't exist
- Run `create_test_reports_table.php`
- Verify table with `DESCRIBE test_reports;`

**Issue:** Reports generated but not saved to DB
- Check PHP error logs
- Run diagnostics: `diagnose_reports.php`
- Verify TestReportLogger.php exists

**Issue:** API returns empty array
- Check database has records: `SELECT COUNT(*) FROM test_reports;`
- Verify API endpoint: `api/get_reports.php`
- Check frontend console for fetch errors

## **Testing with Sample Data**

To test without running actual tests:

```
Visit: http://localhost/xampp/htdocs/testing_portal/setup/insert_test_reports.php
```

This inserts 5 sample reports that will appear in View Reports page.

## **Code Architecture**

### **TestReportLogger Class**
Location: `helpers/TestReportLogger.php`

Key methods:
- `logTestReport($link, $pdf_path, $status)` - Insert new report
- `updateReportStatus($id, $status)` - Update status
- `getRecentReports($limit)` - Fetch recent reports
- `getReportsByLink($link)` - Fetch by URL

### **API Endpoints**
- `api/get_reports.php` - GET all reports (JSON)
- `actions/run_test_report.php` - POST to run test
- `actions/download_report.php` - GET to download PDF

### **Frontend Pages**
- `admin/test_configuration.php` - Form to input test URLs
- `admin/view_reports.php` - Display all reports in table
- `admin/view_report_details.php` - Show single report details

## **Performance Considerations**

- Database queries are optimized with indexes
- Report generation happens asynchronously
- API limits results to 1000 reports
- Consider pagination for large datasets (>10,000 reports)

## **Security Features**

- Prepared statements prevent SQL injection
- Session authentication required on all pages
- PDF download validates file path (prevents directory traversal)
- Input sanitization on all user inputs

## **Next Steps**

1. Run setup scripts to initialize database
2. Test the complete flow
3. Monitor error logs for any issues
4. Configure automated test scheduling (future enhancement)

---

**Issue Fixed:** Reports now automatically save to database after test execution and appear in View Reports page.
