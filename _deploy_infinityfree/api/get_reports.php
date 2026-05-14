
<?php
/**
 * api/get_reports.php
 * Clean API endpoint to fetch test reports from the database
 * Returns JSON data for the View Reports page
 * 
 * Endpoint: GET /api/get_reports.php
 * Response: JSON with reports array containing id, test_link, execution_date, pdf_path
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

try {
    // Validate database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }

    // Fetch all test reports with required columns only
    // Ordered by ID descending (newest first)
    $query = "
        SELECT id, test_link, execution_date, pdf_path
        FROM test_reports
        ORDER BY id DESC
    ";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'id' => (int)$row['id'],
            'test_link' => $row['test_link'],
            'execution_date' => $row['execution_date'],
            'pdf_path' => $row['pdf_path']
        ];
    }

    $result->free();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
