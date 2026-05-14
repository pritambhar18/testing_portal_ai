<?php
// reports/download_report.php
if (empty($_GET['file'])) {
    http_response_code(400);
    echo 'Missing file parameter.';
    exit;
}

$fileName = basename($_GET['file']);
$baseDir = realpath(__DIR__ . '/generated');
$filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $fileName);

if (!$filePath || strpos($filePath, $baseDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
