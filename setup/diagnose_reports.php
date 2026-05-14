<?php
/**
 * setup/diagnose_reports.php
 * Diagnostic script to verify View Reports module setup and data flow
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Reports Module Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .check { margin: 15px 0; padding: 15px; border-left: 4px solid #ccc; border-radius: 4px; }
        .check.pass { border-color: #28a745; background: #d4edda; }
        .check.fail { border-color: #dc3545; background: #f8d7da; }
        .check.warn { border-color: #ffc107; background: #fff3cd; }
        .check h3 { margin: 0 0 10px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .detail { margin: 10px 0; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background: #f4f4f4; }
        .action-button { margin: 20px 0; }
        .action-button a { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .action-button a:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 View Reports Module Diagnostics</h1>
    <p>Testing database connection, table structure, and API endpoints...</p>

    <?php
    require_once __DIR__ . '/../config/db.php';

    // Check 1: Database Connection
    echo '<div class="check ' . (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno ? 'pass' : 'fail') . '">';
    echo '<h3>✓ Database Connection</h3>';
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
        echo '<div class="detail">Connected to: <code>' . htmlspecialchars($conn->get_server_info()) . '</code></div>';
        echo '<div class="detail">Database: <code>' . htmlspecialchars($conn->real_escape_string('') ? 'testing_portal' : 'unknown') . '</code></div>';
    } else {
        echo '<div class="detail" style="color: red;">❌ Failed to connect to database</div>';
    }
    echo '</div>';

    // Check 2: test_reports Table Exists
    echo '<div class="check ' . (checkTableExists($conn, 'test_reports') ? 'pass' : 'fail') . '">';
    echo '<h3>✓ test_reports Table Structure</h3>';
    if (checkTableExists($conn, 'test_reports')) {
        echo '<table>';
        echo '<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>';
        echo '<tbody>';
        $result = $conn->query('DESCRIBE test_reports');
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['Field']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="detail" style="color: red;">❌ test_reports table not found</div>';
        echo '<div class="action-button"><a href="create_test_reports_table.php">Create test_reports Table</a></div>';
    }
    echo '</div>';

    // Check 3: Status Column Exists
    if (checkTableExists($conn, 'test_reports')) {
        $hasStatus = statusColumnExists($conn);
        echo '<div class="check ' . ($hasStatus ? 'pass' : 'warn') . '">';
        echo '<h3>' . ($hasStatus ? '✓' : '⚠') . ' Status Column</h3>';
        if ($hasStatus) {
            echo '<div class="detail">Status column is present with proper configuration</div>';
        } else {
            echo '<div class="detail" style="color: #ff9800;">Status column missing - table needs upgrade</div>';
            echo '<div class="action-button"><a href="upgrade_add_status_column.php">Add Status Column</a></div>';
        }
        echo '</div>';
    }

    // Check 4: Report Count
    if (checkTableExists($conn, 'test_reports')) {
        echo '<div class="check pass">';
        echo '<h3>📈 Current Reports</h3>';
        $result = $conn->query('SELECT COUNT(*) as total FROM test_reports');
        $row = $result->fetch_assoc();
        $reportCount = (int)$row['total'];
        echo '<div class="detail">Reports in database: <strong>' . $reportCount . '</strong></div>';
        
        if ($reportCount > 0) {
            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Test Link</th><th>Execution Date</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            $result = $conn->query('SELECT id, test_link, execution_date, status FROM test_reports ORDER BY execution_date DESC LIMIT 5');
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td><code>' . htmlspecialchars(substr($row['test_link'], 0, 40)) . '...</code></td>';
                echo '<td>' . htmlspecialchars($row['execution_date']) . '</td>';
                echo '<td><span style="padding: 4px 8px; background: #e9ecef; border-radius: 3px;">' . htmlspecialchars($row['status'] ?? 'Unknown') . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="detail" style="color: #666; font-style: italic;">No reports found. Run tests from Test Configuration page to generate reports.</div>';
            echo '<div class="action-button"><a href="insert_test_reports.php">Insert Sample Reports (for testing)</a></div>';
        }
        echo '</div>';
    }

    // Check 5: API Endpoint Test
    echo '<div class="check warn">';
    echo '<h3>🔌 API Endpoints Status</h3>';
    echo '<div class="detail">1. <code>GET /api/get_reports.php</code> - Fetches reports from database</div>';
    echo '<div class="detail">2. <code>POST /actions/run_test_report.php</code> - Executes test and saves report</div>';
    echo '<div class="action-button"><a href="test_api_endpoints.php">Test API Endpoints</a></div>';
    echo '</div>';

    // Check 6: TestReportLogger Class
    echo '<div class="check ' . (testReportLoggerExists() ? 'pass' : 'fail') . '">';
    echo '<h3>' . (testReportLoggerExists() ? '✓' : '❌') . ' TestReportLogger Class</h3>';
    if (testReportLoggerExists()) {
        echo '<div class="detail">TestReportLogger class is available and ready to use</div>';
    } else {
        echo '<div class="detail" style="color: red;">TestReportLogger class not found</div>';
    }
    echo '</div>';

    // Summary & Next Steps
    echo '<div class="check pass">';
    echo '<h3>✅ Next Steps</h3>';
    echo '<ol>';
    echo '<li>Ensure database table exists: Run <code>create_test_reports_table.php</code> if needed</li>';
    echo '<li>Run a test from <code>/admin/test_configuration.php</code></li>';
    echo '<li>Check if report appears in <code>/admin/view_reports.php</code></li>';
    echo '<li>If no reports appear, check browser console for errors and server logs</li>';
    echo '</ol>';
    echo '</div>';

    $conn->close();

    // Helper functions
    function checkTableExists($conn, $tableName) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
        return $result && $result->num_rows > 0;
    }

    function statusColumnExists($conn) {
        $result = $conn->query("SHOW COLUMNS FROM test_reports LIKE 'status'");
        return $result && $result->num_rows > 0;
    }

    function testReportLoggerExists() {
        return file_exists(__DIR__ . '/../helpers/TestReportLogger.php');
    }
    ?>

</div>
</body>
</html>
