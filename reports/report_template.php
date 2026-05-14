<?php
// reports/report_template.php

function file_url_for_path($path) {
    $real = realpath($path);
    if (!$real) {
        return '';
    }
    $url = str_replace('\\', '/', $real);
    if (preg_match('/^[A-Za-z]:/', $url)) {
        $url = '/' . $url;
    }
    return 'file://' . $url;
}

function report_screenshot_preview($path, $maxWidth = 260, $href = '') {
    if (empty($path) || !file_exists($path)) {
        return '<span style="color:#a00;">Not available</span>';
    }

    $imgSrc = $href;
    if ($imgSrc === '') {
        $imgSrc = '../screenshots/' . basename($path);
    }
    $img = '<img src="' . htmlspecialchars($imgSrc) . '" style="max-width:' . intval($maxWidth) . 'px;max-height:180px;object-fit:contain;border:1px solid #d7dee8;border-radius:6px;background:#fff;padding:3px;" />';
    return '<a href="' . htmlspecialchars($imgSrc) . '" target="_blank" rel="noopener noreferrer">' . $img . '</a>';
}

function explain_issue(array $matchData) {
    // Accept either a string or associative array describing an issue.
    $issueText = is_array($matchData) ? ($matchData['message'] ?? implode(' ', $matchData)) : (string)$matchData;
    $lower = strtolower($issueText);
    $result = ['title' => $issueText, 'fix' => 'Investigate this issue and apply an appropriate fix.'];

    if (strpos($lower, 'missing meta title') !== false || strpos($lower, 'missing title') !== false) {
        $result['title'] = 'Missing page title (meta <title> tag).';
        $result['fix'] = 'Add a concise <title> element describing the page (50-60 characters). This improves SEO and user clarity.';
    } elseif (strpos($lower, 'missing meta description') !== false || strpos($lower, 'missing description') !== false) {
        $result['title'] = 'Missing meta description.';
        $result['fix'] = 'Add a meta description tag with a short summary (120-160 characters). Helpful for search snippets.';
    } elseif (strpos($lower, 'missing alt tag') !== false || strpos($lower, 'missing alt') !== false) {
        $result['title'] = 'Images missing alt text.';
        $result['fix'] = 'Provide meaningful alt attributes for images for accessibility and SEO. For decorative images use empty alt="".';
    } elseif (strpos($lower, 'first heading is not h1') !== false) {
        $result['title'] = 'Heading structure: first heading is not H1.';
        $result['fix'] = 'Ensure the primary page title uses an H1 and that heading levels follow a logical order (H2, H3...).';
    } elseif (strpos($lower, 'heading structure') !== false) {
        $result['title'] = 'Heading structure issue.';
        $result['fix'] = 'Fix inconsistent heading levels to maintain a semantic outline (do not skip levels).';
    } elseif (strpos($lower, 'no content returned') !== false) {
        $result['title'] = 'No content returned from the page.';
        $result['fix'] = 'Verify the URL is correct and the server returns HTML (check for redirects, auth requirements, or maintenance pages).';
    } elseif (strpos($lower, 'ssl') !== false || strpos($lower, 'tls') !== false || strpos($lower, 'certificate') !== false) {
        $result['title'] = 'SSL/TLS issue detected.';
        $result['fix'] = 'Check the site certificate chain, expiry, and TLS configuration. Use SSL checkers (e.g., Qualys SSL Labs) for details.';
    } elseif (strpos($lower, 'timeout') !== false) {
        $result['title'] = 'Request timed out.';
        $result['fix'] = 'The site did not respond in time. Check site availability, increase timeouts, or test from a different network.';
    } elseif (strpos($lower, 'http status') !== false || preg_match('/http status\s*\d{3}/i', $issueText)) {
        $result['title'] = 'Unexpected HTTP response code.';
        $result['fix'] = 'Check server response for that URL and ensure it returns a 2xx status for successful pages. Investigate server logs.';
    } elseif (strpos($lower, 'dns') !== false || strpos($lower, 'couldnt resolve') !== false) {
        $result['title'] = 'DNS resolution failed.';
        $result['fix'] = 'Ensure the domain resolves correctly and DNS records are configured; check propagation and DNS provider settings.';
    }

    return $result;
}

// Helper to render issues as friendly list items (global)
function render_issue_list($issues) {
    $out = '<ul style="margin-left:0;padding-left:1.1rem;">';
    foreach ($issues as $issue) {
        $expl = explain_issue(is_array($issue) ? $issue : ['message' => $issue]);
        $out .= '<li style="margin-bottom:8px;">'
            . '<strong>' . htmlspecialchars($expl['title']) . '</strong>'
            . '<div style="margin-top:4px;color:#334155;">' . htmlspecialchars(is_array($issue) && isset($issue['message']) ? $issue['message'] : (is_string($issue) ? $issue : json_encode($issue))) . '</div>'
            . '<div style="margin-top:6px;font-size:12px;color:#0f5132;background:#d1e7dd;padding:8px;border-radius:6px;">'
            . '<strong>How to fix:</strong> ' . htmlspecialchars($expl['fix']) . '</div>'
            . '</li>';
    }
    $out .= '</ul>';
    return $out;
}

function automation_check_explanation(array $row): array {
    $label = (string)($row['label'] ?? 'Check');
    $status = strtoupper((string)($row['status'] ?? ''));
    $detail = trim((string)($row['detail'] ?? ''));
    $lowerLabel = strtolower($label);
    $lowerDetail = strtolower($detail);
    $passed = $status === 'PASS';

    $summary = $detail !== '' ? $detail : 'No raw result detail was returned by the automation runner.';
    $reason = $passed
        ? 'The observed result matched the expected behavior for this checkpoint.'
        : 'The observed result did not match the expected behavior for this checkpoint.';
    $developerNote = $passed
        ? 'No developer action is required for this checkpoint unless the expected behavior has changed.'
        : 'Review the related field, validation rule, selector, or page behavior and update the implementation or test data as needed.';

    if (strpos($lowerLabel, 'form discovery') !== false) {
        if (preg_match('/forms\s*=\s*(\d+)/i', $detail, $match)) {
            $count = (int)$match[1];
            $summary = $count . ' form' . ($count === 1 ? '' : 's') . ' found on the page.';
            $reason = $passed
                ? 'PASS because at least one form was detected, so the page can be checked for functional form behavior.'
                : 'FAIL because no form was detected, so field and validation checks cannot run.';
        }
    } elseif (strpos($lowerLabel, 'field discovery') !== false) {
        if (preg_match('/fields\s*=\s*(\d+)/i', $detail, $match)) {
            $count = (int)$match[1];
            $summary = $count . ' testable field' . ($count === 1 ? '' : 's') . ' found in this form.';
            $reason = $passed
                ? 'PASS because the form contains visible, testable inputs for validation checks.'
                : 'FAIL because no visible, testable inputs were found in the form.';
        }
    } elseif (strpos($lowerLabel, 'blank validation') !== false) {
        $reason = $passed
            ? 'PASS because the field rejected or blocked an empty value as expected.'
            : 'FAIL because the field accepted a blank value or did not show the expected validation.';
    } elseif (strpos($lowerLabel, 'phone') !== false || strpos($lowerLabel, 'zip') !== false || strpos($lowerLabel, 'cvv') !== false || strpos($lowerLabel, 'card') !== false) {
        if (strpos($lowerDetail, 'character') !== false || strpos($lowerDetail, 'accepted') !== false) {
            $reason = $passed
                ? 'PASS because invalid characters were blocked or cleaned up as expected.'
                : 'FAIL because invalid characters were accepted.';
        } elseif (strpos($lowerDetail, 'length') !== false || strpos($lowerDetail, 'digits') !== false || strpos($lowerDetail, 'too long') !== false) {
            $reason = $passed
                ? 'PASS because the field enforced the expected length or digit rule.'
                : 'FAIL because the field allowed an invalid length or digit count.';
        }
    } elseif (strpos($lowerLabel, 'checkbox') !== false) {
        $reason = $passed
            ? 'PASS because the checkbox could be selected and its checked state was confirmed.'
            : 'FAIL because the checkbox could not be selected or did not stay checked.';
    }

    return [
        'summary' => $summary,
        'reason' => $reason,
        'developer_note' => $developerNote,
    ];
}

function build_report_template($baseUrl, $testedBy, $entries, $summary, $automationChecks = []) {
    $testDate = date('Y-m-d H:i:s');
    $entryRows = '';
    $pageTable = '';

    foreach ($entries as $entry) {
        $pageTable .= '<tr>'
            . '<td>' . htmlspecialchars($entry['label']) . '</td>'
            . '<td>' . htmlspecialchars($entry['url']) . '</td>'
            . '<td>' . htmlspecialchars($entry['status']) . '</td>'
            . '<td>' . htmlspecialchars($entry['http_code']) . '</td>'
            . '</tr>';

        $screenshotIcon = '';
        if ($entry['screenshot_path'] && file_exists($entry['screenshot_path'])) {
            $filename = basename($entry['screenshot_path']);
            $link = '../screenshots/' . htmlspecialchars($filename);
            $preview = '<img src="' . $link . '" style="max-width:420px;max-height:260px;object-fit:contain;border:1px solid #d7dee8;border-radius:6px;background:#fff;padding:3px;" />';
            $screenshotIcon = '<a href="' . $link . '" target="_blank" rel="noopener noreferrer">' . $preview . '</a>'
                . '<br /><small><a href="' . $link . '" target="_blank" rel="noopener noreferrer">Open full screenshot</a></small>';
        } else {
            $screenshotIcon = '<span style="color:#a00;">Not available (' . htmlspecialchars($entry['screenshot_error']) . ')</span>';
        }

        $screenshotRow = '<p>Screenshot: ' . $screenshotIcon . '</p>';

        $entryRows .= '<section style="margin-bottom:24px;">'
            . '<h3>' . htmlspecialchars($entry['label']) . '</h3>'
            . '<p><strong>URL:</strong> ' . htmlspecialchars($entry['url']) . '</p>'
            . '<p><strong>Status:</strong> <strong>' . htmlspecialchars($entry['status']) . '</strong></p>'
            . $screenshotRow
            . '<h4>Functional Issues</h4>'
            . (empty($entry['functional']) ? '<p>None</p>' : render_issue_list($entry['functional']))
            . '<h4>SEO Issues</h4>'
            . (empty($entry['seo']) ? '<p>None</p>' : render_issue_list($entry['seo']))
            . '<h4>SSL Issues</h4>'
            . (empty($entry['ssl']) ? '<p>None</p>' : render_issue_list($entry['ssl']))
            . '</section>';
    }

    $overallResult = $summary['pass'] ? 'PASS' : 'FAIL';

    $automationEntries = $automationChecks['entries'] ?? [];
    $automationWarnings = $automationChecks['warnings'] ?? [];
    $automationPath = $automationChecks['path'] ?? '';
    $automationSection = '<h2>Functional Automation Checklist</h2>';

    if (!empty($automationPath)) {
        $automationSection .= '<p>Automation report file: ' . htmlspecialchars($automationPath) . '</p>';
    }

    if (!empty($automationWarnings)) {
        $warningList = '<ul>';
        foreach ($automationWarnings as $warning) {
            $warningList .= '<li>' . htmlspecialchars($warning) . '</li>';
        }
        $warningList .= '</ul>';
        $automationSection .= '<div style="padding: 12px; background:#fff3cd; border:1px solid #ffeeba; margin-bottom:12px;">'
            . '<strong>Warnings:</strong> ' . $warningList
            . '</div>';
    }

    if (!empty($automationEntries)) {
        $rows = '';
        foreach ($automationEntries as $row) {
            $screenshotCell = report_screenshot_preview($row['screenshot_path'] ?? '', 220, $row['screenshot_url'] ?? '');
            $explanation = automation_check_explanation($row);
            $detailHtml = '<strong>' . htmlspecialchars($explanation['summary']) . '</strong>'
                . '<br /><small><strong>Result reason:</strong> ' . htmlspecialchars($explanation['reason']) . '</small>'
                . '<br /><small><strong>Developer note:</strong> ' . htmlspecialchars($explanation['developer_note']) . '</small>';

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($row['label'] ?? 'Check') . '</td>'
                . '<td><span class="' . (strcasecmp($row['status'] ?? '', 'PASS') === 0 ? 'pass' : 'fail') . '">'
                    . htmlspecialchars($row['status'] ?? '') . '</span></td>'
                . '<td>' . $detailHtml . '</td>'
                . '<td>' . $screenshotCell . '</td>'
                . '<td>' . htmlspecialchars($row['time'] ?? '') . '</td>'
                . '</tr>';
        }
        $automationSection .= '<table><thead><tr><th>Check</th><th>Status</th><th>Details</th><th>Screenshot</th><th>Time</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    } else {
        $automationSection .= '<p>No automated functional checks were executed.</p>';
    }


    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Website Test Report</title>'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#222;margin:16px;}'
        . 'h1,h2,h3,h4{color:#0b4f8b;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:8px;text-align:left;vertical-align:top;}'
        . 'th{background:#f3f3f3;}'
        . '.pass{color:#2a7b2a;font-weight:bold;}.fail{color:#c12a2a;font-weight:bold;}'
        . 'ul{margin:0;padding:0;list-style:disc outside;}'
        . '</style>'
        . '</head><body>'
        . '<h1>Website Test Report</h1>'
        . '<p><strong>Tested URL:</strong> ' . htmlspecialchars($baseUrl) . '</p>'
        . '<p><strong>Test Date:</strong> ' . htmlspecialchars($testDate) . '</p>'
        . '<p><strong>Tested By:</strong> ' . htmlspecialchars($testedBy) . '</p>'
        . '<p><strong>Overall Result:</strong> <span class="' . ($summary['pass'] ? 'pass' : 'fail') . '">' . $overallResult . '</span></p>'
        . '<h2>Tested Pages</h2>'
        . '<table><thead><tr><th>Page</th><th>URL</th><th>Status</th><th>HTTP Code</th></tr></thead><tbody>'
        . $pageTable
        . '</tbody></table>'
        . '<h2>Issue Detection Summary</h2>'
        . '<p>Total Issues: ' . intval($summary['total']) . '</p>'
        . '<p>Functional: ' . intval($summary['functional']) . ' | SEO: ' . intval($summary['seo']) . ' | SSL: ' . intval($summary['ssl']) . '</p>'
        . $automationSection
        . '<div>' . $entryRows . '</div>'
        . '</body></html>';

    return $html;
}
