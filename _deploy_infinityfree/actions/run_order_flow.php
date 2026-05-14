<?php
// actions/run_order_flow.php
session_start();

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('max_execution_time', '0');
set_time_limit(0);

function send_order_flow_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    send_order_flow_json([
        'success' => false,
        'error' => "PHP error: $message in $file on line $line",
    ], 500);
});

set_exception_handler(function ($ex) {
    send_order_flow_json([
        'success' => false,
        'error' => 'PHP exception: ' . $ex->getMessage(),
    ], 500);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        send_order_flow_json([
            'success' => false,
            'error' => 'Shutdown error: ' . $error['message'],
        ], 500);
    }
});

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    send_order_flow_json(['success' => false, 'error' => 'Unauthorized'], 401);
}

$adminEmail = (string)$_SESSION['admin_email'];
session_write_close();

require_once __DIR__ . '/../helpers/order_flow_reports.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_order_flow_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

$url = trim($_POST['order_flow_url'] ?? '');
$offerName = trim($_POST['offer_name'] ?? '');
$browser = trim($_POST['browser'] ?? 'msedge');
$allowedBrowsers = ['chromium', 'chrome', 'msedge', 'firefox', 'webkit'];

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    send_order_flow_json(['success' => false, 'error' => 'A valid URL is required.'], 422);
}

if ($offerName === '') {
    send_order_flow_json(['success' => false, 'error' => 'Offer name is required.'], 422);
}

if (!in_array($browser, $allowedBrowsers, true)) {
    send_order_flow_json(['success' => false, 'error' => 'Unsupported browser selected.'], 422);
}

if (!isset($_FILES['order_flow_csv']) || !is_array($_FILES['order_flow_csv'])) {
    send_order_flow_json(['success' => false, 'error' => 'CSV file is required.'], 422);
}

$file = $_FILES['order_flow_csv'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    send_order_flow_json(['success' => false, 'error' => 'CSV upload failed.'], 422);
}

$extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    send_order_flow_json(['success' => false, 'error' => 'Only CSV uploads are allowed.'], 422);
}

ensure_order_flow_reports_root();

$reportId = 'ofr_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 8);
$reportDir = order_flow_reports_root() . DIRECTORY_SEPARATOR . $reportId;
$inputDir = $reportDir . DIRECTORY_SEPARATOR . 'input';

if (!mkdir($inputDir, 0777, true) && !is_dir($inputDir)) {
    send_order_flow_json(['success' => false, 'error' => 'Unable to create report directory.'], 500);
}

$csvPath = $inputDir . DIRECTORY_SEPARATOR . 'orders.csv';
if (!move_uploaded_file($file['tmp_name'], $csvPath)) {
    send_order_flow_json(['success' => false, 'error' => 'Unable to store uploaded CSV.'], 500);
}

$locatorsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'order_placement' . DIRECTORY_SEPARATOR . 'locators.json';
$runnerPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'order_placement' . DIRECTORY_SEPARATOR . 'run-order-flow.mjs';
$runConfigPath = $reportDir . DIRECTORY_SEPARATOR . 'run-config.json';

$runConfig = [
    'reportId' => $reportId,
    'offerName' => $offerName,
    'baseUrl' => $url,
    'browser' => $browser,
    'csvPath' => $csvPath,
    'reportDir' => $reportDir,
    'locatorsPath' => $locatorsPath,
    'requestedBy' => $adminEmail,
];

$encodedConfig = json_encode($runConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($encodedConfig === false || file_put_contents($runConfigPath, $encodedConfig, LOCK_EX) === false) {
    send_order_flow_json(['success' => false, 'error' => 'Unable to write runner config.'], 500);
}

if (!function_exists('exec')) {
    send_order_flow_json([
        'success' => false,
        'error' => 'Order flow execution is unavailable on this hosting plan because PHP exec() is disabled.',
        'suggestion' => 'Please contact your hosting provider to enable the exec() function or switch to a hosting plan that supports it.',
    ], 500);
}

$nodeCommand = 'node ' . escapeshellarg($runnerPath) . ' ' . escapeshellarg($runConfigPath) . ' 2>&1';
$output = [];
$exitCode = 0;
exec($nodeCommand, $output, $exitCode);

$rawOutput = trim(implode("\n", $output));
$decoded = json_decode($rawOutput, true);

if ($exitCode !== 0 || !is_array($decoded)) {
    send_order_flow_json([
        'success' => false,
        'error' => 'Order flow runner failed.',
        'details' => $rawOutput !== '' ? $rawOutput : 'No runner output returned.',
    ], 500);
}

if (!($decoded['success'] ?? false)) {
    send_order_flow_json($decoded, 500);
}

$manifest = $decoded['report'] ?? null;
if (!is_array($manifest)) {
    send_order_flow_json(['success' => false, 'error' => 'Runner did not return report metadata.'], 500);
}

register_order_flow_report($manifest);

$runnerLogPath = $manifest['log_path'] ?? '';
if (is_string($runnerLogPath) && $runnerLogPath !== '') {
    @file_put_contents(
        $runnerLogPath,
        '[' . date('c') . '] PHP action: report registered in index.json for ' . ($manifest['id'] ?? 'unknown') . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

send_order_flow_json([
    'success' => true,
    'message' => 'Order flow completed.',
    'report' => $manifest,
]);
