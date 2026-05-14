<?php
/**
 * setup/create_test_reports_table.php
 * Creates the test_reports table for the View Reports module
 * Run this once to initialize the database structure
 */

require_once __DIR__ . '/../config/db.php';

// Check if table already exists
$tableCheckQuery = "SHOW TABLES LIKE 'test_reports'";
$tableCheckResult = $conn->query($tableCheckQuery);

if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
    echo "✓ test_reports table already exists.<br>";
} else {
    // Create test_reports table
    $createTableSQL = "
    CREATE TABLE test_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        test_link VARCHAR(500) NOT NULL,
        execution_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        pdf_path VARCHAR(500),
        status VARCHAR(50) NOT NULL DEFAULT 'Pending' COMMENT 'Pending, Running, Completed, Failed',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_execution_date (execution_date),
        INDEX idx_test_link (test_link),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    if ($conn->query($createTableSQL) === TRUE) {
        echo "✓ test_reports table created successfully!<br>";
    } else {
        echo "✗ Error creating test_reports table: " . $conn->error . "<br>";
    }
}

// Verify column structure
$verifyQuery = "DESCRIBE test_reports";
$result = $conn->query($verifyQuery);

if ($result) {
    echo "<br><strong>Table Structure:</strong><br>";
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
