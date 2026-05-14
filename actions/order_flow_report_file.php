<?php
// actions/order_flow_report_file.php

session_start();

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    http_response_code(401);
    die('Unauthorized: Session expired. Please login again.');
}

require_once __DIR__ . '/../helpers/order_flow_reports.php';

$reportId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['id'] ?? ''));
$download = isset($_GET['download']) && (string)$_GET['download'] === '1';

if ($reportId === '') {
    http_response_code(400);
    die('Invalid report ID.');
}

$report = null;
foreach (read_order_flow_reports_index() as $entry) {
    if (($entry['id'] ?? '') === $reportId) {
        $report = $entry;
        break;
    }
}

if (!is_array($report)) {
    http_response_code(404);
    die('Report not found.');
}

$reportDir = (string)($report['report_dir'] ?? '');
$realReportDir = $reportDir !== '' ? realpath($reportDir) : false;

if ($realReportDir === false || !is_dir($realReportDir) || basename($realReportDir) !== $reportId) {
    http_response_code(404);
    die('Report directory not found.');
}

$allowedRoots = array_filter([
    realpath(order_flow_reports_root()),
    realpath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'testing_portal_order_flow'),
]);

$insideAllowedRoot = false;
foreach ($allowedRoots as $allowedRoot) {
    $allowedRoot = rtrim($allowedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($realReportDir . DIRECTORY_SEPARATOR, $allowedRoot) === 0) {
        $insideAllowedRoot = true;
        break;
    }
}

if (!$insideAllowedRoot) {
    http_response_code(403);
    die('Access denied: invalid report location.');
}

$format = strtolower((string)($report['report_format'] ?? 'pdf'));
$preferredFile = $format === 'html' ? 'report.html' : 'report.pdf';
$candidatePath = $realReportDir . DIRECTORY_SEPARATOR . $preferredFile;

if (!is_file($candidatePath)) {
    $fallbackFile = $preferredFile === 'report.pdf' ? 'report.html' : 'report.pdf';
    $candidatePath = $realReportDir . DIRECTORY_SEPARATOR . $fallbackFile;
}

$realFile = realpath($candidatePath);
if ($realFile === false || !is_file($realFile) || strpos($realFile, $realReportDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    die('Report artifact not found.');
}

if (!is_readable($realFile)) {
    http_response_code(403);
    die('Report artifact is not readable.');
}

$extension = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$mime = $extension === 'html' || $extension === 'htm'
    ? 'text/html; charset=utf-8'
    : 'application/pdf';
$disposition = $download ? 'attachment' : 'inline';
$filename = $reportId . '.' . ($extension ?: 'pdf');
$fileSize = filesize($realFile);

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
if ($fileSize !== false) {
    header('Content-Length: ' . $fileSize);
}
header('Cache-Control: private, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');

readfile($realFile);
exit;
