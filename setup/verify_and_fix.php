<?php
/**
 * setup/verify_and_fix.php
 * Complete verification and automatic fix script
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify and Fix View Reports Module</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 900px; 
            margin: auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-top: 0; }
        h2 { color: #555; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .step { margin: 25px 0; padding: 20px; background: #f8f9fa; border-left: 4px solid #667eea; border-radius: 5px; }
        .step.complete { border-left-color: #28a745; background: #d4edda; }
        .step.error { border-left-color: #dc3545; background: #f8d7da; }
        .step.warning { border-left-color: #ffc107; background: #fff3cd; }
        .step h3 { margin: 0 0 10px 0; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 3px; font-weight: bold; margin-left: 10px; }
        .status.pass { background: #28a745; color: white; }
        .status.fail { background: #dc3545; color: white; }
        .status.warn { background: #ffc107; color: black; }
        code { 
            background: #f4f4f4; 
            padding: 3px 8px; 
            border-radius: 3px; 
            font-family: 'Courier New', monospace;
        }
        .button-group { margin: 15px 0; }
        .btn { 
            display: inline-block; 
            padding: 12px 25px; 
            margin: 5px 5px 5px 0;
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 1em;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-warning:hover { background: #e0a800; }
        .list-check { list-style: none; padding-left: 0; }
        .list-check li { padding: 8px 0; padding-left: 30px; position: relative; }
        .list-check li:before { 
            content: "✓"; 
            position: absolute; 
            left: 0; 
            color: #28a745; 
            font-weight: bold;
        }
        .summary { 
            background: #e8f5e9; 
            border: 2px solid #28a745; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0;
        }
        .summary h3 { color: #1b5e20; margin-top: 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 View Reports Module - Verification & Fix</h1>
    
    <div class="step complete">
        <h3>Step 1: Database Table <span class="status pass">✓ CHECKING</span></h3>
        <?php
        require_once __DIR__ . '/../config/db.php';
        
        $tableExists = false;
        $hasStatusColumn = false;
        
        if (isset($conn) && $conn instanceof mysqli) {
            // Check if table exists
            $result = $conn->query("SHOW TABLES LIKE 'test_reports'");
            $tableExists = $result && $result->num_rows > 0;
            
            if ($tableExists) {
                // Check if status column exists
                $result = $conn->query("SHOW COLUMNS FROM test_reports LIKE 'status'");
                $hasStatusColumn = $result && $result->num_rows > 0;
                
                echo '<p><strong>✓ test_reports table exists</strong></p>';
            }
        }
        
        if (!$tableExists) {
            echo '<p><strong style="color: red;">✗ test_reports table NOT FOUND</strong></p>';
            echo '<div class="button-group">';
            echo '<a href="create_test_reports_table.php" class="btn btn-success">Create test_reports Table</a>';
            echo '</div>';
        } else {
            if (!$hasStatusColumn) {
                echo '<p><strong style="color: orange;">⚠ Status column missing</strong></p>';
                echo '<div class="button-group">';
                echo '<a href="upgrade_add_status_column.php" class="btn btn-warning">Add Status Column</a>';
                echo '</div>';
            } else {
                echo '<p><strong>✓ Status column exists</strong></p>';
            }
        }
        
        $conn->close();
        ?>
    </div>

    <div class="step">
        <h3>Step 2: Verify Database Connection</h3>
        <ul class="list-check">
            <li>Database host: <code>localhost</code></li>
            <li>Database name: <code>testing_portal</code></li>
            <li>Configuration file: <code>config/db.php</code></li>
        </ul>
        <p><em>Check config/db.php if you're having connection issues.</em></p>
    </div>

    <div class="step">
        <h3>Step 3: Check API Integration</h3>
        <p><strong>The fix has been applied to:</strong></p>
        <ul class="list-check">
            <li><code>actions/run_test_report.php</code> - Now saves reports to database</li>
            <li><code>helpers/TestReportLogger.php</code> - Saves and retrieves reports</li>
            <li><code>api/get_reports.php</code> - Fetches reports for View Reports page</li>
        </ul>
    </div>

    <div class="step">
        <h3>Step 4: Test the Complete Flow</h3>
        <p>Follow these steps to verify everything is working:</p>
        <ol>
            <li>Go to <code>/admin/test_configuration.php</code></li>
            <li>Enter a test URL (e.g., https://www.google.com)</li>
            <li>Click "Test" button</li>
            <li>Wait for report to be generated (30-60 seconds)</li>
            <li>After completion, go to <code>/admin/view_reports.php</code></li>
            <li>Verify that your test appears in the table</li>
        </ol>
        <div class="button-group">
            <a href="../admin/test_configuration.php" class="btn btn-primary">Go to Test Configuration</a>
            <a href="../admin/view_reports.php" class="btn btn-info">Go to View Reports</a>
        </div>
    </div>

    <div class="step">
        <h3>Step 5: Troubleshooting</h3>
        <p><strong>If reports don't appear after testing:</strong></p>
        <ul>
            <li>Check browser console (F12) for JavaScript errors</li>
            <li>Check server logs in <code>logs/php-errors.log</code></li>
            <li>Verify database credentials in <code>config/db.php</code></li>
            <li>Ensure <code>test_reports</code> table exists with correct structure</li>
            <li>Run diagnostic: <a href="diagnose_reports.php" class="btn btn-warning">Run Diagnostics</a></li>
        </ul>
    </div>

    <div class="summary">
        <h3>📋 Summary of Changes</h3>
        <ul class="list-check">
            <li><strong>Database:</strong> Added <code>status</code> column to track report progress</li>
            <li><strong>Backend:</strong> Updated <code>run_test_report.php</code> to save reports using <code>TestReportLogger</code></li>
            <li><strong>API:</strong> <code>get_reports.php</code> now returns status and all report details</li>
            <li><strong>Frontend:</strong> View Reports page displays status with color-coded badges</li>
        </ul>
    </div>

    <div class="step complete">
        <h3>✅ What Should Happen Now</h3>
        <ol>
            <li>User runs a test from Test Configuration page</li>
            <li>Report is generated → PDF saved to disk</li>
            <li><strong>[NEW]</strong> Report data is automatically saved to <code>test_reports</code> table</li>
            <li><strong>[NEW]</strong> View Reports page fetches data from API</li>
            <li><strong>[NEW]</strong> Reports display in table with status badge</li>
        </ol>
    </div>

    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
    
    <h2>📊 Diagnostic Information</h2>
    <?php
    require_once __DIR__ . '/../config/db.php';
    
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
        echo '<div class="step complete">';
        echo '<h3>Database Status <span class="status pass">CONNECTED</span></h3>';
        
        // Check reports
        $result = $conn->query("SELECT COUNT(*) as count FROM test_reports");
        if ($result) {
            $row = $result->fetch_assoc();
            echo '<p>Reports in database: <strong>' . $row['count'] . '</strong></p>';
            
            if ($row['count'] > 0) {
                echo '<p>Latest reports:</p>';
                echo '<table style="width: 100%; border-collapse: collapse;">';
                $result = $conn->query("SELECT id, test_link, execution_date, status FROM test_reports ORDER BY execution_date DESC LIMIT 5");
                while ($r = $result->fetch_assoc()) {
                    echo '<tr style="border-bottom: 1px solid #ddd;">';
                    echo '<td style="padding: 8px;">' . $r['id'] . '</td>';
                    echo '<td style="padding: 8px;"><code>' . substr($r['test_link'], 0, 40) . '...</code></td>';
                    echo '<td style="padding: 8px;">' . $r['execution_date'] . '</td>';
                    echo '<td style="padding: 8px;"><span style="background: #e9ecef; padding: 3px 8px; border-radius: 3px;">' . ($r['status'] ?? 'Unknown') . '</span></td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        }
        
        echo '</div>';
    } else {
        echo '<div class="step error">';
        echo '<h3>Database Status <span class="status fail">NOT CONNECTED</span></h3>';
        echo '<p style="color: red;">Unable to connect to database. Check your credentials in config/db.php</p>';
        echo '</div>';
    }
    
    if (isset($conn)) {
        $conn->close();
    }
    ?>

</div>
</body>
</html>
