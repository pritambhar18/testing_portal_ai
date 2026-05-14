<?php
/**
 * debug_actual_test_execution.php
 * Test the actual test execution flow by calling run_test_report.php directly
 */

echo "<h2>Actual Test Execution Debug</h2>";

// Test data
$testUrl = 'https://httpbin.org/html'; // A simple test URL that returns HTML
$payload = json_encode(['url' => $testUrl]);

echo "<h3>Test Configuration</h3>";
echo "<p>Test URL: $testUrl</p>";
echo "<p>Payload: $payload</p>";

// Simulate the POST request to run_test_report.php
echo "<h3>Executing Test</h3>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/xampp/htdocs/testing_portal/actions/run_test_report.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Allow up to 60 seconds

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h3>Response Analysis</h3>";
echo "<p>HTTP Code: $httpCode</p>";

if ($curlError) {
    echo "<p style='color: red;'>CURL Error: $curlError</p>";
} else {
    echo "<p style='color: green;'>Request completed successfully</p>";

    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "<h4>Response Data:</h4>";
        echo "<pre>" . json_encode($responseData, JSON_PRETTY_PRINT) . "</pre>";

        if (isset($responseData['success']) && $responseData['success']) {
            echo "<p style='color: green;'>✅ Test execution reported success</p>";

            if (isset($responseData['report_id'])) {
                echo "<p style='color: green;'>✅ Report saved to database with ID: " . $responseData['report_id'] . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Report ID not returned - database save may have failed</p>";
            }

            if (isset($responseData['warning'])) {
                echo "<p style='color: orange;'>⚠️ Warning: " . $responseData['warning'] . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Test execution failed: " . ($responseData['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON response</p>";
        echo "<p>Raw response: <pre>$response</pre></p>";
    }
}

// Check database after execution
echo "<h3>Database Check</h3>";
require_once __DIR__ . '/../config/db.php';

if ($conn->connect_errno) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
} else {
    $countResult = $conn->query('SELECT COUNT(*) as count FROM test_reports');
    $count = $countResult->fetch_assoc()['count'];
    echo "<p>Total records in test_reports: $count</p>";

    if ($count > 0) {
        // Show the most recent record
        $recent = $conn->query('SELECT * FROM test_reports ORDER BY execution_date DESC LIMIT 1');
        $row = $recent->fetch_assoc();

        echo "<h4>Most Recent Record:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($row as $key => $value) {
            echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?: 'NULL') . "</td></tr>";
        }
        echo "</table>";
    }
}

$conn->close();
?>