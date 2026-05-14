<?php
// reports/generate_report.php

if (!function_exists('send_json')) {
    function send_json($payload, $status = 200) {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

require_once __DIR__ . '/../helpers/url_helper.php';

function get_curl_options($url) {
    return [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'WebsiteTestPortal/1.0',
        CURLOPT_HEADER => false,
    ];
}

function finalize_curl_handle($curl, $url, $body = null) {
    $body = $body ?? curl_multi_getcontent($curl);
    $errno = curl_errno($curl);
    $error = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $result = [
        'url' => $url,
        'body' => $body,
        'http_code' => $httpCode,
        'success' => false,
        'error' => '',
        'ssl_issue' => false,
        'timeout' => false,
        'dns' => false,
    ];

    if ($errno !== 0) {
        $result['error'] = $error ?: 'Unknown network error.';

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            $result['timeout'] = true;
            $result['error'] = 'Timeout while connecting to the website.';
        } elseif (in_array($errno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY])) {
            $result['dns'] = true;
            $result['error'] = 'DNS resolution failed.';
        } elseif (in_array($errno, [
            defined('CURLE_SSL_CONNECT_ERROR') ? CURLE_SSL_CONNECT_ERROR : 35,
            defined('CURLE_SSL_PEER_CERTIFICATE') ? CURLE_SSL_PEER_CERTIFICATE : 51,
            60, 51, 58, 77
        ])) {
            $result['ssl_issue'] = true;
            $result['error'] = 'SSL handshake failed: ' . $error;
        }

        return $result;
    }

    if ($httpCode < 100 || $httpCode >= 400) {
        $result['error'] = 'HTTP status ' . $httpCode . ' returned.';
        return $result;
    }

    $result['success'] = true;
    return $result;
}

function curl_fetch($url) {
    $curl = curl_init();
    curl_setopt_array($curl, get_curl_options($url));
    $body = curl_exec($curl);
    return finalize_curl_handle($curl, $url, $body);
}

function supports_curl_multi() {
    return function_exists('curl_multi_init')
        && function_exists('curl_multi_add_handle')
        && function_exists('curl_multi_exec')
        && function_exists('curl_multi_getcontent')
        && function_exists('curl_multi_remove_handle')
        && function_exists('curl_multi_select')
        && function_exists('curl_multi_close');
}

function build_page_url($baseUrl, $path = '/', $explicitUrl = '') {
    $explicitUrl = trim((string)$explicitUrl);
    if ($explicitUrl !== '') {
        return $explicitUrl;
    }

    $path = trim((string)$path);
    if ($path === '') {
        $path = '/';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $normalizedBase = rtrim($baseUrl, '/');
    return $normalizedBase . '/' . ltrim($path, '/');
}

function is_same_host($hrefHost, $baseHost) {
    if (!$hrefHost || !$baseHost) {
        return false;
    }
    return strcasecmp($hrefHost, $baseHost) === 0;
}

function get_link_check_config($baseUrl) {
    $linkChecksEnabled = strtolower(getenv('ENABLE_LINK_CHECKS') ?: 'true') === 'true';
    $limit = (int)(getenv('REPORT_LINK_CHECK_LIMIT') ?: 6);
    $limit = $linkChecksEnabled ? max(1, min(6, $limit)) : 0;
    $baseHost = parse_url($baseUrl, PHP_URL_HOST);

    return [
        'enabled' => $linkChecksEnabled,
        'limit' => $limit,
        'base_host' => $baseHost,
    ];
}

function fetch_pages_in_parallel(array $pages, $baseUrl, $chunkSize = null) {
    $results = [];
    if (empty($pages)) {
        return $results;
    }

    if (!supports_curl_multi()) {
        foreach ($pages as $pageIndex => $page) {
            $pageUrl = build_page_url($baseUrl, $page['path'] ?? '/', $page['url'] ?? '');
            $results[$pageIndex] = curl_fetch($pageUrl);
        }
        ksort($results);
        return $results;
    }

    $totalPages = count($pages);
    $chunkSize = is_null($chunkSize) ? max(1, min(6, $totalPages)) : max(1, min((int)$chunkSize, $totalPages));
    if ($chunkSize === 0) {
        $chunkSize = 1;
    }
    for ($offset = 0; $offset < $totalPages; $offset += $chunkSize) {
        $batch = array_slice($pages, $offset, $chunkSize, true);
        $multi = curl_multi_init();
        if ($multi === false) {
            foreach ($batch as $pageIndex => $page) {
                $pageUrl = build_page_url($baseUrl, $page['path'] ?? '/', $page['url'] ?? '');
                $results[$pageIndex] = curl_fetch($pageUrl);
            }
            continue;
        }

        $handles = [];
        foreach ($batch as $pageIndex => $page) {
            $pageUrl = build_page_url($baseUrl, $page['path'] ?? '/', $page['url'] ?? '');
            $curl = curl_init();
            curl_setopt_array($curl, get_curl_options($pageUrl));
            curl_multi_add_handle($multi, $curl);
            $handles[$pageIndex] = ['handle' => $curl, 'url' => $pageUrl];
        }

        $running = null;
        do {
            $mrc = curl_multi_exec($multi, $running);
            if ($mrc === CURLM_CALL_MULTI_PERFORM) {
                continue;
            }
            if ($running) {
                $ready = curl_multi_select($multi, 0.5);
                if ($ready === -1) {
                    usleep(100000);
                }
            }
        } while ($running && $mrc === CURLM_OK);

        foreach ($handles as $pageIndex => $info) {
            curl_multi_remove_handle($multi, $info['handle']);
            $results[$pageIndex] = finalize_curl_handle($info['handle'], $info['url']);
        }

        curl_multi_close($multi);
    }

    if (count($results) !== count($pages)) {
        foreach ($pages as $pageIndex => $page) {
            if (!isset($results[$pageIndex])) {
                $pageUrl = build_page_url($baseUrl, $page['path'] ?? '/', $page['url'] ?? '');
                $results[$pageIndex] = curl_fetch($pageUrl);
            }
        }
    }

    ksort($results);
    return $results;
}

function check_link_fast($href, $timeout = 3) {
    // Quick link check with timeout context
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => false,
            'method' => 'HEAD',
            'user_agent' => 'WebsiteTestPortal/1.0'
        ],
        'https' => [
            'timeout' => $timeout,
            'follow_location' => false,
            'method' => 'HEAD',
            'user_agent' => 'WebsiteTestPortal/1.0'
        ]
    ]);

    $previousErrorHandler = set_error_handler(function() { return true; }, E_WARNING | E_NOTICE);
    
    $headers = @get_headers($href, 1, $context);
    
    if ($previousErrorHandler !== null) {
        set_error_handler($previousErrorHandler);
    } else {
        restore_error_handler();
    }

    if (!$headers || !preg_match('/^HTTP\/\d\.\d\s+[23]\d\d/i', $headers[0])) {
        return false;
    }
    return true;
}

function analyze_content($url, $body, $sslIssueFlag, array $options = []) {
    $seo = [];
    $functional = [];
    $ssl = [];

    if ($sslIssueFlag) {
        $ssl[] = 'SSL issue detected, connection had TLS verification problems.';
    }

    if (!$body) {
        $functional[] = 'No content returned for analysis.';
        return ['seo' => $seo, 'functional' => $functional, 'ssl' => $ssl];
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $html = preg_match('/<meta\s+charset=/i', $body)
        ? $body
        : '<meta charset="utf-8">' . $body;
    $doc->loadHTML($html);

    $titleNodes = $doc->getElementsByTagName('title');
    if ($titleNodes->length === 0 || trim($titleNodes->item(0)->textContent) === '') {
        $seo[] = 'Missing meta title.';
    }

    $descFound = false;
    foreach ($doc->getElementsByTagName('meta') as $meta) {
        if (strtolower($meta->getAttribute('name')) === 'description' && trim($meta->getAttribute('content')) !== '') {
            $descFound = true;
            break;
        }
    }
    if (!$descFound) {
        $seo[] = 'Missing meta description.';
    }

    foreach ($doc->getElementsByTagName('img') as $img) {
        if (trim($img->getAttribute('alt')) === '') {
            $seo[] = 'Missing Alt tag for image: ' . trim($img->getAttribute('src'));
        }
    }

    $headings = [];
    for ($i = 1; $i <= 6; $i++) {
        foreach ($doc->getElementsByTagName('h' . $i) as $h) {
            $headings[] = $i;
        }
    }
    if (!empty($headings) && $headings[0] !== 1) {
        $seo[] = 'First heading is not H1.';
    }
    for ($i = 1; $i < count($headings); $i++) {
        if ($headings[$i] > $headings[$i-1] + 1) {
            $seo[] = 'Heading structure issue: h' . $headings[$i-1] . ' to h' . $headings[$i] . '.';
        }
    }

    $broken = [];
    $linkChecksEnabled = $options['enabled'] ?? true;
    $linkCheckLimit = $linkChecksEnabled ? max(1, min(6, (int)($options['limit'] ?? 6))) : 0;
    $baseHost = $options['base_host'] ?? '';
    $linkCount = 0;
    $linksSkipped = false;

    if ($linkChecksEnabled && $linkCheckLimit > 0) {
        foreach ($doc->getElementsByTagName('a') as $a) {
            if ($linkCount >= $linkCheckLimit) {
                $linksSkipped = true;
                break;
            }

            $href = trim($a->getAttribute('href'));
            if ($href === '' || strpos($href, '#') === 0 || stripos($href, 'mailto:') === 0) {
                continue;
            }

            if (!preg_match('/^https?:\/\//i', $href)) {
                $href = rtrim($url, '/') . '/' . ltrim($href, '/');
            }

            $hrefHost = parse_url($href, PHP_URL_HOST);
            if ($hrefHost && $baseHost && !is_same_host($hrefHost, $baseHost)) {
                continue;
            }

            // Use faster link checking with timeout
            if (!check_link_fast($href, 2)) {  // 2 second timeout per link
                $broken[] = $href;
            }

            $linkCount++;
        }
    }

    if (!empty($broken)) {
        $seo[] = 'Broken links: ' . implode(', ', array_slice($broken, 0, 5));
    }
    if ($linkChecksEnabled && $linksSkipped && count($broken) < $linkCheckLimit) {
        $seo[] = 'Link analysis stopped after ' . $linkCheckLimit . ' links checked (limit reached).';
    }

    $forms = $doc->getElementsByTagName('form');
    if ($forms->length === 0) {
        $functional[] = 'No form detected on the page.';
    } else {
        foreach ($forms as $idx => $form) {
            $requiredFound = false;
            $paymentFound = false;
            foreach ($form->getElementsByTagName('input') as $input) {
                if ($input->hasAttribute('required')) {
                    $requiredFound = true;
                }
                $iname = strtolower($input->getAttribute('name'));
                $itype = strtolower($input->getAttribute('type'));
                if (preg_match('/card|cc|cvc|cvv|payment|checkout/i', $iname) || in_array($itype, ['number', 'tel', 'email'])) {
                    $paymentFound = true;
                }
            }
            if (!$requiredFound) {
                $functional[] = 'Form #' . ($idx + 1) . ' has no required fields.';
            }
            if ($paymentFound) {
                $functional[] = 'Form #' . ($idx + 1) . ' contains payment-related fields; verify secure checkout.';
            }
        }
    }

    return ['seo' => $seo, 'functional' => $functional, 'ssl' => $ssl];
}

function find_wkhtmltopdf() {
    $candidates = [
        'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
        'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
        '/usr/local/bin/wkhtmltopdf',
        '/usr/bin/wkhtmltopdf',
        'wkhtmltopdf'
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            return $candidate;
        }
        if (function_exists('shell_exec')) {
            $resolved = trim(shell_exec('where ' . escapeshellarg($candidate) . ' 2>nul'));
            if ($resolved !== '' && file_exists($resolved) && is_executable($resolved)) {
                return $resolved;
            }
        }
    }

    return null;
}

function find_wkhtmltoimage() {
    $candidates = [
        'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltoimage.exe',
        'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltoimage.exe',
        '/usr/local/bin/wkhtmltoimage',
        '/usr/bin/wkhtmltoimage',
        'wkhtmltoimage',
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            return $candidate;
        }
        if (function_exists('shell_exec')) {
            $resolved = trim(shell_exec('where ' . escapeshellarg($candidate) . ' 2>nul'));
            if ($resolved !== '' && file_exists($resolved) && is_executable($resolved)) {
                return $resolved;
            }
        }
    }

    return null;
}

function check_ssl_certificate($url) {
    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT) ?: 443;

    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
        ],
    ]);

    $timeoutSeconds = max(1, min(5, (int)(getenv('REPORT_SSL_TIMEOUT') ?: 3)));
    
    // Use set_error_handler to suppress the stream_socket_client warning entirely
    $previousErrorHandler = set_error_handler(function() { return true; }, E_WARNING | E_NOTICE);
    
    $client = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
    
    // Restore previous error handler
    if ($previousErrorHandler !== null) {
        set_error_handler($previousErrorHandler);
    } else {
        restore_error_handler();
    }

    if ($client === false) {
        // Connection failed - this is non-fatal, just return warning
        // (e.g., website down, blocking connections, or network issues)
        return ['SSL certificate verification skipped: Unable to connect to server.'];
    }

    $params = stream_context_get_params($client);
    fclose($client);

    if (!isset($params['options']['ssl']['peer_certificate'])) {
        return ['SSL certificate not provided by host.'];
    }

    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    if (!$cert || empty($cert['validTo_time_t'])) {
        return ['Unable to parse SSL certificate details.'];
    }

    $days = floor(($cert['validTo_time_t'] - time()) / 86400);
    if ($days < 0) {
        return ['SSL certificate has expired.'];
    }
    if ($days < 14) {
        return ['SSL certificate expiring in ' . $days . ' days.'];
    }

    return [];
}

function capture_screenshot($url, $screenshotsDir, $label, $skipIfSlowMode = false) {
    if (!is_dir($screenshotsDir) && !mkdir($screenshotsDir, 0777, true)) {
        return ['success' => false, 'path' => null, 'error' => 'Unable to create screenshots folder.'];
    }

    $safeLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $label);
    $filename = 'screenshot_' . $safeLabel . '_' . time() . '.png';
    $path = $screenshotsDir . DIRECTORY_SEPARATOR . $filename;

    if (!function_exists('exec')) {
        return ['success' => false, 'path' => null, 'error' => 'Screenshot capture disabled because PHP exec() is unavailable.'];
    }

    $wkhtmltoimage = find_wkhtmltoimage();
    if (!$wkhtmltoimage) {
        $msg = $skipIfSlowMode ? 'wkhtmltoimage not installed (skipped due to time constraints).' : 'wkhtmltoimage not installed.';
        return ['success' => false, 'path' => null, 'error' => $msg];
    }

    // If in slow mode and we're running low on time, skip screenshot
    if ($skipIfSlowMode) {
        return ['success' => false, 'path' => null, 'error' => 'Screenshot skipped due to time constraints.'];
    }

    $cmd = escapeshellarg($wkhtmltoimage)
        . ' --width 900 --quality 60 --disable-smart-width --javascript-delay 1200 --load-error-handling ignore '
        . escapeshellarg($url) . ' ' . escapeshellarg($path) . ' 2>&1';

    try {
        if (function_exists('exec')) {
            exec($cmd, $output, $status);
        } else {
            return ['success' => false, 'path' => null, 'error' => 'Screenshot capture unavailable - exec() is disabled'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'path' => null, 'error' => 'Screenshot error: ' . $e->getMessage()];
    }

    if ($status !== 0 || !file_exists($path) || filesize($path) === 0) {
        @unlink($path);
        return ['success' => false, 'path' => null, 'error' => trim(implode(' ', $output)) ?: 'Screenshot capture failed'];
    }

    return ['success' => true, 'path' => $path, 'error' => ''];
}

function is_valid_pdf($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    
    $size = filesize($filePath);
    if ($size === false || $size < 100) {
        return false;
    }
    
    // PDF files must start with %PDF- magic bytes
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return false;
    }
    
    $header = fread($handle, 5);
    fclose($handle);
    
    // Valid PDF signature is %PDF-1.x (e.g., %PDF-1.4)
    if (strpos($header, '%PDF-') !== 0) {
        return false;
    }
    
    return true;
}

function convert_html_to_pdf($htmlPath, $pdfPath) {
    if (!function_exists('exec')) {
        return ['success' => false, 'error' => 'PDF generation disabled because PHP exec() is unavailable.', 'code' => 500];
    }

    $wkhtmltopdf = find_wkhtmltopdf();
    if (!$wkhtmltopdf) {
        return ['success' => false, 'error' => 'wkhtmltopdf executable not found or command resolution is unavailable.', 'code' => 500];
    }

    $cmd = escapeshellarg($wkhtmltopdf) . ' --enable-local-file-access --print-media-type '
        . escapeshellarg($htmlPath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1';

    try {
        if (function_exists('exec')) {
            exec($cmd, $output, $status);
        } else {
            return ['success' => false, 'error' => 'PDF generation unavailable - exec() is disabled', 'code' => 500];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'PDF error: ' . $e->getMessage(), 'code' => 500];
    }

    // Get detailed error info
    $errorOutput = trim(implode(' ', $output));
    
    // First check: did the command execute successfully?
    if ($status !== 0) {
        @unlink($pdfPath);
        return ['success' => false, 'error' => 'PDF generation failed (exit code ' . $status . '): ' . $errorOutput, 'code' => 500];
    }
    
    // Second check: does file exist?
    if (!file_exists($pdfPath)) {
        return ['success' => false, 'error' => 'PDF file was not created: ' . $errorOutput, 'code' => 500];
    }
    
    // Third check: is file size reasonable?
    $fileSize = filesize($pdfPath);
    if ($fileSize === false || $fileSize === 0) {
        @unlink($pdfPath);
        return ['success' => false, 'error' => 'PDF file is empty (0 bytes): ' . $errorOutput, 'code' => 500];
    }
    
    // Fourth check: is it a valid PDF?
    if (!is_valid_pdf($pdfPath)) {
        @unlink($pdfPath);
        return ['success' => false, 'error' => 'Generated file is not a valid PDF (magic bytes check failed): ' . $errorOutput, 'code' => 500];
    }

    return ['success' => true, 'code' => 200];
}

function generate_test_report($baseUrl, $pages, $testedBy, $automationChecks = null) {
    require_once __DIR__ . '/report_template.php';

    $reportDir = realpath(__DIR__) ?: __DIR__;
    $screenshotsDir = $reportDir . DIRECTORY_SEPARATOR . 'screenshots';
    $generatedDir = $reportDir . DIRECTORY_SEPARATOR . 'generated';

    if (!is_dir($screenshotsDir) && !mkdir($screenshotsDir, 0777, true)) {
        return ['success' => false, 'error' => 'Unable to create screenshots directory.', 'code' => 500];
    }
    if (!is_dir($generatedDir) && !mkdir($generatedDir, 0777, true)) {
        return ['success' => false, 'error' => 'Unable to create generated reports directory.', 'code' => 500];
    }

    $entries = [];
    $sumFunctional = 0;
    $sumSeo = 0;
    $sumSsl = 0;
    $allPassed = true;
    $sslCache = [];
    $timeLimit = 260;  // Leave 40 second buffer before 300 second limit
    $avgTimePerPage = $timeLimit / (count($pages) + 1);  // +1 for PDF generation

    $enableScreenshots = strtolower(getenv('ENABLE_SCREENSHOTS') ?: '') === 'true';
    $screenshotsSkippedReason = 'disabled by ENABLE_SCREENSHOTS=false';

    $overallStartTime = microtime(true);
    $pageFetchResults = fetch_pages_in_parallel($pages, $baseUrl);
    foreach ($pages as $pageIndex => $page) {
        $pageStartTime = microtime(true);
        $label = $page['label'] ?? 'Page';
        $path = $page['path'] ?? '/';
        $pageUrl = $pageFetchResults[$pageIndex]['url'] ?? build_page_url($baseUrl, $path, $page['url'] ?? '');
        $pageLinkOptions = get_link_check_config($pageUrl);

        // Check if we're running slow and need to skip screenshots
        $elapsedTime = microtime(true) - $overallStartTime;
        $timeUsedPerPage = $elapsedTime / ($pageIndex + 1);
        $isSlowMode = $timeUsedPerPage > ($avgTimePerPage * 0.9);  // 90% of budget
        
        try {
            // Wrap page testing in try-catch to continue on errors
            $fetch = $pageFetchResults[$pageIndex] ?? curl_fetch($pageUrl);
            $sslIssues = [];
            $isHttps = stripos($pageUrl, 'https://') === 0;
            $host = parse_url($pageUrl, PHP_URL_HOST);

            if (!$isHttps) {
                $sslIssues[] = 'HTTPS implementation not present for this page.';
            } elseif ($host) {
                if (!isset($sslCache[$host])) {
                    try {
                        $sslCache[$host] = check_ssl_certificate($pageUrl);
                    } catch (Exception $e) {
                        $sslCache[$host] = ['SSL check skipped due to error: ' . $e->getMessage()];
                    }
                }
                if (!empty($sslCache[$host])) {
                    $sslIssues = array_merge($sslIssues, $sslCache[$host]);
                }
            }

            try {
            $analysis = analyze_content($pageUrl, $fetch['body'] ?? '', !$fetch['success'] || $fetch['ssl_issue'], $pageLinkOptions);
            } catch (Exception $e) {
                $analysis = [
                    'seo' => ['Content analysis error: ' . $e->getMessage()],
                    'functional' => [],
                    'ssl' => []
                ];
            }

            $screenshotPath = null;
            $screenshotError = '';
            if ($enableScreenshots) {
                try {
                    $screenshot = capture_screenshot($pageUrl, $screenshotsDir, $label, $isSlowMode);
                    $screenshotPath = $screenshot['success'] ? $screenshot['path'] : null;
                    $screenshotError = $screenshot['error'];
                } catch (Exception $e) {
                    $screenshotError = 'Screenshot error: ' . $e->getMessage();
                }
            } else {
                $screenshotError = $screenshotsSkippedReason;
            }

            $finalSslIssues = array_values(array_unique(array_merge($analysis['ssl'], $sslIssues)));

            $issueCount = count($analysis['functional']) + count($analysis['seo']) + count($finalSslIssues);
            $status = $issueCount > 0 ? 'FAIL' : 'PASS';

            if ($issueCount > 0) {
                $allPassed = false;
            }

            $entries[] = [
                'label' => $label,
                'url' => $pageUrl,
                'http_code' => $fetch['http_code'],
                'fetched' => $fetch['success'],
                'fetch_error' => $fetch['error'],
                'screenshot_path' => $screenshotPath,
                'screenshot_error' => $screenshotError,
                'seo' => $analysis['seo'],
                'ssl' => $finalSslIssues,
                'functional' => $analysis['functional'],
                'status' => $status,
            ];

            $sumFunctional += count($analysis['functional']);
            $sumSeo += count($analysis['seo']);
            $sumSsl += count($finalSslIssues);
            
        } catch (Throwable $e) {
            // If a page test completely fails, still record it and continue
            $entries[] = [
                'label' => $label,
                'url' => $pageUrl,
                'http_code' => 0,
                'fetched' => false,
                'fetch_error' => 'Page test failed: ' . $e->getMessage(),
                'screenshot_path' => null,
                'screenshot_error' => 'Skipped',
                'seo' => [],
                'ssl' => [],
                'functional' => ['Test execution error: ' . $e->getMessage()],
                'status' => 'FAIL',
            ];
            $sumFunctional += 1;
            $allPassed = false;
        }
        
        // Check if we're still within time budget
        $pageElapsedTime = microtime(true) - $pageStartTime;
        if ($pageElapsedTime > $avgTimePerPage * 1.5) {
            // Page took significantly longer, log warning
            error_log("Page $label took " . number_format($pageElapsedTime, 2) . " seconds (budget: " . number_format($avgTimePerPage, 2) . ")");
        }
    }

    $automationChecks = is_array($automationChecks) ? $automationChecks : [];
    $automationChecks = array_merge([
        'entries' => [],
        'warnings' => [],
        'path' => '',
        'success' => false,
    ], $automationChecks);

    foreach ($automationChecks['entries'] as &$automationEntry) {
        $automationScreenshot = $automationEntry['screenshot_path'] ?? '';
        if ($automationScreenshot && file_exists($automationScreenshot)) {
            $destName = 'automation_' . basename($automationScreenshot);
            $destPath = $screenshotsDir . DIRECTORY_SEPARATOR . $destName;
            if (@copy($automationScreenshot, $destPath)) {
                $automationEntry['screenshot_path'] = $destPath;
                $automationEntry['screenshot_url'] = '../screenshots/' . $destName;
            }
        }
    }
    unset($automationEntry);

    foreach ($entries as &$entry) {
        if (!empty($entry['screenshot_path']) && file_exists($entry['screenshot_path'])) {
            continue;
        }

        $entryLabel = (string)($entry['label'] ?? '');
        foreach ($automationChecks['entries'] as $automationEntry) {
            $automationLabel = (string)($automationEntry['label'] ?? '');
            if ($entryLabel !== '' && strpos($automationLabel, $entryLabel . ' - Form discovery') === 0 && !empty($automationEntry['screenshot_path'])) {
                $entry['screenshot_path'] = $automationEntry['screenshot_path'];
                $entry['screenshot_error'] = '';
                break;
            }
        }
    }
    unset($entry);

    $automationFailures = 0;
    foreach ($automationChecks['entries'] as $automationEntry) {
        if (strcasecmp((string)($automationEntry['status'] ?? ''), 'FAIL') === 0) {
            $automationFailures++;
        }
    }
    if ($automationFailures > 0) {
        $allPassed = false;
    }

    $summary = [
        'total' => $sumFunctional + $automationFailures + $sumSeo + $sumSsl,
        'functional' => $sumFunctional + $automationFailures,
        'seo' => $sumSeo,
        'ssl' => $sumSsl,
        'pass' => $allPassed,
    ];

    $reportBaseName = 'test_report_' . date('Ymd_His');
    $reportHtmlName = $reportBaseName . '.html';
    $reportPdfName = $reportBaseName . '.pdf';
    $reportHtmlPath = $generatedDir . DIRECTORY_SEPARATOR . $reportHtmlName;
    $reportPdfPath = $generatedDir . DIRECTORY_SEPARATOR . $reportPdfName;

    try {
        $htmlContent = build_report_template($baseUrl, $testedBy, $entries, $summary, $automationChecks);

        // Primary write attempt
        if (@file_put_contents($reportHtmlPath, $htmlContent) === false) {
            // Try to ensure directory exists and retry
            @mkdir($generatedDir, 0777, true);
            if (@file_put_contents($reportHtmlPath, $htmlContent) === false) {
                // Attempt fallback to system temp directory (best-effort)
                $tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'testing_portal_reports';
                @mkdir($tempRoot, 0777, true);
                $tempReportHtmlPath = $tempRoot . DIRECTORY_SEPARATOR . $reportHtmlName;

                if (@file_put_contents($tempReportHtmlPath, $htmlContent) !== false) {
                    // Saved to temp - try to copy into a web-accessible uploads fallback so the report can be viewed
                    $uploadsDir = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
                    $uploadsFallbackRoot = $uploadsDir . DIRECTORY_SEPARATOR . 'reports_generated';
                    @mkdir($uploadsFallbackRoot, 0777, true);
                    $uploadsSub = $uploadsFallbackRoot . DIRECTORY_SEPARATOR . $reportBaseName;
                    @mkdir($uploadsSub, 0777, true);
                    $uploadsHtmlPath = $uploadsSub . DIRECTORY_SEPARATOR . $reportHtmlName;

                    if (@copy($tempReportHtmlPath, $uploadsHtmlPath)) {
                        // Return success but inform caller that primary location was not writable
                        return [
                            'success' => true,
                            'message' => 'Report generated to uploads fallback because reports/generated is not writable.',
                            'view_url' => '../uploads/reports_generated/' . $reportBaseName . '/' . $reportHtmlName,
                            'download_url' => '',
                            'pdf_path' => '',
                            'report_html' => '../uploads/reports_generated/' . $reportBaseName . '/' . $reportHtmlName,
                            'issues_total' => $summary['total'],
                            'seo_issues' => $sumSeo,
                            'ssl_issues' => $sumSsl,
                            'functional_issues' => $summary['functional'],
                            'pdf_engine' => 'html-only',
                            'screenshot_dir' => '../reports/screenshots/',
                            'code' => 200,
                            'warning' => 'reports/generated not writable; using uploads/reports_generated fallback.',
                            'automation_report_path' => $automationChecks['path'],
                            'automation_success' => $automationChecks['success'],
                            'automation_checks_count' => count($automationChecks['entries']),
                        ];
                    }

                    // Could not copy to uploads - return informative error with attempted paths
                    return [
                        'success' => false,
                        'error' => 'Permission denied writing HTML report to generated folder. Report saved to temporary path instead.',
                        'attempted_paths' => [$reportHtmlPath, $tempReportHtmlPath],
                        'suggestion' => 'Make the reports/generated directory writable by the webserver user (e.g., chmod 755/775) or configure a writable reports directory.',
                        'code' => 500,
                    ];
                }

                return ['success' => false, 'error' => 'Unable to write HTML report due to filesystem permissions.', 'attempted_paths' => [$reportHtmlPath], 'code' => 500];
            }
        }

        $pdfResult = convert_html_to_pdf($reportHtmlPath, $reportPdfPath);
        if (!$pdfResult['success']) {
            // If PDF generation is unavailable, still return the HTML report so the test can be reviewed.
            return [
                'success' => true,
                'message' => 'Report generated as HTML only. PDF generation is unavailable on this server.',
                'view_url' => '../reports/generated/' . basename($reportHtmlPath),
                'download_url' => '',
                'pdf_path' => '',
                'report_html' => '../reports/generated/' . basename($reportHtmlPath),
                'issues_total' => $summary['total'],
                'seo_issues' => $sumSeo,
                'ssl_issues' => $sumSsl,
                'functional_issues' => $summary['functional'],
                'pdf_engine' => 'html-only',
                'screenshot_dir' => '../reports/screenshots/',
                'code' => 200,
                'warning' => $pdfResult['error'] ?? 'PDF creation failed',
                'automation_report_path' => $automationChecks['path'],
                'automation_success' => $automationChecks['success'],
                'automation_checks_count' => count($automationChecks['entries']),
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Report generation failed: ' . $e->getMessage(), 'code' => 500];
    }

    return [
        'success' => true,
        'message' => 'Report generated',
        'view_url' => '../reports/generated/' . basename($reportHtmlPath),
        'download_url' => '../reports/generated/' . basename($reportPdfPath),
        'pdf_path' => 'reports/generated/' . basename($reportPdfPath),
        'issues_total' => $summary['total'],
        'seo_issues' => $sumSeo,
        'ssl_issues' => $sumSsl,
        'functional_issues' => $summary['functional'],
        'pdf_engine' => 'wkhtmltopdf',
        'screenshot_dir' => '../reports/screenshots/',
        'code' => 200,
        'report_html' => '../reports/generated/' . basename($reportHtmlName),
        'functional_checks_report' => $automationChecks,
        'automation_report_path' => $automationChecks['path'],
        'automation_success' => $automationChecks['success'],
        'automation_checks_count' => count($automationChecks['entries']),
    ];
}
