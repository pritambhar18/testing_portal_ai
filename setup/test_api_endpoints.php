<?php
/**
 * setup/test_api_endpoints.php
 * Test script to verify API endpoints are working correctly
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Endpoint Tester</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .test-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .test-header { font-size: 1.1em; font-weight: bold; margin-bottom: 15px; }
        .test-button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .test-button:hover { background: #0056b3; }
        .result { margin: 15px 0; padding: 15px; border-left: 4px solid #ccc; border-radius: 4px; background: #f9f9f9; }
        .result.success { border-color: #28a745; background: #d4edda; }
        .result.error { border-color: #dc3545; background: #f8d7da; }
        .result.info { border-color: #17a2b8; background: #d1ecf1; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow: auto; max-height: 300px; }
        .json { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔌 API Endpoint Tester</h1>
    <p>Use this tool to verify that the API endpoints are working correctly.</p>

    <!-- Test 1: GET /api/get_reports.php -->
    <div class="test-section">
        <div class="test-header">Test 1: Fetch Reports (GET /api/get_reports.php)</div>
        <p>This endpoint should return all reports from the database.</p>
        <button class="test-button" onclick="testGetReports()">Test GET Reports API</button>
        <div id="test1-result"></div>
    </div>

    <!-- Test 2: Database Connection Test -->
    <div class="test-section">
        <div class="test-header">Test 2: Database Connection</div>
        <p>Check if database is properly connected.</p>
        <button class="test-button" onclick="testDatabaseConnection()">Test Database Connection</button>
        <div id="test2-result"></div>
    </div>

    <!-- Test 3: Table Structure Test -->
    <div class="test-section">
        <div class="test-header">Test 3: Table Structure</div>
        <p>Verify test_reports table has correct columns.</p>
        <button class="test-button" onclick="testTableStructure()">Test Table Structure</button>
        <div id="test3-result"></div>
    </div>

</div>

<script>
async function testGetReports() {
    const resultDiv = document.getElementById('test1-result');
    resultDiv.innerHTML = '<div class="result info">Testing...</div>';

    try {
        const response = await fetch('../api/get_reports.php');
        const data = await response.json();

        if (response.ok && data.success) {
            const reportCount = data.reports ? data.reports.length : 0;
            resultDiv.innerHTML = `
                <div class="result success">
                    <strong>✓ Success!</strong><br>
                    Reports found: ${reportCount}<br>
                    <pre class="json">${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="result error">
                    <strong>✗ API Error</strong><br>
                    Status: ${response.status}<br>
                    Response: <pre class="json">${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        }
    } catch (err) {
        resultDiv.innerHTML = `
            <div class="result error">
                <strong>✗ Network Error</strong><br>
                ${err.message}
            </div>
        `;
    }
}

function testDatabaseConnection() {
    const resultDiv = document.getElementById('test2-result');
    resultDiv.innerHTML = '<div class="result info">Testing...</div>';

    // Create a simple test script to check DB
    const testScript = `
        <?php
        require_once __DIR__ . '/../config/db.php';
        header('Content-Type: application/json');
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
            echo json_encode([
                'success' => true,
                'message' => 'Database connected',
                'server' => $conn->get_server_info(),
                'database' => 'testing_portal'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
        }
        ?>
    `;

    fetch('test_db_connection.php')
        .then(r => r.json())
        .then(data => {
            const html = data.success ? 
                `<div class="result success"><strong>✓ Connected</strong><br>Server: ${data.server}</div>` :
                `<div class="result error"><strong>✗ Failed</strong><br>${data.message}</div>`;
            resultDiv.innerHTML = html;
        })
        .catch(err => {
            resultDiv.innerHTML = `<div class="result error"><strong>✗ Error</strong><br>${err.message}</div>`;
        });
}

function testTableStructure() {
    const resultDiv = document.getElementById('test3-result');
    resultDiv.innerHTML = '<div class="result info">Testing...</div>';

    fetch('test_table_structure.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="result success"><strong>✓ Table Structure OK</strong><br>';
                html += '<table style="width: 100%; margin: 10px 0;">';
                html += '<tr><th>Field</th><th>Type</th></tr>';
                data.columns.forEach(col => {
                    html += `<tr><td>${col.Field}</td><td>${col.Type}</td></tr>`;
                });
                html += '</table></div>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = `<div class="result error"><strong>✗ Error</strong><br>${data.message}</div>`;
            }
        })
        .catch(err => {
            resultDiv.innerHTML = `<div class="result error"><strong>✗ Error</strong><br>${err.message}</div>`;
        });
}
</script>

<?php
// Additional helper scripts

// Save helper scripts if they don't exist
if ($_GET['action'] ?? null === 'test_db') {
    require_once __DIR__ . '/../config/db.php';
    header('Content-Type: application/json');
    echo json_encode([
        'success' => isset($conn) && $conn instanceof mysqli && !$conn->connect_errno,
        'message' => 'Database connection ' . (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno ? 'successful' : 'failed'),
        'server' => isset($conn) ? $conn->get_server_info() : 'N/A'
    ]);
    exit;
}

if ($_GET['action'] ?? null === 'test_table') {
    require_once __DIR__ . '/../config/db.php';
    header('Content-Type: application/json');
    
    $result = $conn->query("DESCRIBE test_reports");
    if ($result) {
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
        echo json_encode(['success' => true, 'columns' => $columns]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;
}
?>

</body>
</html>
