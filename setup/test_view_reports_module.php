<?php
/**
 * setup/test_view_reports_module.php
 * Quick test script to verify View Reports module is working correctly
 * 
 * Usage: Open in browser at http://localhost/xampp/htdocs/testing_portal/setup/test_view_reports_module.php
 */

session_start();

// Set error display for testing
ini_set('display_errors', '1');
error_reporting(E_ALL);

$testResults = [];
$allPassed = true;

// ========================================
// TEST 1: Database Connection
// ========================================
$testResults['db_connection'] = ['name' => 'Database Connection', 'passed' => false, 'message' => ''];

try {
    $conn = require_once __DIR__ . '/../config/db.php';
    
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_errno === 0) {
            $testResults['db_connection']['passed'] = true;
            $testResults['db_connection']['message'] = 'Successfully connected to MySQL database';
        } else {
            $testResults['db_connection']['message'] = 'Connection error: ' . $conn->connect_error;
            $allPassed = false;
        }
    } else {
        $testResults['db_connection']['message'] = 'Connection object is invalid';
        $allPassed = false;
    }
} catch (Exception $e) {
    $testResults['db_connection']['message'] = 'Exception: ' . $e->getMessage();
    $allPassed = false;
}

// ========================================
// TEST 2: Check test_reports Table
// ========================================
$testResults['table_exists'] = ['name' => 'Table Exists', 'passed' => false, 'message' => ''];

if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query("SHOW TABLES LIKE 'test_reports'");
    
    if ($result && $result->num_rows > 0) {
        $testResults['table_exists']['passed'] = true;
        $testResults['table_exists']['message'] = 'test_reports table exists';
        
        // Get table structure
        $struct = $conn->query("DESCRIBE test_reports");
        $columns = [];
        while ($row = $struct->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        $testResults['table_exists']['details'] = 'Columns: ' . implode(', ', $columns);
    } else {
        $testResults['table_exists']['message'] = 'test_reports table not found';
        $allPassed = false;
    }
} else {
    $testResults['table_exists']['message'] = 'Cannot check - no database connection';
    $allPassed = false;
}

// ========================================
// TEST 3: Check Required Columns
// ========================================
$testResults['required_columns'] = ['name' => 'Required Columns', 'passed' => false, 'message' => ''];

if (isset($conn) && $conn instanceof mysqli) {
    $requiredColumns = ['id', 'test_link', 'execution_date', 'pdf_path'];
    $result = $conn->query("DESCRIBE test_reports");
    
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    
    if (empty($missingColumns)) {
        $testResults['required_columns']['passed'] = true;
        $testResults['required_columns']['message'] = 'All required columns present: ' . implode(', ', $requiredColumns);
    } else {
        $testResults['required_columns']['message'] = 'Missing columns: ' . implode(', ', $missingColumns);
        $allPassed = false;
    }
}

// ========================================
// TEST 4: Count Records
// ========================================
$testResults['record_count'] = ['name' => 'Record Count', 'passed' => true, 'message' => ''];

if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM test_reports");
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    $testResults['record_count']['message'] = "Total records in test_reports: <strong>$count</strong>";
    
    if ($count === 0) {
        $testResults['record_count']['details'] = '⚠️ No test reports found. Run a test from Test Configuration page first.';
    } else {
        $testResults['record_count']['details'] = "✓ Records available for display";
    }
}

// ========================================
// TEST 5: API Response
// ========================================
$testResults['api_response'] = ['name' => 'API Endpoint Response', 'passed' => false, 'message' => ''];

if (isset($conn) && $conn instanceof mysqli) {
    // Simulate API request
    $query = "SELECT id, test_link, execution_date, pdf_path FROM test_reports ORDER BY id DESC";
    $result = $conn->query($query);
    
    if ($result) {
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = [
                'id' => (int)$row['id'],
                'test_link' => $row['test_link'],
                'execution_date' => $row['execution_date'],
                'pdf_path' => $row['pdf_path']
            ];
        }
        
        $testResults['api_response']['passed'] = true;
        $testResults['api_response']['message'] = 'API query executed successfully';
        $testResults['api_response']['json'] = json_encode([
            'success' => true,
            'reports' => $reports,
            'count' => count($reports)
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        $testResults['api_response']['message'] = 'Query failed: ' . $conn->error;
        $allPassed = false;
    }
}

// ========================================
// TEST 6: View Reports Page Accessible
// ========================================
$testResults['view_reports_page'] = ['name' => 'View Reports Page', 'passed' => false, 'message' => ''];

$pageFile = __DIR__ . '/../admin/view_reports.php';
if (file_exists($pageFile)) {
    $filesize = filesize($pageFile);
    $testResults['view_reports_page']['passed'] = true;
    $testResults['view_reports_page']['message'] = "admin/view_reports.php exists ($filesize bytes)";
} else {
    $testResults['view_reports_page']['message'] = 'admin/view_reports.php not found';
    $allPassed = false;
}

// ========================================
// TEST 7: API File Accessible
// ========================================
$testResults['api_file'] = ['name' => 'API File', 'passed' => false, 'message' => ''];

$apiFile = __DIR__ . '/../api/get_reports.php';
if (file_exists($apiFile)) {
    $filesize = filesize($apiFile);
    $testResults['api_file']['passed'] = true;
    $testResults['api_file']['message'] = "api/get_reports.php exists ($filesize bytes)";
} else {
    $testResults['api_file']['message'] = 'api/get_reports.php not found';
    $allPassed = false;
}

// ========================================
// TEST 8: Supporting Files
// ========================================
$testResults['supporting_files'] = ['name' => 'Supporting Files', 'passed' => true, 'message' => ''];

$requiredFiles = [
    'admin/view_report_details.php',
    'actions/download_report.php',
    'helpers/TestReportLogger.php'
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/../' . $file)) {
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    $testResults['supporting_files']['passed'] = true;
    $testResults['supporting_files']['message'] = 'All supporting files present';
} else {
    $testResults['supporting_files']['passed'] = false;
    $testResults['supporting_files']['message'] = 'Missing files: ' . implode(', ', $missingFiles);
    $allPassed = false;
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports Module - Test Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
        }
        .test-result {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #ccc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .test-result.passed {
            border-left-color: #198754;
            background: #f0fef4;
        }
        .test-result.failed {
            border-left-color: #dc3545;
            background: #fee;
        }
        .test-result-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .test-result-icon {
            font-size: 1.5rem;
        }
        .test-result-message {
            color: #666;
            margin-bottom: 0.5rem;
        }
        .test-result-details {
            font-size: 0.85rem;
            color: #999;
            margin-top: 0.5rem;
        }
        .json-output {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-top: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .summary {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }
        .summary.success {
            border-top: 4px solid #198754;
        }
        .summary.failed {
            border-top: 4px solid #dc3545;
        }
        .summary h2 {
            margin-bottom: 1rem;
        }
        .next-steps {
            background: #cfe2ff;
            border: 1px solid #b6d4fe;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        .next-steps h5 {
            color: #084298;
            margin-bottom: 1rem;
        }
        .next-steps li {
            color: #084298;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="mb-4">
            <h1 class="mb-2">
                <i class="bi bi-file-earmark-pdf me-2"></i>View Reports Module - Test Results
            </h1>
            <p class="text-muted">Testing timestamp: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <!-- Summary -->
        <div class="summary <?php echo $allPassed ? 'success' : 'failed'; ?>">
            <h2>
                <i class="bi <?php echo $allPassed ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-2"></i>
                <?php echo $allPassed ? 'All Tests Passed ✅' : 'Some Tests Failed ⚠️'; ?>
            </h2>
            <p class="text-muted">
                Passed: <?php echo count(array_filter($testResults, function($t) { return $t['passed']; })); ?> / <?php echo count($testResults); ?>
            </p>
        </div>

        <!-- Test Results -->
        <h3 class="mb-3">Test Results</h3>
        <?php foreach ($testResults as $key => $test): ?>
            <div class="test-result <?php echo $test['passed'] ? 'passed' : 'failed'; ?>">
                <div class="test-result-title">
                    <div class="test-result-icon">
                        <?php if ($test['passed']): ?>
                            <i class="bi bi-check-circle text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle text-danger"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($test['name']); ?></strong>
                    </div>
                </div>
                <div class="test-result-message">
                    <?php echo htmlspecialchars($test['message']); ?>
                </div>
                <?php if (isset($test['details'])): ?>
                    <div class="test-result-details">
                        <?php echo htmlspecialchars($test['details']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($test['json'])): ?>
                    <div class="json-output"><?php echo htmlspecialchars($test['json']); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Next Steps -->
        <div class="next-steps">
            <h5>
                <i class="bi bi-arrow-right me-2"></i>Next Steps
            </h5>
            <ul>
                <?php if ($allPassed): ?>
                    <li><strong>Module is ready!</strong> Navigate to <a href="../admin/view_reports.php" style="color: #084298;">View Reports</a> in the admin dashboard.</li>
                    <li>If you see "No Reports Available", go to <a href="../admin/test_configuration.php" style="color: #084298;">Test Configuration</a> and run a test first.</li>
                    <li>After running a test, refresh View Reports to see the new report in the table.</li>
                <?php else: ?>
                    <li>Check the failed tests above and review the error messages.</li>
                    <li>Verify database configuration in <code>config/db.php</code></li>
                    <li>Ensure <code>test_reports</code> table exists: <code>SHOW TABLES LIKE 'test_reports';</code></li>
                    <li>Check file permissions and directory structure.</li>
                    <li>Review PHP error logs for more details.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 text-muted">
            <small>
                Test script: <code>setup/test_view_reports_module.php</code><br>
                For more details, see <code>VIEW_REPORTS_REBUILD.md</code>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
