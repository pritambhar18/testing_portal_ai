<?php
/**
 * actions/download_report.php
 * Handles PDF report downloads
 */

session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    http_response_code(401);
    die('Unauthorized: Session expired. Please login again.');
}

// Get report ID from URL parameter
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
    http_response_code(400);
    die('Invalid report ID.');
}

require_once __DIR__ . '/../config/db.php';

try {
    // Validate database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }

    // Fetch the report details to get the PDF path
    $stmt = $conn->prepare("
        SELECT 
            id,
            test_link,
            execution_date,
            pdf_path
        FROM test_reports
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        die('Report not found.');
    }

    $report = $result->fetch_assoc();
    $stmt->close();

    // Validate PDF path
    if (empty($report['pdf_path'])) {
        http_response_code(404);
        die('PDF file path is not available for this report.');
    }

    // Construct the full file path
    // Remove leading slash if present (in case path is stored as /reports/generated/...)
    $relativePath = ltrim($report['pdf_path'], '/');
    $pdfPath = __DIR__ . '/../' . $relativePath;

    // Security check: ensure the file exists and is within the expected directory
    if (!file_exists($pdfPath)) {
        http_response_code(404);
        die('PDF file not found: ' . basename($pdfPath));
    }

    // Verify file is in the reports directory (prevent directory traversal attacks)
    $realPath = realpath($pdfPath);
    $reportsDir = realpath(__DIR__ . '/../reports');

    if ($realPath === false || strpos($realPath, $reportsDir) !== 0) {
        http_response_code(403);
        die('Access denied: Invalid file path.');
    }

    // Check if file is readable
    if (!is_readable($pdfPath)) {
        http_response_code(403);
        die('PDF file is not readable.');
    }

    // Get file size
    $fileSize = filesize($pdfPath);

    if ($fileSize === false || $fileSize <= 0) {
        http_response_code(500);
        die('Unable to determine file size.');
    }

    // Generate download filename
    $downloadFilename = 'test_report_' . $reportId . '_' . date('YmdHis', strtotime($report['execution_date'])) . '.pdf';

    // Set appropriate headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: 0');

    // Output the PDF file
    readfile($pdfPath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
