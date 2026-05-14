<?php
/**
 * INTEGRATION_GUIDE.md - View Reports Module Integration
 * 
 * This file explains how to integrate the View Reports module with your existing
 * test execution system to automatically populate reports.
 */
?>

# Integration Guide: View Reports Module

## Overview

The View Reports module is designed to work with your existing test execution system. Reports are automatically logged when tests are executed.

## Integration Points

### 1. Using TestReportLogger in Your Test Execution Code

The `TestReportLogger` class provides a simple interface to log test reports:

```php
<?php
// In your test execution handler (update run_test_report.php or similar)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/TestReportLogger.php';

// Initialize the logger
$reporter = new TestReportLogger($conn);

// When you complete a test and have a PDF path:
$test_link = 'https://www.example.com';
$pdf_path = 'reports/test_report_20260315_100000.pdf';

// Log the report
$report_id = $reporter->logTestReport($test_link, $pdf_path);

if ($report_id) {
    echo "Report logged with ID: " . $report_id;
} else {
    echo "Failed to log report";
}
?>
```

### 2. Integration with Existing Test Flow

If your test execution follows this pattern:

```php
<?php
// 1. Validate input
// 2. Create PDF report
// 3. Save to disk
// 4. Store path in database
// 5. Return response
?>
```

Add this at step 4:

```php
<?php
// Step 4: Store paths and log report
$pdf_path_relative = 'reports/test_report_' . $timestamp . '.pdf';

// Save to main reports table (if you have one)
// ... your existing code ...

// Log to test_reports table for View Reports module
$reporter = new TestReportLogger($conn);
$report_id = $reporter->logTestReport($test_url, $pdf_path_relative);

if (!$report_id) {
    // Log error but don't fail the test execution
    error_log("Failed to log test report for: " . $test_url);
}
?>
```

### 3. Batch Test Execution

If you execute multiple test links from a single request:

```php
<?php
require_once __DIR__ . '/../helpers/TestReportLogger.php';

$reporter = new TestReportLogger($conn);
$test_links = ['https://example.com', 'https://example.org', 'https://example.net'];

foreach ($test_links as $link) {
    // Execute test, generate PDF...
    $pdf_path = generateTestReport($link);
    
    // Log the report
    $report_id = $reporter->logTestReport($link, $pdf_path);
    
    // Track results
    $results[] = [
        'link' => $link,
        'report_id' => $report_id,
        'pdf_path' => $pdf_path
    ];
}

// Return results to client
return json_encode(['results' => $results]);
?>
```

## TestReportLogger API Reference

### Methods Available

#### `logTestReport($test_link, $pdf_path = null)`
Inserts a new test report entry.
- **Parameters**:
  - `$test_link` (string): The URL that was tested
  - `$pdf_path` (string, optional): Relative path to the PDF report
- **Returns**: Report ID on success, false on failure
- **Example**:
```php
$report_id = $reporter->logTestReport('https://example.com', 'reports/report_001.pdf');
```

#### `updateReportPdfPath($report_id, $pdf_path)`
Updates the PDF path for an existing report.
- **Parameters**:
  - `$report_id` (int): The report ID to update
  - `$pdf_path` (string): New PDF path
- **Returns**: true on success, false on failure
- **Example**:
```php
$reporter->updateReportPdfPath(5, 'reports/test_report_final.pdf');
```

#### `getRecentReports($limit = 10)`
Retrieves recent test reports.
- **Parameters**:
  - `$limit` (int): Number of recent reports to fetch (default: 10)
- **Returns**: Array of report records
- **Example**:
```php
$recent = $reporter->getRecentReports(5);
foreach ($recent as $report) {
    echo $report['test_link'];
}
```

#### `getReportsByLink($test_link, $limit = 100)`
Retrieves all reports for a specific test link.
- **Parameters**:
  - `$test_link` (string): The test link to search for
  - `$limit` (int): Maximum results to return (default: 100)
- **Returns**: Array of report records
- **Example**:
```php
$reports = $reporter->getReportsByLink('https://example.com');
```

#### `getReportCount()`
Gets the total number of reports.
- **Returns**: Integer count
- **Example**:
```php
$total = $reporter->getReportCount();
echo "Total reports: " . $total;
```

#### `deleteOldReports($days = 30)`
Deletes old test reports (cleanup function).
- **Parameters**:
  - `$days` (int): Delete reports older than this many days (default: 30)
- **Returns**: Number of reports deleted
- **Example**:
```php
$deleted = $reporter->deleteOldReports(30);
echo "Deleted " . $deleted . " old reports";
```

## Complete Integration Example

Here's a complete example showing how to integrate with a test execution handler:

```php
<?php
// actions/run_test_with_reporting.php

session_start();

if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/TestReportLogger.php';

try {
    // Get test links from request
    $test_links = isset($_POST['test_links']) ? 
        array_filter(array_map('trim', (array)$_POST['test_links']), 'strlen') : 
        [];

    if (empty($test_links)) {
        throw new Exception('No test links provided');
    }

    // Initialize report logger
    $reporter = new TestReportLogger($conn);
    $results = [];

    // Execute tests
    foreach ($test_links as $test_link) {
        try {
            // Validate URL
            if (!filter_var($test_link, FILTER_VALIDATE_URL)) {
                $results[] = [
                    'link' => $test_link,
                    'success' => false,
                    'error' => 'Invalid URL format'
                ];
                continue;
            }

            // Generate test report (your existing logic)
            $pdf_path = generateTestReportPDF($test_link);

            if ($pdf_path) {
                // Log to test_reports table
                $report_id = $reporter->logTestReport($test_link, $pdf_path);

                $results[] = [
                    'link' => $test_link,
                    'success' => true,
                    'report_id' => $report_id,
                    'pdf_path' => $pdf_path
                ];
            } else {
                $results[] = [
                    'link' => $test_link,
                    'success' => false,
                    'error' => 'PDF generation failed'
                ];
            }

        } catch (Exception $e) {
            $results[] = [
                'link' => $test_link,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Return results
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => count($results),
        'report_count' => $reporter->getReportCount()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
```

## Database Queries

If you need to manually query reports, here are useful SQL patterns:

### Get all reports for a specific link:
```sql
SELECT * FROM test_reports 
WHERE test_link = 'https://example.com' 
ORDER BY execution_date DESC;
```

### Get reports from last 7 days:
```sql
SELECT * FROM test_reports 
WHERE execution_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
ORDER BY execution_date DESC;
```

### Count reports by link:
```sql
SELECT test_link, COUNT(*) as count 
FROM test_reports 
GROUP BY test_link 
ORDER BY count DESC;
```

### Get latest report for each link:
```sql
SELECT DISTINCT ON (test_link) * FROM test_reports 
ORDER BY test_link, execution_date DESC;
```

## Error Handling

TestReportLogger methods return false on error. Always check the return value:

```php
$report_id = $reporter->logTestReport($test_link, $pdf_path);

if ($report_id === false) {
    // Handle error
    error_log("Failed to log test report");
    // Continue execution, don't fail the overall test
} else {
    // Log successful report ID
    echo "Report logged: " . $report_id;
}
```

## Cleanup

To remove old reports periodically, you can:

1. **Manual cleanup via script**:
```php
$deleted = $reporter->deleteOldReports(30); // Delete reports older than 30 days
echo "Cleaned up " . $deleted . " old reports";
```

2. **Automated cleanup via cron job**:
```bash
*/1 * * * * php /path/to/testing_portal/helpers/cleanup_old_reports.php
```

3. **Call from test execution**:
```php
// After running tests, cleanup old reports
if (rand(1, 100) <= 5) { // Run cleanup 5% of the time
    $reporter->deleteOldReports(30);
}
```

## Testing the Integration

To verify the integration works:

1. **Check database has test_reports table**:
```php
// Run setup/create_test_reports_table.php
```

2. **Insert sample reports**:
```php
// Run setup/insert_test_reports.php
```

3. **Test the API**:
```
GET http://localhost/xampp/htdocs/testing_portal/api/get_reports.php
```

4. **View in UI**:
```
http://localhost/xampp/htdocs/testing_portal/admin/view_reports.php
```

## Troubleshooting Integration

### Reports not appearing in View Reports page
1. Verify `test_reports` table exists: `DESCRIBE test_reports;`
2. Check that `logTestReport()` is being called
3. Verify database connection is active
4. Check for PHP errors in logs

### logTestReport() returns false
1. Verify test link is not empty
2. Check database connection
3. Review MySQL error logs
4. Verify table has correct schema

### PDF download fails
1. Verify PDF file exists at the path
2. Check file permissions (755 recommended)
3. Ensure pdf_path is relative to project root
4. Check download_report.php error handling

## Performance Considerations

- TestReportLogger uses prepared statements (safe)
- Indexes on execution_date and test_link for fast queries
- Consider archiving reports older than 90 days
- Monitor test_reports table size (consider partitioning for >100k records)
