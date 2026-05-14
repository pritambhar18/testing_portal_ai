<?php
/**
 * debug_test_insertion.php
 * Test script to manually insert a test report and verify it works
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/TestReportLogger.php';

echo "<h2>Test Report Database Insertion</h2>";

// Test database connection
if ($conn->connect_errno) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connection successful.</p>";

// Test TestReportLogger
try {
    $logger = new TestReportLogger($conn);

    // Test data
    $testUrl = 'https://example.com/test-' . time();
    $pdfPath = 'reports/test_report_' . time() . '.html';
    $status = 'Completed';

    echo "<h3>Testing Manual Insertion</h3>";
    echo "<p>Test URL: $testUrl</p>";
    echo "<p>PDF Path: $pdfPath</p>";
    echo "<p>Status: $status</p>";

    $reportId = $logger->logTestReport($testUrl, $pdfPath, $status);

    if ($reportId) {
        echo "<p style='color: green;'>✅ Report inserted successfully with ID: $reportId</p>";

        // Verify the record exists
        $stmt = $conn->prepare("SELECT * FROM test_reports WHERE id = ?");
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            echo "<h3>Inserted Record Details:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($row as $key => $value) {
                echo "<tr><td>$key</td><td>" . htmlspecialchars($value) . "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>❌ Could not retrieve inserted record</p>";
        }
        $stmt->close();

    } else {
        echo "<p style='color: red;'>❌ Report insertion failed</p>";
        echo "<p>Last MySQL error: " . $conn->error . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception during insertion: " . $e->getMessage() . "</p>";
}

// Show total records
$countResult = $conn->query('SELECT COUNT(*) as count FROM test_reports');
$count = $countResult->fetch_assoc()['count'];
echo "<p><strong>Total records in test_reports table:</strong> $count</p>";

$conn->close();
?>