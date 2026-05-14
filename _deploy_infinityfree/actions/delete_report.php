<?php
// actions/delete_report.php
session_start();

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    http_response_code(401);
    die('Unauthorized: Session expired. Please login again.');
}

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) {
    $_SESSION['error'] = 'Invalid report ID.';
    header('Location: ../admin/order_flow_check.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed.');
    }

    $stmt = $conn->prepare('SELECT pdf_path FROM test_reports WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Unable to prepare report lookup.');
    }

    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$report) {
        $_SESSION['error'] = 'Report not found.';
        header('Location: ../admin/order_flow_check.php');
        exit;
    }

    $deleteStmt = $conn->prepare('DELETE FROM test_reports WHERE id = ? LIMIT 1');
    if (!$deleteStmt) {
        throw new Exception('Unable to prepare report deletion.');
    }

    $deleteStmt->bind_param('i', $reportId);
    $deleteStmt->execute();
    $deletedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($deletedRows < 1) {
        $_SESSION['error'] = 'Report could not be deleted.';
        header('Location: ../admin/order_flow_check.php');
        exit;
    }

    if (!empty($report['pdf_path'])) {
        $relativePath = ltrim($report['pdf_path'], '/');
        $fullPath = __DIR__ . '/../' . $relativePath;
        $realPath = realpath($fullPath);
        $reportsDir = realpath(__DIR__ . '/../reports');

        if ($realPath !== false && $reportsDir !== false && strpos($realPath, $reportsDir) === 0 && is_file($realPath)) {
            @unlink($realPath);
        }
    }

    $_SESSION['success'] = 'Report deleted successfully.';
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

header('Location: ../admin/order_flow_check.php');
exit;
?>
