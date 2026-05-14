<?php
/**
 * setup/verify_pdf_paths.php
 * Verify that PDF paths are stored correctly in the database
 * and that files exist in the filesystem
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

echo "<h2>PDF Path Verification Report</h2>";
echo "<hr>";

try {
    // Check database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }

    echo "<h3>📊 Database Records:</h3>";
    
    $result = $conn->query("SELECT id, test_link, pdf_path, execution_date FROM test_reports ORDER BY id DESC LIMIT 5");
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    if ($result->num_rows === 0) {
        echo "<p style='color: orange;'>⚠️ No test reports found in database</p>";
    } else {
        echo "<table border='1' cellpadding='10' cellspacing='0' style='margin: 20px 0;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th>";
        echo "<th>Test Link</th>";
        echo "<th>PDF Path</th>";
        echo "<th>Execution Date</th>";
        echo "<th>File Exists</th>";
        echo "<th>Status</th>";
        echo "</tr>";

        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $test_link = htmlspecialchars($row['test_link']);
            $pdf_path = htmlspecialchars($row['pdf_path']);
            $execution_date = $row['execution_date'];
            
            // Check if file exists
            $relative_path = ltrim($pdf_path, '/');
            $full_path = __DIR__ . '/../' . $relative_path;
            $file_exists = file_exists($full_path);
            
            // Determine status
            $status = $file_exists ? "✅ OK" : "❌ NOT FOUND";
            $status_color = $file_exists ? "green" : "red";
            
            echo "<tr>";
            echo "<td>$id</td>";
            echo "<td>$test_link</td>";
            echo "<td><code>$pdf_path</code></td>";
            echo "<td>$execution_date</td>";
            echo "<td style='text-align: center;'>" . ($file_exists ? "Yes" : "No") . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>$status</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }

    echo "<hr>";
    echo "<h3>📁 File System Check:</h3>";
    
    $reports_dir = __DIR__ . '/../reports';
    $generated_dir = $reports_dir . '/generated';
    
    if (is_dir($generated_dir)) {
        $files = glob($generated_dir . '/*.html');
        $count = count($files);
        echo "<p>✅ Generated reports directory exists</p>";
        echo "<p>Files found: <strong>$count</strong> HTML files</p>";
        
        if ($count > 0) {
            echo "<details>";
            echo "<summary>Show files (" . $count . ")</summary>";
            echo "<ul>";
            foreach (array_slice($files, 0, 10) as $file) {
                echo "<li><code>" . basename($file) . "</code></li>";
            }
            if ($count > 10) {
                echo "<li>... and " . ($count - 10) . " more</li>";
            }
            echo "</ul>";
            echo "</details>";
        }
    } else {
        echo "<p style='color: red;'>❌ Generated reports directory not found: $generated_dir</p>";
    }

    // Test the path transformation
    echo "<hr>";
    echo "<h3>🔍 Path Format Verification:</h3>";
    
    // Sample paths to test
    $test_cases = [
        '/reports/generated/test_report_20260315_175928.html',
        'reports/generated/test_report_20260315_175928.html',
    ];
    
    foreach ($test_cases as $test_path) {
        $relative_path = ltrim($test_path, '/');
        $full_path = __DIR__ . '/../' . $relative_path;
        $realpath = realpath($full_path);
        $exists = file_exists($full_path);
        
        echo "<p>";
        echo "Path: <code>$test_path</code><br>";
        echo "After ltrim: <code>$relative_path</code><br>";
        echo "Full path: <code>$full_path</code><br>";
        echo "Exists: " . ($exists ? "✅ Yes" : "❌ No") . "<br>";
        echo "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

?>
