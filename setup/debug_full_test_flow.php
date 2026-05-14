<?php
/**
 * debug_full_test_flow.php
 * Simulate the full test execution flow to identify where the database save fails
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/TestReportLogger.php';
require_once __DIR__ . '/../helpers/url_helper.php';

echo "<h2>Full Test Execution Flow Debug</h2>";

// Step 1: Database connection
echo "<h3>Step 1: Database Connection</h3>";
if ($conn->connect_errno) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    exit;
}
echo "<p style='color: green;'>✅ Database connection successful.</p>";

// Step 2: TestReportLogger instantiation
echo "<h3>Step 2: TestReportLogger Instantiation</h3>";
try {
    $logger = new TestReportLogger($conn);
    echo "<p style='color: green;'>✅ TestReportLogger instantiated successfully.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ TestReportLogger instantiation failed: " . $e->getMessage() . "</p>";
    exit;
}

// Step 3: Simulate report generation success
echo "<h3>Step 3: Simulate Report Generation</h3>";
$testUrl = 'https://example.com/debug-test-' . time();
$mockResult = [
    'success' => true,
    'report_html' => 'reports/debug_test_' . time() . '.html',
    'view_url' => 'reports/debug_test_' . time() . '.html',
    'download_url' => 'reports/debug_test_' . time() . '.pdf',
    'message' => 'Mock report generated successfully'
];

echo "<p>Test URL: $testUrl</p>";
echo "<p>Report HTML: " . $mockResult['report_html'] . "</p>";
echo "<p style='color: green;'>✅ Report generation simulated successfully.</p>";

// Step 4: Database save (the critical step)
echo "<h3>Step 4: Database Save</h3>";
try {
    $pdf_path = $mockResult['report_html'] ?? '';
    if (empty($pdf_path) && !empty($mockResult['view_url'])) {
        $pdf_path = $mockResult['view_url'];
    }

    echo "<p>Attempting to save with:</p>";
    echo "<ul>";
    echo "<li>Test Link: $testUrl</li>";
    echo "<li>PDF Path: $pdf_path</li>";
    echo "<li>Status: Completed</li>";
    echo "</ul>";

    $report_id = $logger->logTestReport($testUrl, $pdf_path, 'Completed');

    if ($report_id) {
        echo "<p style='color: green;'>✅ Report saved to database with ID: $report_id</p>";

        // Verify the record
        $stmt = $conn->prepare("SELECT * FROM test_reports WHERE id = ?");
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            echo "<h4>Saved Record:</h4>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($row as $key => $value) {
                echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?: 'NULL') . "</td></tr>";
            }
            echo "</table>";
        }

    } else {
        echo "<p style='color: red;'>❌ Database save failed</p>";
        echo "<p>MySQL Error: " . $conn->error . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception during database save: " . $e->getMessage() . "</p>";
}

// Step 5: Check API endpoint
echo "<h3>Step 5: API Endpoint Test</h3>";
try {
    $apiUrl = 'http://localhost/xampp/htdocs/testing_portal/api/get_reports.php';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json',
            'timeout' => 10
        ]
    ]);

    $apiResponse = file_get_contents($apiUrl, false, $context);
    if ($apiResponse === false) {
        echo "<p style='color: red;'>❌ API endpoint unreachable</p>";
    } else {
        $apiData = json_decode($apiResponse, true);
        if ($apiData && isset($apiData['success']) && $apiData['success']) {
            echo "<p style='color: green;'>✅ API endpoint working. Found " . $apiData['count'] . " reports.</p>";
        } else {
            echo "<p style='color: red;'>❌ API endpoint returned error: " . ($apiData['error'] ?? 'Unknown error') . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ API test failed: " . $e->getMessage() . "</p>";
}

// Step 6: Summary
echo "<h3>Step 6: Summary</h3>";
$countResult = $conn->query('SELECT COUNT(*) as count FROM test_reports');
$count = $countResult->fetch_assoc()['count'];
echo "<p><strong>Total records in database:</strong> $count</p>";

if ($count > 0) {
    echo "<p style='color: green;'>✅ Database persistence is working correctly.</p>";
} else {
    echo "<p style='color: red;'>❌ No records found - database persistence is NOT working.</p>";
}

$conn->close();
?>