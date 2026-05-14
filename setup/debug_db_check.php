<?php
/**
 * debug_db_check.php
 * Debug script to check database connection and test_reports table
 */

require_once __DIR__ . '/../config/db.php';

echo "<h2>Database Connection Debug</h2>";

if ($conn->connect_errno) {
    echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connection successful.</p>";

// Check if test_reports table exists
$result = $conn->query('SHOW TABLES LIKE "test_reports"');
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✅ test_reports table exists.</p>";

    // Show table structure
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

    $result = $conn->query('DESCRIBE test_reports');
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "</td>";
        echo "<td>" . ($row['Key'] ?: ' ') . "</td>";
        echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check for existing records
    $countResult = $conn->query('SELECT COUNT(*) as count FROM test_reports');
    $count = $countResult->fetch_assoc()['count'];
    echo "<p><strong>Existing records:</strong> $count</p>";

    if ($count > 0) {
        echo "<h3>Recent Records:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Test Link</th><th>Execution Date</th><th>Status</th><th>PDF Path</th></tr>";

        $records = $conn->query('SELECT id, test_link, execution_date, status, pdf_path FROM test_reports ORDER BY execution_date DESC LIMIT 5');
        while ($row = $records->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['test_link'], 0, 50)) . "...</td>";
            echo "<td>" . $row['execution_date'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['pdf_path'] ?: '', 0, 30)) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} else {
    echo "<p style='color: red;'>❌ test_reports table does NOT exist.</p>";
}

$conn->close();
?>