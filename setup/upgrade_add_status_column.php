<?php
/**
 * setup/upgrade_add_status_column.php
 * Adds the status column to test_reports table if it doesn't exist
 * Run this if you're upgrading an existing installation
 */

require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }

    // Check if status column exists
    $checkColumnQuery = "SHOW COLUMNS FROM test_reports LIKE 'status'";
    $result = $conn->query($checkColumnQuery);

    if ($result && $result->num_rows > 0) {
        echo "✓ Status column already exists in test_reports table.<br>";
    } else {
        // Add status column
        $alterSQL = "
            ALTER TABLE test_reports 
            ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending' 
            COMMENT 'Pending, Running, Completed, Failed' 
            AFTER pdf_path
        ";

        if ($conn->query($alterSQL) === TRUE) {
            echo "✓ Status column added successfully!<br>";
        } else {
            throw new Exception('Error adding status column: ' . $conn->error);
        }

        // Add index on status column
        $indexSQL = "ALTER TABLE test_reports ADD INDEX idx_status (status)";
        if ($conn->query($indexSQL) === TRUE) {
            echo "✓ Status index created successfully!<br>";
        } else {
            // Index might already exist, ignore error
            echo "✓ Status index already exists.<br>";
        }
    }

    // Verify final structure
    $verifyQuery = "DESCRIBE test_reports";
    $result = $conn->query($verifyQuery);

    if ($result) {
        echo "<br><strong>Updated Table Structure:</strong><br>";
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

    echo "<br><p><strong>Upgrade complete!</strong> You can now use the status tracking features.</p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

$conn->close();
?>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 2rem;
        background: #f5f5f5;
    }
    h3 {
        color: #333;
    }
    table {
        border-collapse: collapse;
    }
</style>
