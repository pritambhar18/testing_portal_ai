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
            $link = '/reports/screenshots/' . htmlspecialchars($filename);
            $fileUrl = file_url_for_path($entry['screenshot_path']);
            $preview = $fileUrl ? '<img src="' . htmlspecialchars($fileUrl) . '" style="max-width:260px;border:1px solid #ccc;" />' : '<span>Preview unavailable</span>';
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
            . (empty($entry['functional']) ? '<p>None</p>' : '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $entry['functional'])) . '</li></ul>')
            . '<h4>SEO Issues</h4>'
            . (empty($entry['seo']) ? '<p>None</p>' : '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $entry['seo'])) . '</li></ul>')
            . '<h4>SSL Issues</h4>'
            . (empty($entry['ssl']) ? '<p>None</p>' : '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $entry['ssl'])) . '</li></ul>')
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
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($row['label'] ?? 'Check') . '</td>'
                . '<td><span class="' . (strcasecmp($row['status'] ?? '', 'PASS') === 0 ? 'pass' : 'fail') . '">'
                    . htmlspecialchars($row['status'] ?? '') . '</span></td>'
                . '<td>' . htmlspecialchars($row['detail'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($row['time'] ?? '') . '</td>'
                . '</tr>';
        }
        $automationSection .= '<table><thead><tr><th>Check</th><th>Status</th><th>Details</th><th>Time</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    } else {
        $automationSection .= '<p>No automated functional checks were executed.</p>';
    }

    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Website Test Report</title>'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#222;margin:16px;}'
        . 'h1,h2,h3,h4{color:#0b4f8b;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:8px;text-align:left;}'
        . 'th{background:#f3f3f3;}'
        . '.pass{color:#2a7b2a;font-weight:bold;}.fail{color:#c12a2a;font-weight:bold;}</style>'
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
