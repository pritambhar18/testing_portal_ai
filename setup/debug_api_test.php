<?php
/**
 * debug_api_test.php
 * Test the API endpoint directly
 */

echo "<h2>API Endpoint Test</h2>";

// Test the API endpoint directly
$apiUrl = 'http://localhost/xampp/htdocs/testing_portal/api/get_reports.php';

echo "<h3>Testing API Endpoint</h3>";
echo "<p>URL: $apiUrl</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

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
            echo "<p style='color: green;'>✅ API returned success</p>";
            echo "<p>Number of reports: " . ($responseData['count'] ?? 0) . "</p>";

            if (isset($responseData['reports']) && is_array($responseData['reports'])) {
                echo "<h4>Report Records:</h4>";
                foreach ($responseData['reports'] as $report) {
                    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
                    echo "<strong>ID:</strong> " . ($report['id'] ?? 'N/A') . "<br>";
                    echo "<strong>Test Link:</strong> " . htmlspecialchars(substr($report['test_link'] ?? '', 0, 50)) . "...<br>";
                    echo "<strong>Execution Date:</strong> " . ($report['execution_date'] ?? 'N/A') . "<br>";
                    echo "<strong>Status:</strong> " . ($report['status'] ?? 'N/A') . "<br>";
                    echo "<strong>PDF Path:</strong> " . htmlspecialchars($report['pdf_path'] ?? 'N/A') . "<br>";
                    echo "</div>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ API returned error: " . ($responseData['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON response</p>";
        echo "<p>Raw response: <pre>$response</pre></p>";
    }
}
?>