<?php
// actions/delete_order_flow_report.php
session_start();

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: ../admin/login.php');
    exit;
}

require_once __DIR__ . '/../helpers/order_flow_reports.php';

$reportId = trim($_GET['id'] ?? '');
if ($reportId === '') {
    $_SESSION['error'] = 'Invalid order flow report ID.';
    header('Location: ../admin/order_flow_check.php');
    exit;
}

$removed = remove_order_flow_report($reportId);
if ($removed === null) {
    $_SESSION['error'] = 'Order flow report not found.';
    header('Location: ../admin/order_flow_check.php');
    exit;
}

$reportDir = $removed['report_dir'] ?? '';
$root = realpath(order_flow_reports_root());
$realDir = $reportDir !== '' ? realpath($reportDir) : false;

if ($root !== false && $realDir !== false && strpos($realDir, $root) === 0) {
    delete_order_flow_report_directory($realDir);
}

$_SESSION['success'] = 'Order flow report deleted successfully.';
header('Location: ../admin/order_flow_check.php');
exit;
