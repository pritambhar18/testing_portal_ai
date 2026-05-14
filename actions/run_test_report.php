<?php
ob_start();
// actions/run_test_report.php
// Single-file report generation endpoint (renamed from v2 implementation).

// Increase execution time limit to 5 minutes to handle multiple page tests
set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return true;
    }
    if (ob_get_length() !== false) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "PHP error: $message in $file on line $line"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

set_exception_handler(function ($ex) {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PHP exception: ' . $ex->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length() !== false) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Shutdown error: ' . $err['message']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
});

function send_json($payload, $status = 200) {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function safe_url($url) {
    $clean = trim($url);
    $clean = preg_replace('/\s+/', '', $clean);
    return $clean;
}

function parse_requested_pages(array $payload) {
    $pages = [];

    if (!empty($payload['pages']) && is_array($payload['pages'])) {
        foreach ($payload['pages'] as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $url = safe_url($page['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $label = trim((string)($page['label'] ?? ''));
            if ($label === '') {
                $label = 'Page ' . ($index + 1);
            }

            $pages[] = [
                'label' => $label,
                'url' => $url,
            ];
        }
    }

    if (!empty($pages)) {
        return $pages;
    }

    $fallbackUrl = safe_url($payload['url'] ?? '');
    if ($fallbackUrl === '') {
        return [];
    }

    $fallbackLabel = trim((string)($payload['label'] ?? 'Quick Test Page'));
    if ($fallbackLabel === '') {
        $fallbackLabel = 'Quick Test Page';
    }

    return [[
        'label' => $fallbackLabel,
        'url' => $fallbackUrl,
    ]];
}

function run_automation_checks($baseUrl, array $pages = []) {
    $script = realpath(__DIR__ . '/../automation/form-functional-checks.mjs');
    $config = realpath(__DIR__ . '/../automation/form-functional-checks.config.json');
    $resultsDir = __DIR__ . '/../automation/results';

    $payload = [
        'entries' => [],
        'warnings' => [],
        'success' => false,
        'path' => '',
        'command_output' => [],
    ];

    if (!$script || !$config) {
        $payload['warnings'][] = 'Automation script or config file was not found.';
        return $payload;
    }

    if (!function_exists('exec')) {
        $payload['warnings'][] = 'Node automation is unavailable on this hosting plan because PHP exec() is disabled.';
        return $payload;
    }

    if (!is_dir($resultsDir) && !mkdir($resultsDir, 0777, true)) {
        $payload['warnings'][] = 'Unable to create automation results directory.';
        return $payload;
    }

    $timestamp = (new DateTime())->format('Ymd_His');
    $outputPath = $resultsDir . DIRECTORY_SEPARATOR . "form-functional-checks_{$timestamp}.json";
    $payload['path'] = $outputPath;

    $pagesJson = json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pagesPayload = 'base64:' . base64_encode($pagesJson ?: '[]');
    $commandArgs = array_map('escapeshellarg', [$script, $config, $baseUrl, $outputPath, $pagesPayload]);
    $command = 'node ' . implode(' ', $commandArgs);

    try {
        @exec($command . ' 2>&1', $commandOutput, $exitCode);
    } catch (Exception $e) {
        $payload['warnings'][] = 'Automation failed: ' . $e->getMessage();
        return $payload;
    }
    $payload['command_output'] = $commandOutput;

    if ($exitCode !== 0) {
        $payload['warnings'][] = 'Automation script execution failed: ' . implode(PHP_EOL, $commandOutput);
    }

    if (file_exists($outputPath)) {
        $contents = file_get_contents($outputPath);
        $entries = json_decode($contents, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($entries)) {
            $payload['entries'] = $entries;
        } else {
            $payload['warnings'][] = 'Automation report JSON was invalid.';
        }
    } elseif ($exitCode === 0) {
        $payload['warnings'][] = 'Automation script completed without generating a report file.';
    }

    $payload['success'] = $exitCode === 0 && !empty($payload['entries']) && empty($payload['warnings']);
    return $payload;
}

require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../helpers/TestReportLogger.php';

// Load database connection
$conn = require_once __DIR__ . '/../config/db.php';

// Orchestrator for /api call
try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        send_json(['success' => false, 'error' => 'Invalid JSON payload.'], 400);
    }

    $testedBy = trim($payload['tested_by'] ?? 'Automated Test Runner');
    $pages = parse_requested_pages($payload);

    if (empty($pages)) {
        send_json(['success' => false, 'error' => 'Empty URL. Please provide a valid URL.'], 400);
    }

    foreach ($pages as $page) {
        if (!is_valid_url($page['url'])) {
            send_json(['success' => false, 'error' => 'Invalid URL format for ' . $page['label'] . '.'], 400);
        }
    }

    $baseUrl = $pages[0]['url'];
    $automationPayload = run_automation_checks($baseUrl, $pages);

    require_once __DIR__ . '/../reports/generate_report.php';

    $result = generate_test_report($baseUrl, $pages, $testedBy, $automationPayload);
    $result['automation_report_path'] = $automationPayload['path'];
    if (!empty($automationPayload['warnings'])) {
        $result['automation_warnings'] = $automationPayload['warnings'];
    }

    if (!empty($result['success']) && $result['success'] === true) {
        // ✅ FIXED: Save report to database using TestReportLogger
        try {
            if (isset($conn) && $conn instanceof mysqli) {
                $reporter = new TestReportLogger($conn);
                
                        // Extract PDF path if available
                $pdf_path = isset($result['pdf_path']) ? trim((string)$result['pdf_path']) : '';
                if ($pdf_path !== '') {
                    $pdf_path = preg_replace('#^\.\./#', '', $pdf_path);
                    $pdf_path = ltrim($pdf_path, '/');

                    // Validate PDF file exists and is valid before saving to database
                    $full_pdf_path = __DIR__ . '/../' . $pdf_path;
                    if (!file_exists($full_pdf_path)) {
                        error_log("Error: PDF file not found for database save: " . $full_pdf_path);
                        send_json(['success' => false, 'error' => 'PDF file was generated but not found during database save. Path: ' . $pdf_path], 500);
                    }

                    $file_size = @filesize($full_pdf_path);
                    if ($file_size === false || $file_size === 0) {
                        error_log("Error: PDF file has invalid size: " . $file_size . " for path: " . $full_pdf_path);
                        @unlink($full_pdf_path);
                        send_json(['success' => false, 'error' => 'PDF file was created but is empty or unreadable'], 500);
                    }

                    $handle = @fopen($full_pdf_path, 'rb');
                    if (!$handle) {
                        error_log("Error: Cannot open PDF file for validation: " . $full_pdf_path);
                        send_json(['success' => false, 'error' => 'PDF file exists but cannot be read'], 500);
                    }
                    $header = @fread($handle, 5);
                    @fclose($handle);

                    if (strpos($header, '%PDF-') !== 0) {
                        error_log("Error: PDF magic bytes check failed for: " . $full_pdf_path . " Header: " . bin2hex($header));
                        @unlink($full_pdf_path);
                        send_json(['success' => false, 'error' => 'PDF file is corrupted (invalid PDF format)'], 500);
                    }
                } elseif (!empty($result['view_url']) || !empty($result['report_html'])) {
                    $result['warning'] = $result['warning'] ?? 'PDF generation is unavailable on this server. Report is available as HTML only.';
                    $result['pdf_path'] = '';
                } else {
                    error_log("Error: No PDF path returned from report generation");
                    send_json(['success' => false, 'error' => 'Report generated but output file path is missing'], 500);
                }
                
                // Log the test report (schema matches table columns: test_link, execution_date, pdf_path)
                $report_html = isset($result['report_html']) ? trim((string)$result['report_html']) : '';
                $report_id = $reporter->logTestReport($baseUrl, $pdf_path, $report_html);
                
                if ($report_id) {
                    // Add report_id to response for client
                    $result['report_id'] = $report_id;
                    $result['message'] = 'Test report generated and saved successfully.';
                } else {
                    // Report generated but couldn't save to DB - still return success but log warning
                    error_log("Warning: Report generated but failed to save to database for URL: " . $baseUrl);
                    $result['warning'] = 'Report generated but database save failed - may not appear in View Reports';
                }
            } else {
                error_log("Warning: Database not available for report logging");
                $result['warning'] = 'Report generated but database unavailable - may not appear in View Reports';
            }
        } catch (Exception $e) {
            error_log("Error saving report to database: " . $e->getMessage());
            $result['warning'] = 'Report generated but failed to save to database';
        }

        send_json($result, 200);
    }

    $statusCode = $result['code'] ?? 500;
    send_json(['success' => false, 'error' => $result['error'] ?? 'Report generation failed.'], $statusCode);
} catch (Throwable $ex) {
    send_json(['success' => false, 'error' => 'Unhandled exception: ' . $ex->getMessage()], 500);
}


