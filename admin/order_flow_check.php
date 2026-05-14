<?php
// admin/order_flow_check.php
session_start();
ini_set('max_execution_time', '0');
set_time_limit(0);

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$reportsPerPage = 10;
require_once __DIR__ . '/../helpers/order_flow_reports.php';
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$pagination = get_order_flow_reports_paginated($currentPage, $reportsPerPage);
$reports = $pagination['reports'];
$totalReports = $pagination['total'];
$currentPage = $pagination['page'];
$totalPages = $pagination['pages'];

function browser_display_name(?string $browser): string
{
    $normalized = strtolower(trim((string)$browser));
    $labels = [
        'msedge' => 'Edge',
        'edge' => 'Edge',
        'chrome' => 'Chrome',
        'chromium' => 'Chromium',
        'firefox' => 'Firefox',
        'webkit' => 'WebKit',
    ];

    return $labels[$normalized] ?? ($browser ? ucfirst($browser) : 'Chromium');
}

session_write_close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>88Startech QA Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <!-- Ensure all icons and tooltips are styled consistently -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
      :root {
        --qa-ink: #142033;
        --qa-muted: #66768d;
        --qa-line: rgba(214, 224, 236, 0.95);
        --qa-panel: rgba(255, 255, 255, 0.94);
        --qa-shadow: 0 20px 52px rgba(15, 23, 42, 0.08);
        --qa-accent: #0f766e;
        --qa-gold: #f59e0b;
      }

      body {
        background:
          radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 24%),
          radial-gradient(circle at bottom right, rgba(20, 32, 51, 0.06), transparent 28%),
          linear-gradient(180deg, #f4f7fb 0%, #edf3f8 100%);
      }

      main.content {
        min-height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: calc(var(--header-height) + 1rem) 0 0;
        display: flex;
        flex-direction: column;
      }

      .flow-shell {
        display: grid;
        grid-template-rows: auto auto 1fr;
        gap: 0;
        height: 100%;
        min-height: 0;
        flex: 1;
      }

      .qa-page-head {
        position: relative;
        margin-top: 0;
        z-index: 50;
        left: 0;
        right: 0;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr);
        gap: 1.25rem;
        padding: 1.4rem 1.5rem;
        border-radius: 0;
        background:
          linear-gradient(135deg, rgba(19, 42, 69, 0.98) 0%, rgba(17, 82, 104, 0.94) 55%, rgba(15, 118, 110, 0.88) 100%);
        box-shadow: 0 4px 12px rgba(19, 42, 69, 0.15);
        color: #fff;
        flex-shrink: 0;
      }

      .qa-page-head::after {
        content: "";
        position: absolute;
        inset: auto -70px -80px auto;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.07);
      }

      .qa-page-head h3 {
        margin: 0;
        font-size: 1.9rem;
        font-weight: 800;
        letter-spacing: 0;
      }

      .qa-page-copy {
        margin-top: 0.6rem;
        max-width: 720px;
        color: rgba(255, 255, 255, 0.76);
        font-size: 0.95rem;
        line-height: 1.6;
      }

      .qa-head-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        align-self: stretch;
      }

      .qa-head-tile {
        position: relative;
        z-index: 1;
        min-width: 0;
        padding: 0.9rem 0.95rem;
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
      }

      .qa-head-tile span {
        display: block;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.68);
        font-weight: 700;
      }

      .qa-head-tile strong {
        display: block;
        margin-top: 0.45rem;
        font-size: 0.95rem;
        line-height: 1.35;
        overflow-wrap: anywhere;
      }

      .flow-toolbar,
      .report-panel {
        border: 1px solid var(--qa-line);
        border-radius: 0;
        background: var(--qa-panel);
        box-shadow: none;
        backdrop-filter: blur(10px);
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
        margin: 0 1.5rem;
      }

      .flow-toolbar {
        margin: 1rem 1.5rem 0 1.5rem;
        background:
          linear-gradient(135deg, rgba(19, 42, 69, 0.98) 0%, rgba(15, 118, 110, 0.88) 100%),
          #132a45;
        color: #fff;
        flex-shrink: 0;
        border-radius: 8px;
      }

      .report-panel {
        margin: 1rem 1.5rem;
        overflow: hidden;
        flex: 1;
        min-height: 0;
        border-radius: 8px;
      }

      .flow-toolbar .card-body,
      .report-panel .card-body {
        padding: 1.4rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-height: 0;
        flex: 1;
      }

      /* Tooltip styling */
      [data-tooltip] {
        position: relative;
        cursor: pointer;
        border-bottom: 1px dotted #0f766e;
        transition: border-color 0.2s;
      }

      [data-tooltip]:hover {
        border-bottom: 1px solid #0f766e;
      }

      [data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        padding: 0.5rem 0.75rem;
        background: #142033;
        color: #fff;
        font-size: 0.75rem;
        border-radius: 6px;
        white-space: nowrap;
        z-index: 100;
        box-shadow: 0 4px 12px rgba(20, 32, 51, 0.2);
        font-weight: 600;
        pointer-events: none;
      }

      [data-tooltip]:hover::before {
        content: "";
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 4px solid #142033;
        z-index: 100;
        pointer-events: none;
      }

      [data-tooltip][data-bs-toggle="tooltip"]::after,
      [data-tooltip][data-bs-toggle="tooltip"]::before {
        display: none !important;
      }

      .flow-toolbar .text-muted,
      .flow-toolbar .helper-copy {
        color: rgba(255, 255, 255, 0.76) !important;
      }

      .flow-toolbar-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-shrink: 0;
      }

      .flow-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 700;
        border: 1px solid rgba(255, 255, 255, 0.18);
        white-space: nowrap;
      }

      .flow-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        align-items: start;
        flex-shrink: 0;
      }

      .field-shell {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
      }

      .field-shell label {
        display: block;
        margin-bottom: 0.5rem;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
      }

      .field-shell .form-control {
        border: 0;
        background: transparent;
        padding: 0;
        box-shadow: none;
        color: #fff;
        min-height: 24px;
      }

      .field-shell.browser-field .form-select,
      .field-shell .form-control {
        width: 100%;
      }

      .field-shell .form-check {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.2rem;
      }

      .field-shell .form-control::placeholder {
        color: rgba(255, 255, 255, 0.5);
      }

      .field-shell .form-control option {
        color: #1a2435;
      }

      .field-shell.browser-field {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.24);
      }

      .field-shell.browser-field .form-select {
        min-height: 42px;
        padding: 0.45rem 2.3rem 0.45rem 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.96);
        border-radius: 8px;
        background-color: rgba(255, 255, 255, 0.98);
        color: #132a45;
        box-shadow: none;
        background-position: right 0.65rem center;
        font-weight: 800;
        cursor: pointer;
      }

      .flow-actions {
        display: grid;
        grid-column: 1 / -1;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
        align-self: stretch;
        flex-shrink: 0;
      }

      .run-button,
      .stop-button {
        height: 52px;
        width: 100%;
        min-width: 0;
        border-radius: 12px;
        font-weight: 800;
        border: 0;
        font-size: 0.95rem;
      }

      .flow-actions .btn {
        height: 48px;
      }

      .run-button {
        background: linear-gradient(135deg, #f4c95d 0%, #f59e0b 100%);
        color: #192432;
        box-shadow: 0 14px 28px rgba(245, 158, 11, 0.28);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
      }

      .stop-button {
        background: rgba(255, 255, 255, 0.94);
        color: #991b1b;
        box-shadow: 0 14px 28px rgba(127, 29, 29, 0.16);
      }

      .stop-button:disabled {
        opacity: 0.58;
        cursor: not-allowed;
        box-shadow: none;
      }

      .run-button:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(245, 158, 11, 0.34);
      }

      .helper-copy {
        margin-top: 0.5rem;
        font-size: 0.85rem;
        flex-shrink: 0;
      }

      .status-panel {
        display: none;
        margin-top: 0.5rem;
        border-radius: 8px;
        flex-shrink: 0;
      }

      .status-panel pre {
        margin: 0.5rem 0 0;
        white-space: pre-wrap;
      }

      .report-meta {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding-bottom: 0.9rem;
        border-bottom: 1px solid #ebf0f6;
        flex-shrink: 0;
      }

      .report-count {
        color: #546276;
        font-size: 0.9rem;
      }

      .report-count strong {
        display: block;
        color: #132a45;
        font-size: 1.5rem;
        line-height: 1.1;
        font-weight: 800;
      }

      .report-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 0.8rem;
        flex-shrink: 0;
      }

      .summary-tile {
        padding: 0.85rem 1rem;
        border: 1px solid #e2eaf3;
        border-radius: 8px;
        background: linear-gradient(180deg, #fbfdff 0%, #f3f7fb 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.78);
      }

      .summary-tile span {
        display: block;
        color: #6f7f93;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .summary-tile strong {
        display: block;
        margin-top: 0.4rem;
        color: #132a45;
        font-size: 1.35rem;
        font-weight: 800;
      }

      .table-wrap {
        border: 1px solid #e6edf5;
        border-radius: 8px;
        overflow: auto;
        max-height: min(58vh, 620px);
        background: #fff;
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
      }

      .report-table {
        table-layout: fixed;
        width: 100%;
        font-size: 0.85rem;
        border-collapse: collapse;
      }

      .table thead th {
        background: linear-gradient(135deg, #f8fafd 0%, #f0f5fa 100%);
        color: #3c4b5f;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 2px solid #d9e4f0;
        white-space: nowrap;
        padding: 0.8rem 0.6rem;
        position: sticky;
        top: 0;
        z-index: 10;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .table td {
        vertical-align: middle;
        padding: 0.75rem 0.6rem;
        font-size: 0.85rem;
        color: var(--qa-ink);
        border-bottom: 1px solid #eef2f8;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .table tbody tr {
        transition: background-color 0.15s ease;
      }

      .table tbody tr:nth-child(even) {
        background: #f9fbfd;
      }

      .table tbody tr:hover {
        background: #f0f7ff;
      }

      /* Icon-based column styling */
      .col-icon {
        width: 2.2rem;
        text-align: center;
        font-size: 1.2rem;
        color: #0f766e;
      }

      .col-icon-text {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #0f766e;
      }

      .status-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.8rem;
        height: 1.8rem;
        border-radius: 50%;
        font-size: 0.85rem;
      }

      .status-success {
        background: #d1fae5;
        color: #065f46;
      }

      .status-failed {
        background: #fee2e2;
        color: #991b1b;
      }

      .status-pending {
        background: #fef3c7;
        color: #92400e;
      }

      .sl-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.8rem;
        height: 1.8rem;
        border-radius: 6px;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        font-weight: 800;
        font-size: 0.78rem;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.12);
      }

      .report-table .sl-col {
        width: 50px;
      }

      .report-table .id-col {
        width: 90px;
      }

      .report-table .offer-col {
        width: 100px;
      }

      .report-table .url-col {
        width: 180px;
      }

      .report-table .time-col {
        width: 140px;
      }

      .report-table .browser-col {
        width: 80px;
      }

      .report-table .stats-col {
        width: 100px;
      }

      .report-table .actions-col {
        width: 120px;
      }

      .browser-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        margin-top: 0.3rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        background: linear-gradient(135deg, #e0f2fe 0%, #cffafe 100%);
        color: #0369a1;
        font-size: 0.7rem;
        font-weight: 700;
        box-shadow: 0 1px 3px rgba(3, 105, 161, 0.1);
        white-space: nowrap;
      }

      .report-id-text {
        display: block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
        font-size: 0.8rem;
        font-weight: 600;
      }

      .time-stamp {
        font-weight: 700;
        color: #132a45;
        font-size: 0.8rem;
        line-height: 1.2;
      }

      .time-meta {
        margin-top: 0.25rem;
        color: #627286;
        font-size: 0.72rem;
        line-height: 1.2;
      }

      .report-url {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
        font-size: 0.8rem;
      }

      .action-group {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
        justify-content: center;
      }

      .action-group .btn {
        border-radius: 6px;
        padding: 0.3rem 0.5rem;
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
        min-height: 26px;
        height: auto;
      }

      .action-group .btn i {
        font-size: 0.75rem;
      }

      .empty-state {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #6f7f93;
      }

      .pagination-wrap {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-top: 0.8rem;
        padding-top: 0.8rem;
        border-top: 1px solid #eef2f8;
        flex-shrink: 0;
      }

      .pagination-note {
        color: #6e7d91;
        font-size: 0.85rem;
      }

      .container-fluid {
        padding: 0;
      }

      @media (max-width: 1200px) {
        .flow-form {
          grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .report-table .url-col {
          width: 240px;
        }

        .report-table .time-col {
          width: 150px;
        }

        .qa-page-head {
          grid-template-columns: 1fr;
        }

        .qa-head-meta {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 991px) {
        .flow-form {
          grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .flow-actions {
          grid-column: 1 / -1;
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .report-summary {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .report-meta {
          flex-direction: column;
        }

        .report-table .url-col {
          width: 200px;
        }

        .report-table .actions-col {
          width: 120px;
        }
      }

      @media (max-width: 768px) {
        main.content {
          padding: calc(var(--header-height) + 1rem) 1rem 1rem;
          height: auto;
        }

        .qa-page-head {
          padding: 1.2rem;
        }

        .qa-head-meta {
          grid-template-columns: 1fr;
        }

        .flow-form {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .flow-actions {
          grid-template-columns: 1fr;
        }

        .flow-toolbar .card-body,
        .report-panel .card-body {
          padding: 1rem;
        }

        .report-summary {
          grid-template-columns: 1fr;
        }

        .report-table {
          font-size: 0.82rem;
        }

        .table thead th {
          padding: 0.75rem 0.6rem;
        }

        .table td {
          padding: 0.7rem 0.6rem;
        }

        .report-table .sl-col,
        .report-table .id-col,
        .report-table .offer-col,
        .report-table .url-col,
        .report-table .time-col,
        .report-table .actions-col {
          width: auto;
        }

        .pagination-wrap {
          flex-direction: column;
          align-items: stretch;
        }
      }

      @media (max-width: 600px) {
        .flow-form {
          grid-template-columns: 1fr;
        }

        .action-group {
          flex-direction: column;
        }

        .action-group .btn {
          width: 100%;
        }
      }
    </style>
  </head>
  <body>
    <header class="topbar d-flex align-items-center px-3">
      <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
        <i class="bi bi-list"></i>
      </button>
      <div class="brand">Testing Portal</div>
      <div class="top-actions ms-auto">
        <a href="../actions/logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
      </div>
    </header>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="content">
      <div class="container-fluid">
        <?php if ($success): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>



        <div class="flow-shell">
          <section class="card flow-toolbar">
            <div class="card-body">
              <div class="flow-toolbar-header">
                <div>
                  <h5 class="card-title mb-1">88Startech QA Workspace</h5>
                  <p class="text-muted mb-0">Run the current 88Startech validation flow here and keep the same workspace ready for future console, functional, and security checks.</p>
                </div>
                <span class="flow-chip">
                  <i class="bi bi-ui-checks-grid"></i>
                  Project Workspace
                </span>
              </div>

              <form class="flow-form" id="orderFlowForm" enctype="multipart/form-data">
                <div class="field-shell">
                  <label for="offer_name">Offer Name</label>
                  <input id="offer_name" name="offer_name" type="text" class="form-control" placeholder="Enter offer name" required>
                </div>

                <div class="field-shell">
                  <label for="order_flow_url">URL</label>
                  <input id="order_flow_url" name="order_flow_url" type="url" class="form-control" placeholder="Enter website URL" required>
                </div>

                <div class="field-shell">
                  <label for="order_flow_csv">Upload CSV</label>
                  <input id="order_flow_csv" name="order_flow_csv" type="file" class="form-control" accept=".csv" required>
                </div>

                <div class="field-shell browser-field">
                  <label for="order_flow_browser">Browser</label>
                  <select id="order_flow_browser" name="browser" class="form-select" aria-label="Choose browser">
                    <option value="chrome">Chrome</option>
                    <option value="msedge" selected>Edge</option>
                    <option value="chromium">Chromium</option>
                    <option value="firefox">Firefox</option>
                    <option value="webkit">WebKit</option>
                  </select>
                </div>

                <div class="field-shell">
                  <div class="form-check">
                    <input id="order_flow_headless" name="headless" type="checkbox" class="form-check-input" checked>
                    <label class="form-check-label" for="order_flow_headless">Run browser in headless mode</label>
                  </div>
                </div>

                <div class="flow-actions">
                  <button type="submit" class="btn btn-primary run-button" id="runOrderFlowButton">
                    <i class="bi bi-play-circle me-1"></i>Run Test
                  </button>
                  <button type="button" class="btn stop-button" id="stopOrderFlowButton" disabled>
                    <i class="bi bi-stop-circle me-1"></i>Stop
                  </button>
                </div>
              </form>

              <p class="helper-copy mb-0">This checks area is separate from the `View Reports` module. CSV upload accepts only `.csv` files. Update selectors in `order_placement/locators.json` when site locators change.</p>
              <div class="alert alert-info status-panel" id="orderFlowStatus"></div>
            </div>
          </section>

          <section class="card report-panel">
            <div class="card-body">
              <div class="report-meta">
                <div>
                  <h5 class="card-title mb-1">88Startech QA Reports</h5>
                  <p class="text-muted mb-0">These 88Startech QA reports are kept separate from the `View Reports` section.</p>
                </div>
                <span class="report-count">
                  <strong><?php echo number_format($totalReports); ?></strong>
                  Total reports archived
                </span>
              </div>

              <div class="report-summary">
                <div class="summary-tile">
                  <span>Page</span>
                  <strong><?php echo (int)$currentPage; ?> / <?php echo max(1, (int)$totalPages); ?></strong>
                </div>
                <div class="summary-tile">
                  <span>Visible Reports</span>
                  <strong><?php echo count($reports); ?></strong>
                </div>
                <div class="summary-tile">
                  <span>Per Page</span>
                  <strong><?php echo (int)$reportsPerPage; ?></strong>
                </div>
              </div>

              <div class="table-wrap table-responsive">
                <table class="table table-hover align-middle mb-0 report-table">
                  <colgroup>
                    <col class="sl-col">
                    <col class="id-col">
                    <col class="offer-col">
                    <col class="url-col">
                    <col class="browser-col">
                    <col class="time-col">
                    <col class="stats-col">
                    <col class="actions-col">
                  </colgroup>
                  <thead>
                    <tr>
                      <th>#</th>
                      <th title="Report Identifier">Report ID</th>
                      <th title="Offer/Deal Name">Offer</th>
                      <th title="Test URL"><i class="bi bi-link-45deg"></i></th>
                      <th title="Browser"><i class="bi bi-browser-chrome"></i></th>
                      <th title="Execution Time"><i class="bi bi-clock"></i></th>
                      <th title="Stats"><i class="bi bi-graph-up"></i></th>
                      <th title="Actions"><i class="bi bi-gear"></i></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($reports)): ?>
                      <tr>
                        <td colspan="8">
                          <div class="empty-state">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No 88Startech QA reports available.
                          </div>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($reports as $index => $report): 
                        $placedOrders = (int)($report['placed_orders'] ?? 0);
                        $failedOrders = (int)($report['failed_orders'] ?? 0);
                        $totalRows = (int)($report['total_rows'] ?? 0);
                        $successRate = $totalRows > 0 ? round(($placedOrders / $totalRows) * 100, 0) : 0;
                      ?>
                        <tr>
                          <td class="col-icon">
                            <span class="sl-badge"><?php echo (int)(($currentPage - 1) * $reportsPerPage + $index + 1); ?></span>
                          </td>
                          <td>
                            <span class="report-id-text" data-tooltip="<?php echo htmlspecialchars($report['id'] ?? 'N/A'); ?>">
                              <?php echo htmlspecialchars(substr($report['id'] ?? '', 0, 12)); ?>
                            </span>
                          </td>
                          <td>
                            <span class="report-id-text" data-tooltip="<?php echo htmlspecialchars($report['offer_name'] ?? 'N/A'); ?>">
                              <?php echo htmlspecialchars(substr($report['offer_name'] ?? '-', 0, 12)); ?>
                            </span>
                          </td>
                          <td>
                            <span class="report-url" data-tooltip="<?php echo htmlspecialchars($report['url'] ?? ''); ?>" tabindex="0">
                              <i class="bi bi-link-45deg" style="color: #0f766e; font-size: 0.85rem;"></i>
                              <span style="max-width: 80px; display: inline-block; overflow: hidden; text-overflow: ellipsis; font-size: 0.8rem;" class="report-link-text">
                                <?php 
                                  $url = $report['url'] ?? '';
                                  $domain = preg_replace('#^https?://#', '', $url);
                                  $domain = preg_replace('#/.*#', '', $domain);
                                  echo htmlspecialchars(substr($domain, 0, 10));
                                ?>
                              </span>
                            </span>
                          </td>
                          <td class="col-icon">
                            <span class="browser-pill" data-tooltip="<?php echo htmlspecialchars(browser_display_name($report['browser'] ?? 'chromium')); ?>">
                              <?php 
                                $browserName = browser_display_name($report['browser'] ?? 'chromium');
                                $browserIcon = match(strtolower($browserName)) {
                                  'chrome' => 'bi-browser-chrome',
                                  'edge' => 'bi-browser-edge',
                                  'firefox' => 'bi-firefox',
                                  'webkit' => 'bi-browser-safari',
                                  default => 'bi-browser-chrome'
                                };
                              ?>
                              <i class="bi <?php echo $browserIcon; ?>"></i>
                            </span>
                          </td>
                          <td>
                            <div class="time-stamp" data-tooltip="<?php 
                              if (!empty($report['created_at'])) {
                                  $parsed = strtotime((string)$report['created_at']);
                                  if ($parsed !== false) {
                                      echo htmlspecialchars(date('d M Y h:i A', $parsed));
                                  }
                              }
                            ?>">
                              <?php 
                                $displayDate = '-';
                                if (!empty($report['created_at'])) {
                                    $parsed = strtotime((string)$report['created_at']);
                                    if ($parsed !== false) {
                                        $displayDate = date('d M', $parsed);
                                    }
                                }
                                echo htmlspecialchars($displayDate);
                              ?>
                            </div>
                            <div class="time-meta">
                              <?php echo !empty($report['created_at']) ? date('H:i', strtotime($report['created_at'])) : '-'; ?>
                            </div>
                          </td>
                          <td class="col-icon">
                            <div class="col-icon-text" data-tooltip="Total: <?php echo $totalRows; ?> | Placed: <?php echo $placedOrders; ?> | Failed: <?php echo $failedOrders; ?>">
                              <span class="status-icon status-success" style="font-size: 0.7rem; width: 1.6rem; height: 1.6rem; line-height: 1.6rem;">
                                <i class="bi bi-check"></i>
                              </span>
                              <span style="font-size: 0.75rem; font-weight: 700; color: #065f46;">
                                <?php echo $successRate; ?>%
                              </span>
                            </div>
                          </td>
                          <td class="col-icon">
                            <div class="action-group" style="justify-content: center; gap: 0.2rem;">
                              <?php
                                $reportId = (string)($report['id'] ?? '');
                                $reportFileUrl = '../actions/order_flow_report_file.php?id=' . urlencode($reportId);
                                $reportDownloadUrl = $reportFileUrl . '&download=1';
                              ?>
                              <?php if ($reportId !== ''): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($reportFileUrl); ?>" target="_blank" rel="noopener" title="View Report" data-tooltip="View">
                                  <i class="bi bi-eye"></i>
                                </a>
                              <?php else: ?>
                                <button class="btn btn-sm btn-outline-primary" type="button" disabled title="Report URL not available" data-tooltip="Unavailable">
                                  <i class="bi bi-eye"></i>
                                </button>
                              <?php endif; ?>
                              <a class="btn btn-sm btn-outline-success <?php echo $reportId === '' ? 'disabled' : ''; ?>" href="<?php echo $reportId !== '' ? htmlspecialchars($reportDownloadUrl) : '#'; ?>" download title="Download Report" data-tooltip="Download">
                                <i class="bi bi-download"></i>
                              </a>
                              <a class="btn btn-sm btn-outline-danger" href="../actions/delete_order_flow_report.php?id=<?php echo urlencode($report['id'] ?? ''); ?>" onclick="return confirm('Delete this report?');" title="Delete Report" data-tooltip="Delete">
                                <i class="bi bi-trash"></i>
                              </a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <div class="pagination-wrap">
                <span class="pagination-note">Pagination is applied after every 10 reports.</span>

                <nav aria-label="Reports pagination">
                  <ul class="pagination mb-0">
                    <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                      <?php if ($currentPage <= 1): ?>
                        <span class="page-link">Previous</span>
                      <?php else: ?>
                        <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>">Previous</a>
                      <?php endif; ?>
                    </li>
                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                      <li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
                        <?php if ($page === $currentPage): ?>
                          <span class="page-link"><?php echo $page; ?></span>
                        <?php else: ?>
                          <a class="page-link" href="?page=<?php echo $page; ?>"><?php echo $page; ?></a>
                        <?php endif; ?>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                      <?php if ($currentPage >= $totalPages): ?>
                        <span class="page-link">Next</span>
                      <?php else: ?>
                        <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>">Next</a>
                      <?php endif; ?>
                    </li>
                  </ul>
                </nav>
              </div>
            </div>

            <!-- Report View Modal -->
            <div class="modal fade" id="reportViewModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body" id="reportModalBody">
                    <div class="text-center">
                      <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      (() => {
        document.querySelectorAll('[data-tooltip]').forEach((element) => {
          const tooltipText = element.getAttribute('data-tooltip');
          if (!tooltipText) {
            return;
          }

          if (!element.getAttribute('title')) {
            element.setAttribute('title', tooltipText);
          }
          element.setAttribute('data-bs-toggle', 'tooltip');
          element.setAttribute('data-bs-container', 'body');
          element.setAttribute('data-bs-trigger', 'hover focus');
        });

        if (window.bootstrap) {
          document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            bootstrap.Tooltip.getOrCreateInstance(element);
          });
        }

        const form = document.getElementById('orderFlowForm');
        const statusPanel = document.getElementById('orderFlowStatus');
        const runButton = document.getElementById('runOrderFlowButton');
        const stopButton = document.getElementById('stopOrderFlowButton');
        let activeController = null;
        let activeClientRunId = '';

        const setStatus = (type, message, details = '') => {
          statusPanel.className = `alert alert-${type} status-panel`;
          statusPanel.style.display = 'block';
          statusPanel.innerHTML = details
            ? `${message}<pre>${details}</pre>`
            : message;
        };

        const parseOrderFlowResponse = async (response) => {
          const rawText = await response.text();
          const contentType = response.headers.get('content-type') || '';

          if (contentType.includes('application/json')) {
            try {
              return JSON.parse(rawText);
            } catch (error) {
              throw new Error(rawText || 'Invalid JSON response from order flow endpoint.');
            }
          }

          const normalizedText = rawText.trim();
          const htmlMessage = normalizedText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
          throw new Error(htmlMessage || 'Server returned a non-JSON response.');
        };

        form.addEventListener('submit', async (event) => {
          event.preventDefault();

          const formData = new FormData(form);
          if (!formData.get('order_flow_url') || !formData.get('order_flow_csv')) {
            setStatus('danger', 'URL and CSV file are required.');
            return;
          }

          activeClientRunId = `ofr_${Date.now()}_${Math.random().toString(16).slice(2)}`;
          formData.set('client_run_id', activeClientRunId);
          const headlessCheckbox = document.getElementById('order_flow_headless');
          if (headlessCheckbox) {
            formData.set('headless', headlessCheckbox.checked ? '1' : '0');
          }
          activeController = new AbortController();
          runButton.disabled = true;
          stopButton.disabled = false;
          runButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Running';
          setStatus('info', 'Order flow is running. The consolidated report will be generated after all rows are processed.');

          try {
            const response = await fetch('../actions/run_order_flow.php', {
              method: 'POST',
              headers: {
                'Accept': 'application/json'
              },
              body: formData,
              signal: activeController.signal
            });
            const data = await parseOrderFlowResponse(response);

            if (data.stopped) {
              setStatus('warning', 'Order flow stopped.', 'The stop signal was received and the active browser session is being closed.');
              return;
            }

            if (!response.ok || !data.success) {
              throw new Error(data.details || data.error || 'Order flow failed.');
            }

            const report = data.report || {};
            setStatus(
              'success',
              `Run completed. ${report.placed_orders || 0} order(s) placed, ${report.failed_orders || 0} failed.`,
              `Report ID: ${report.id || '-'}\nView: ${report.view_url || '-'}`
            );

            window.setTimeout(() => window.location.reload(), 1200);
          } catch (error) {
            if (error.name === 'AbortError') {
              setStatus('warning', 'Order flow request stopped from this screen.', 'If the server runner had already started, it may finish in the background.');
            } else {
              setStatus('danger', 'Order flow execution failed.', error.message);
            }
          } finally {
            runButton.disabled = false;
            stopButton.disabled = true;
            activeController = null;
            activeClientRunId = '';
            runButton.innerHTML = '<i class="bi bi-play-circle me-1"></i>Run Test';
          }
        });

        stopButton.addEventListener('click', async () => {
          if (!activeClientRunId) {
            return;
          }
          stopButton.disabled = true;
          setStatus('warning', 'Stopping order flow...', 'A stop signal is being sent to close the active browser session.');

          try {
            await fetch('../actions/stop_order_flow.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              body: JSON.stringify({ client_run_id: activeClientRunId })
            });
          } catch (error) {
            setStatus('danger', 'Unable to send stop signal.', error.message);
            stopButton.disabled = false;
          }
        });
      })();

      // Report View Modal Function
      function viewReportModal(viewUrl, reportId) {
        const modal = new bootstrap.Modal(document.getElementById('reportViewModal'));
        const modalBody = document.getElementById('reportModalBody');
        
        // Show loading state
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        if (!viewUrl) {
          modalBody.innerHTML = '<div class="alert alert-warning">Report URL not available.</div>';
          modal.show();
          return;
        }

        // Fetch and display report
        fetch(viewUrl)
          .then(response => {
            if (!response.ok) throw new Error('Failed to load report');
            return response.text();
          })
          .then(html => {
            // Extract body content or display as-is
            const bodyMatch = html.match(/<body[^>]*>([\s\S]*)<\/body>/i);
            const content = bodyMatch ? bodyMatch[1] : html;
            modalBody.innerHTML = content;
            modal.show();
          })
          .catch(error => {
            modalBody.innerHTML = `<div class="alert alert-danger">Error loading report: ${error.message}</div>`;
            modal.show();
          });
      }
    </script>
  </body>
</html>
