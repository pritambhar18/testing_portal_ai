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

      .flow-shell {
        display: grid;
        gap: 1.6rem;
      }

      .qa-page-head {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(260px, 0.95fr);
        gap: 1.25rem;
        margin-bottom: 1.4rem;
        padding: 1.6rem 1.7rem;
        border-radius: 1.5rem;
        background:
          linear-gradient(135deg, rgba(19, 42, 69, 0.98) 0%, rgba(17, 82, 104, 0.94) 55%, rgba(15, 118, 110, 0.88) 100%);
        box-shadow: 0 24px 60px rgba(19, 42, 69, 0.18);
        color: #fff;
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
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.03em;
      }

      .qa-page-copy {
        margin-top: 0.7rem;
        max-width: 720px;
        color: rgba(255, 255, 255, 0.76);
        font-size: 0.98rem;
        line-height: 1.65;
      }

      .qa-head-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
        align-self: stretch;
      }

      .qa-head-tile {
        position: relative;
        z-index: 1;
        padding: 1rem 1.05rem;
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
      }

      .qa-head-tile span {
        display: block;
        font-size: 0.74rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.68);
        font-weight: 700;
      }

      .qa-head-tile strong {
        display: block;
        margin-top: 0.5rem;
        font-size: 1rem;
        line-height: 1.45;
      }

      .flow-toolbar,
      .report-panel {
        border: 1px solid var(--qa-line);
        border-radius: 1.25rem;
        background: var(--qa-panel);
        box-shadow: var(--qa-shadow);
        backdrop-filter: blur(10px);
      }

      .flow-toolbar .card-body,
      .report-panel .card-body {
        padding: 1.5rem;
      }

      .flow-toolbar {
        overflow: hidden;
        background:
          linear-gradient(135deg, rgba(19, 42, 69, 0.98) 0%, rgba(15, 118, 110, 0.88) 100%),
          #132a45;
        color: #fff;
      }

      .report-panel {
        overflow: hidden;
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
        margin-bottom: 1rem;
      }

      .flow-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.4rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 700;
        border: 1px solid rgba(255, 255, 255, 0.18);
      }

      .flow-form {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.2fr) minmax(210px, 1.05fr) minmax(170px, 0.8fr) auto;
        gap: 0.9rem;
        align-items: end;
      }

      .field-shell {
        padding: 0.85rem 0.95rem;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 0.95rem;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(8px);
      }

      .field-shell label {
        display: block;
        margin-bottom: 0.55rem;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.83rem;
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

      .field-shell .form-control::placeholder {
        color: rgba(255, 255, 255, 0.5);
      }

      .field-shell .form-control option {
        color: #1a2435;
      }

      .run-button {
        height: 54px;
        min-width: 150px;
        border-radius: 0.95rem;
        font-weight: 700;
        border: 0;
        background: linear-gradient(135deg, #f4c95d 0%, #f59e0b 100%);
        color: #192432;
        box-shadow: 0 14px 28px rgba(245, 158, 11, 0.28);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
      }

      .run-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(245, 158, 11, 0.34);
      }

      .helper-copy {
        margin-top: 0.75rem;
        font-size: 0.88rem;
      }

      .status-panel {
        display: none;
        margin-top: 1rem;
        border-radius: 0.95rem;
      }

      .status-panel pre {
        margin: 0.65rem 0 0;
        white-space: pre-wrap;
      }

      .report-meta {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #ebf0f6;
      }

      .report-count {
        color: #546276;
        font-size: 0.92rem;
      }

      .report-count strong {
        display: block;
        color: #132a45;
        font-size: 1.7rem;
        line-height: 1.1;
      }

      .report-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.9rem;
        margin-bottom: 1rem;
      }

      .summary-tile {
        padding: 1rem 1.05rem;
        border: 1px solid #e2eaf3;
        border-radius: 1rem;
        background: linear-gradient(180deg, #fbfdff 0%, #f3f7fb 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.78);
      }

      .summary-tile span {
        display: block;
        color: #6f7f93;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .summary-tile strong {
        display: block;
        margin-top: 0.45rem;
        color: #132a45;
        font-size: 1.45rem;
      }

      .table-wrap {
        border: 1px solid #e6edf5;
        border-radius: 1rem;
        overflow: hidden;
        background: #fff;
      }

      .report-table {
        table-layout: fixed;
        width: 100%;
      }

      .table thead th {
        background: #f4f8fb;
        color: #3c4b5f;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom-width: 1px;
        white-space: normal;
        padding-top: 0.95rem;
        padding-bottom: 0.95rem;
      }

      .table td {
        vertical-align: middle;
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
        font-size: 0.9rem;
        color: var(--qa-ink);
      }

      .table tbody tr:nth-child(even) {
        background: #fbfdff;
      }

      .table tbody tr:hover {
        background: #f5fbfa;
      }

      .sl-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.2rem;
        height: 2.2rem;
        border-radius: 999px;
        background: #e8f2ff;
        color: #0b5ed7;
        font-weight: 700;
      }

      .browser-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.55rem;
        padding: 0.28rem 0.6rem;
        border-radius: 999px;
        background: #eef4fb;
        color: #33506f;
        font-size: 0.78rem;
        font-weight: 600;
      }

      .report-id-text {
        display: inline-block;
        max-width: 100%;
        word-break: break-word;
        line-height: 1.35;
        font-size: 0.84rem;
      }

      .time-stamp {
        font-weight: 700;
        color: #132a45;
        font-size: 0.86rem;
        line-height: 1.35;
      }

      .time-meta {
        margin-top: 0.4rem;
        color: #627286;
        font-size: 0.82rem;
        line-height: 1.45;
      }

      .report-url {
        display: block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .action-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }

      .action-group .btn {
        border-radius: 999px;
        padding: 0.4rem 0.65rem;
        font-size: 0.78rem;
        font-weight: 600;
      }

      .empty-state {
        padding: 2rem 1rem;
        text-align: center;
        color: #6f7f93;
      }

      .pagination-wrap {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-top: 0.85rem;
      }

      .pagination-note {
        color: #6e7d91;
        font-size: 0.88rem;
      }

      @media (max-width: 991px) {
        .qa-page-head {
          grid-template-columns: 1fr;
        }

        .qa-head-meta {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .flow-form {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .run-button {
          width: 100%;
          grid-column: 1 / -1;
        }

        .report-summary {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 768px) {
        .qa-page-head {
          padding: 1.2rem;
        }

        .qa-head-meta {
          grid-template-columns: 1fr;
        }

        .flow-toolbar-header,
        .report-meta,
        .pagination-wrap {
          flex-direction: column;
          align-items: stretch;
        }

        .flow-form {
          grid-template-columns: 1fr;
        }

        .report-url {
          max-width: 220px;
        }

        .report-summary {
          grid-template-columns: 1fr;
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

        <section class="qa-page-head">
          <div>
            <h3><i class="bi bi-shuffle me-2"></i>88Startech QA Center</h3>
            <p class="qa-page-copy mb-0">Dedicated quality assurance workspace for the 88Startech project. Use this screen to run execution checks now and expand the same area with console, functional, and security validations as the project grows.</p>
          </div>
          <div class="qa-head-meta">
            <div class="qa-head-tile">
              <span>Project</span>
              <strong>88Startech</strong>
            </div>
            <div class="qa-head-tile">
              <span>Environment</span>
              <strong>Testing Portal</strong>
            </div>
            <div class="qa-head-tile">
              <span>Reports</span>
              <strong><?php echo number_format($totalReports); ?> Archived</strong>
            </div>
            <div class="qa-head-tile">
              <span>Signed In</span>
              <strong><?php echo htmlspecialchars($admin_email); ?></strong>
            </div>
          </div>
        </section>

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

                <div class="field-shell">
                  <label for="order_flow_browser">Browser</label>
                  <select id="order_flow_browser" name="browser" class="form-control">
                    <option value="chrome">Chrome</option>
                    <option value="msedge" selected>Microsoft Edge</option>
                    <option value="chromium">Chromium</option>
                    <option value="firefox">Firefox</option>
                    <option value="webkit">WebKit</option>
                  </select>
                </div>

                <button type="submit" class="btn btn-primary run-button" id="runOrderFlowButton">
                  <i class="bi bi-play-circle me-1"></i>Run
                </button>
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
                    <col style="width: 6%;">
                    <col style="width: 16%;">
                    <col style="width: 18%;">
                    <col style="width: 22%;">
                    <col style="width: 16%;">
                    <col style="width: 22%;">
                  </colgroup>
                  <thead>
                    <tr>
                      <th>Sl No</th>
                      <th>Report ID</th>
                      <th>Offer Name</th>
                      <th>URL</th>
                      <th>Execution System Time</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($reports)): ?>
                      <tr>
                        <td colspan="6">
                          <div class="empty-state">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No 88Startech QA reports available.
                          </div>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($reports as $index => $report): ?>
                        <tr>
                          <td>
                            <span class="sl-badge"><?php echo (int)(($currentPage - 1) * $reportsPerPage + $index + 1); ?></span>
                          </td>
                          <td><strong class="report-id-text"><?php echo htmlspecialchars($report['id'] ?? ''); ?></strong></td>
                          <td>
                            <strong class="report-id-text"><?php echo htmlspecialchars($report['offer_name'] ?? '-'); ?></strong>
                          </td>
                          <td>
                            <span class="report-url" title="<?php echo htmlspecialchars($report['url'] ?? ''); ?>">
                              <?php echo htmlspecialchars($report['url'] ?? ''); ?>
                            </span>
                            <div class="browser-pill">
                              <i class="bi bi-browser-edge"></i>
                              <?php echo htmlspecialchars($report['browser'] ?? 'chromium'); ?>
                            </div>
                          </td>
                          <td>
                            <?php
                              $displayDate = '-';
                              if (!empty($report['created_at'])) {
                                  $parsed = strtotime((string)$report['created_at']);
                                  $displayDate = $parsed !== false
                                      ? date('d M Y h:i:s A', $parsed)
                                      : (string)$report['created_at'];
                              }
                            ?>
                            <div class="time-stamp"><?php echo htmlspecialchars($displayDate); ?></div>
                            <div class="time-meta">
                              Rows: <?php echo (int)($report['total_rows'] ?? 0); ?> |
                              Placed: <?php echo (int)($report['placed_orders'] ?? 0); ?> |
                              Failed: <?php echo (int)($report['failed_orders'] ?? 0); ?>
                            </div>
                          </td>
                          <td>
                            <div class="action-group">
                              <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($report['view_url'] ?? '#'); ?>" target="_blank" rel="noopener">
                                <i class="bi bi-eye me-1"></i>View
                              </a>
                              <a class="btn btn-sm btn-outline-success" href="<?php echo htmlspecialchars($report['download_url'] ?? '#'); ?>" download>
                                <i class="bi bi-download me-1"></i>Download
                              </a>
                              <a class="btn btn-sm btn-outline-danger" href="../actions/delete_order_flow_report.php?id=<?php echo urlencode($report['id'] ?? ''); ?>" onclick="return confirm('Delete this order flow report?');">
                                <i class="bi bi-trash me-1"></i>Delete
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
          </section>
        </div>
      </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      (() => {
        const form = document.getElementById('orderFlowForm');
        const statusPanel = document.getElementById('orderFlowStatus');
        const runButton = document.getElementById('runOrderFlowButton');

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

          runButton.disabled = true;
          runButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Running';
          setStatus('info', 'Order flow is running. The consolidated report will be generated after all rows are processed.');

          try {
            const response = await fetch('../actions/run_order_flow.php', {
              method: 'POST',
              headers: {
                'Accept': 'application/json'
              },
              body: formData
            });
            const data = await parseOrderFlowResponse(response);

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
            setStatus('danger', 'Order flow execution failed.', error.message);
          } finally {
            runButton.disabled = false;
            runButton.innerHTML = '<i class="bi bi-play-circle me-1"></i>Run';
          }
        });
      })();
    </script>
  </body>
</html>
