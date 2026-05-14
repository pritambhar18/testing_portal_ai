<?php
ob_start();
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

function send_reports_json(array $payload, int $status = 200): void
{
    if (ob_get_length() !== false) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Validate database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }

    // Fetch all test reports with required columns only.
    // Ordered by execution date descending, then ID descending (latest first).
    $query = "
        SELECT id, test_link, execution_date, pdf_path
        FROM test_reports
        ORDER BY execution_date DESC, id DESC
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
    send_reports_json([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ]);

} catch (Exception $e) {
    send_reports_json([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}

$conn->close();
