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
    // Treat only fatal errors as immediate failures. Non-fatal warnings/notices are logged and allowed to continue.
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (in_array($severity, $fatalTypes, true)) {
        send_order_flow_json([
            'success' => false,
            'error' => "PHP fatal error: $message in $file on line $line",
        ], 500);
    }

    // Non-fatal: record to server error log for diagnostics and continue.
    error_log(sprintf("PHP non-fatal (severity %d): %s in %s on line %d", $severity, $message, $file, $line));
    return true; // indicate the error was handled
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
$reportRoot = order_flow_reports_root();
$reportDir = $reportRoot . DIRECTORY_SEPARATOR . $reportId;
$inputDir = $reportDir . DIRECTORY_SEPARATOR . 'input';

// Try to create the normal report directory. If that fails due to permissions, fall back to system temp directory.
if (!is_dir($inputDir)) {
    // If reportRoot is already writable or its parent is writable, attempt creation; otherwise skip to temp fallback.
    $parentDir = dirname($reportRoot);
    $reportRootWritable = is_dir($reportRoot) && is_writable($reportRoot);
    $parentWritable = is_writable($parentDir);

    if ($reportRootWritable || $parentWritable) {
        // Attempt to create the reportRoot and input directory
        if (!is_dir($reportRoot)) {
            @mkdir($reportRoot, 0777, true);
        }

        if (!@mkdir($inputDir, 0777, true) && !is_dir($inputDir)) {
            // Creation failed despite writable parent; fall back to temp
            $reportRootWritable = false;
        }
    } else {
        // Parent not writable — cannot create in repo; will use temp fallback
        $reportRootWritable = false;
    }

    if (empty($reportRootWritable) || !is_dir($inputDir)) {
        // Attempt fallback to system temp directory
        $tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'testing_portal_order_flow';
        $tempReportDir = $tempRoot . DIRECTORY_SEPARATOR . $reportId;
        $tempInputDir = $tempReportDir . DIRECTORY_SEPARATOR . 'input';

        if (!is_dir($tempInputDir)) {
            @mkdir($tempInputDir, 0777, true);
        }

        if (is_dir($tempInputDir) && is_writable($tempInputDir)) {
            // Use temp fallback
            $reportDir = $tempReportDir;
            $inputDir = $tempInputDir;
        } else {
            // Nothing worked — return helpful error
            send_order_flow_json([
                'success' => false,
                'error' => 'Unable to create report directory due to filesystem permissions.',
                'attempted_paths' => [$reportRoot, $reportDir, $tempReportDir],
                'suggestion' => 'Please make uploads/order_flow_reports writable by the web server user or provide a writable directory.',
            ], 500);
        }
    }
}

// Attempt to relax permissions (best-effort)
@chmod($inputDir, 0777);

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

// Check for exec() or try alternatives
$execAvailable = function_exists('exec');
$fallbackMethod = null;

if (!$execAvailable) {
    if (function_exists('proc_open')) {
        $fallbackMethod = 'proc_open';
    } elseif (function_exists('shell_exec')) {
        $fallbackMethod = 'shell_exec';
    } elseif (function_exists('system')) {
        $fallbackMethod = 'system';
    } elseif (function_exists('passthru')) {
        $fallbackMethod = 'passthru';
    }
}

if (!$execAvailable && !$fallbackMethod) {
    // No execution functions available - use queue-based fallback
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
        send_order_flow_json([
            'success' => true,
            'message' => 'Order flow job queued for processing.',
            'jobId' => $jobId,
            'reportId' => $reportId,
            'status' => 'pending',
            'note' => 'Your hosting plan does not support direct command execution. The job has been queued and will be processed by a background worker when available. Please check back in 1-2 minutes.',
            'checkStatusUrl' => '/admin/view_reports.php?report=' . $reportId,
        ], 202);
    }

    send_order_flow_json([
        'success' => false,
        'error' => 'Order flow execution is unavailable on this hosting plan because PHP exec() is disabled.',
        'details' => 'No alternative execution functions (proc_open, shell_exec, system, passthru) are available either.',
        'suggestion' => 'Please contact your hosting provider to enable command execution functions (exec, proc_open, system, or shell_exec) or upgrade to a plan that supports them.',
        'reference' => 'See /testing_portal/IMMEDIATE_ACTION_PLAN.md for detailed instructions.',
    ], 503);
}

$nodeCommand = 'node ' . escapeshellarg($runnerPath) . ' ' . escapeshellarg($runConfigPath) . ' 2>&1';
$output = [];
$exitCode = 0;

// Use exec if available, otherwise fall back
if ($execAvailable) {
    @exec($nodeCommand, $output, $exitCode);
} elseif ($fallbackMethod === 'proc_open') {
    $spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($nodeCommand, $spec, $pipes, null, null);
    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = explode("\n", trim(stream_get_contents($pipes[1])));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    } else {
        send_order_flow_json([
            'success' => false,
            'error' => 'proc_open is available but failed to execute.',
        ], 500);
    }
} elseif ($fallbackMethod === 'shell_exec') {
    $result = @shell_exec($nodeCommand);
    $output = $result ? explode("\n", trim($result)) : [];
    $exitCode = 0;
}

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
    if (!empty($decoded['stopped'])) {
        send_order_flow_json($decoded, 200);
    }
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
