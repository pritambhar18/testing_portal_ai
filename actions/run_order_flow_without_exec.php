<?php
/**
 * ALTERNATIVE: Order Flow Execution WITHOUT exec()
 * 
 * Use this if exec() is permanently disabled on your hosting.
 * This version uses proc_open() or queues the job for background processing.
 * 
 * To use this:
 * 1. Backup: cp actions/run_order_flow.php actions/run_order_flow.php.backup
 * 2. Implement one of the strategies below
 * 3. Update the form to call this instead
 */

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

// Validate inputs
$url = trim($_POST['order_flow_url'] ?? '');
$offerName = trim($_POST['offer_name'] ?? '');
$browser = trim($_POST['browser'] ?? 'msedge');
$clientRunId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['client_run_id'] ?? ''));
$headless = filter_var($_POST['headless'] ?? '1', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($headless === null) {
    $headless = true;
}
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

if ($clientRunId === '') {
    $clientRunId = 'ofr_client_' . bin2hex(random_bytes(8));
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
$stopRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'order_flow_stops';
if (!is_dir($stopRoot)) {
    @mkdir($stopRoot, 0777, true);
}
$stopSignalPath = $stopRoot . DIRECTORY_SEPARATOR . $clientRunId . '.stop';
if (is_file($stopSignalPath)) {
    @unlink($stopSignalPath);
}

$runConfig = [
    'reportId' => $reportId,
    'offerName' => $offerName,
    'baseUrl' => $url,
    'browser' => $browser,
    'headless' => $headless,
    'csvPath' => $csvPath,
    'reportDir' => $reportDir,
    'locatorsPath' => $locatorsPath,
    'requestedBy' => $adminEmail,
    'clientRunId' => $clientRunId,
    'stopSignalPath' => $stopSignalPath,
];

$encodedConfig = json_encode($runConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($encodedConfig === false || file_put_contents($runConfigPath, $encodedConfig, LOCK_EX) === false) {
    send_order_flow_json(['success' => false, 'error' => 'Unable to write runner config.'], 500);
}

// ============================================================================
// STRATEGY 1: Try proc_open() if exec() not available
// ============================================================================

if (function_exists('proc_open')) {
    try {
        $nodeCommand = 'node ' . escapeshellarg($runnerPath) . ' ' . escapeshellarg($runConfigPath);
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];
        
        $process = proc_open($nodeCommand, $descriptorSpec, $pipes, null, null);
        
        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin
            
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $exitCode = proc_close($process);
            
            $rawOutput = trim($output ?: $error);
            $decoded = json_decode($rawOutput, true);
            
            if ($exitCode === 0 && is_array($decoded)) {
                if ($decoded['success'] ?? false) {
                    $manifest = $decoded['report'] ?? null;
                    if (is_array($manifest)) {
                        register_order_flow_report($manifest);
                        $runnerLogPath = $manifest['log_path'] ?? '';
                        if (is_string($runnerLogPath) && $runnerLogPath !== '') {
                            @file_put_contents(
                                $runnerLogPath,
                                '[' . date('c') . '] PHP action (proc_open): report registered in index.json for ' . ($manifest['id'] ?? 'unknown') . PHP_EOL,
                                FILE_APPEND | LOCK_EX
                            );
                        }
                        send_order_flow_json([
                            'success' => true,
                            'message' => 'Order flow completed via proc_open.',
                            'report' => $manifest,
                        ]);
                    }
                } else {
                    send_order_flow_json($decoded, 500);
                }
            }
        }
    } catch (Exception $e) {
        // Fall through to next strategy
    }
}

// ============================================================================
// STRATEGY 2: Queue-based execution (save job, wait for background processor)
// ============================================================================

// Create a queue directory for background jobs
$queueDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'order_flow_queue';
if (!is_dir($queueDir)) {
    @mkdir($queueDir, 0777, true);
}

$jobId = 'job_' . $reportId;
$jobFile = $queueDir . DIRECTORY_SEPARATOR . $jobId . '.json';

$job = [
    'jobId' => $jobId,
    'reportId' => $reportId,
    'runConfigPath' => $runConfigPath,
    'runnerPath' => $runnerPath,
    'status' => 'pending',
    'created_at' => date('c'),
    'completed_at' => null,
];

if (file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
    // Job queued successfully
    // Return immediately telling user to check back later
    send_order_flow_json([
        'success' => true,
        'message' => 'Order flow job queued for processing.',
        'jobId' => $jobId,
        'reportId' => $reportId,
        'status' => 'pending',
        'note' => 'This hosting plan does not support synchronous execution. Your job has been queued and will be processed by a background worker. Check the report status in 1-2 minutes.',
        'checkStatusUrl' => '/admin/view_reports.php?report=' . $reportId,
    ], 202); // 202 Accepted
}

// ============================================================================
// STRATEGY 3: Return helpful error message if no alternatives work
// ============================================================================

send_order_flow_json([
    'success' => false,
    'error' => 'Order flow execution is unavailable on this hosting plan.',
    'details' => 'Neither exec(), proc_open(), nor queue-based execution is available.',
    'suggestion' => 'Please upgrade your hosting plan or contact your provider to enable command execution functions.',
    'availableFunctions' => [
        'exec' => function_exists('exec'),
        'proc_open' => function_exists('proc_open'),
        'shell_exec' => function_exists('shell_exec'),
        'system' => function_exists('system'),
    ],
], 503); // 503 Service Unavailable
