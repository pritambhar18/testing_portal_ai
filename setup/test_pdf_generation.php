<?php
/**
 * setup/test_pdf_generation.php
 * Diagnostic tool to test PDF generation and validation
 * This script tests the entire PDF workflow to ensure PDFs are generated correctly
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h2>PDF Generation & Validation Test Suite</h2>";
echo "<hr>";

// Test 1: Check wkhtmltopdf is installed
echo "<h3>Test 1: wkhtmltopdf Availability</h3>";

require_once __DIR__ . '/../reports/generate_report.php';

$wkhtmltopdf = find_wkhtmltopdf();
if ($wkhtmltopdf) {
    echo "<p style='color: green;'>✅ wkhtmltopdf found at: <code>$wkhtmltopdf</code></p>";
} else {
    echo "<p style='color: red;'>❌ wkhtmltopdf not found</p>";
}

// Test 2: Check directories exist
echo "<h3>Test 2: Report Directories</h3>";

$reports_dir = __DIR__ . '/../reports';
$generated_dir = $reports_dir . '/generated';
$screenshots_dir = $reports_dir . '/screenshots';

foreach (['reports' => $reports_dir, 'generated' => $generated_dir, 'screenshots' => $screenshots_dir] as $name => $path) {
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "<p style='color: green;'>✅ $name directory exists (perms: $perms)</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ $name directory missing, attempting to create...</p>";
        if (@mkdir($path, 0777, true)) {
            echo "<p style='color: green;'>✅ Created $name directory</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create $name directory</p>";
        }
    }
}

// Test 3: Test PDF validation function
echo "<h3>Test 3: PDF Validation Function</h3>";

// Create a test PDF file
$test_pdf = $generated_dir . '/test_validation_' . time() . '.pdf';
$dummy_pdf_content = "%PDF-1.4\n%dummy test pdf content\nThis is a minimal test PDF";

if (file_put_contents($test_pdf, $dummy_pdf_content)) {
    echo "<p>Created test PDF: <code>" . basename($test_pdf) . "</code></p>";
    
    if (is_valid_pdf($test_pdf)) {
        echo "<p style='color: green;'>✅ PDF validation passed</p>";
    } else {
        echo "<p style='color: red;'>❌ PDF validation failed</p>";
    }
    
    // Show magic bytes
    $handle = fopen($test_pdf, 'rb');
    $header = fread($handle, 10);
    fclose($handle);
    echo "<p>Magic bytes: <code>" . bin2hex($header) . "</code> = <code>" . htmlspecialchars($header) . "</code></p>";
    
    @unlink($test_pdf);
    echo "<p>Cleaned up test file</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create test PDF</p>";
}

// Test 4: Generate actual report
echo "<h3>Test 4: Full Report Generation</h3>";

$test_url = 'https://example.com';
$pages = [
    ['label' => 'Homepage', 'path' => '/']
];
$tested_by = 'PDF Generation Test';

echo "<p>Generating test report for: <code>$test_url</code></p>";

$result = generate_test_report($test_url, $pages, $tested_by);

if ($result['success']) {
    echo "<p style='color: green;'>✅ Report generated successfully</p>";
    
    echo "<p><strong>Report details:</strong></p>";
    echo "<ul>";
    echo "<li>View URL: <code>" . htmlspecialchars($result['view_url']) . "</code></li>";
    echo "<li>HTML file: <code>" . htmlspecialchars($result['report_html']) . "</code></li>";
    echo "<li>Total issues: " . intval($result['issues_total']) . "</li>";
    echo "</ul>";
    
    // Verify PDF file exists and is valid
    $pdf_path = ltrim($result['view_url'], '/');
    $full_pdf_path = __DIR__ . '/../' . $pdf_path;
    
    if (file_exists($full_pdf_path)) {
        $size = filesize($full_pdf_path);
        echo "<p style='color: green;'>✅ PDF file exists: <code>" . basename($full_pdf_path) . "</code> (" . $size . " bytes)</p>";
        
        if (is_valid_pdf($full_pdf_path)) {
            echo "<p style='color: green;'>✅ PDF validation passed - file is valid binary PDF</p>";
        } else {
            echo "<p style='color: red;'>❌ PDF validation failed - file may be corrupted</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ PDF file not found at: <code>$full_pdf_path</code></p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Report generation failed</p>";
    echo "<p>Error: " . htmlspecialchars($result['error']) . "</p>";
}

// Test 5: Database connectivity
echo "<h3>Test 5: Database Connection</h3>";

require_once __DIR__ . '/../config/db.php';

if (isset($conn) && $conn instanceof mysqli) {
    echo "<p style='color: green;'>✅ Database connected</p>";
    
    // Check if test_reports table exists
    $result = $conn->query("SHOW TABLES LIKE 'test_reports'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✅ test_reports table exists</p>";
        
        // Show table columns
        $columns = $conn->query("DESCRIBE test_reports");
        echo "<p><strong>Table columns:</strong></p>";
        echo "<ul>";
        while ($col = $columns->fetch_assoc()) {
            echo "<li><code>" . $col['Field'] . "</code> (" . $col['Type'] . ")</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p style='color: orange;'>⚠️ test_reports table not found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
}

echo "<hr>";
echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";

?>
