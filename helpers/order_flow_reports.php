<?php
// helpers/order_flow_reports.php

function order_flow_reports_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'order_flow_reports';
}

function order_flow_reports_index_path(): string
{
    return order_flow_reports_root() . DIRECTORY_SEPARATOR . 'index.json';
}

function ensure_order_flow_reports_root(): void
{
    $root = order_flow_reports_root();
    if (!is_dir($root)) {
        mkdir($root, 0777, true);
    }
}

function read_order_flow_reports_index(): array
{
    $path = order_flow_reports_index_path();
    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function write_order_flow_reports_index(array $reports): bool
{
    ensure_order_flow_reports_root();
    $json = json_encode(array_values($reports), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(order_flow_reports_index_path(), $json, LOCK_EX) !== false;
}

function get_order_flow_reports_paginated(int $page = 1, int $perPage = 10): array
{
    $reports = read_order_flow_reports_index();
    usort($reports, static function (array $a, array $b): int {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    $total = count($reports);
    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'reports' => array_slice($reports, $offset, $perPage),
        'total' => $total,
        'page' => $page,
        'pages' => $totalPages,
        'per_page' => $perPage,
    ];
}

function register_order_flow_report(array $report): bool
{
    $reports = read_order_flow_reports_index();
    $replaced = false;

    foreach ($reports as $index => $existingReport) {
        if (($existingReport['id'] ?? '') === ($report['id'] ?? '')) {
            $reports[$index] = $report;
            $replaced = true;
            break;
        }
    }

    if (!$replaced) {
        $reports[] = $report;
    }

    return write_order_flow_reports_index($reports);
}

function remove_order_flow_report(string $reportId): ?array
{
    $reports = read_order_flow_reports_index();
    $removed = null;
    $remaining = [];

    foreach ($reports as $report) {
        if (($report['id'] ?? '') === $reportId && $removed === null) {
            $removed = $report;
            continue;
        }
        $remaining[] = $report;
    }

    if ($removed !== null) {
        write_order_flow_reports_index($remaining);
    }

    return $removed;
}

function delete_order_flow_report_directory(string $absoluteDir): void
{
    if (!is_dir($absoluteDir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absoluteDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($absoluteDir);
}
