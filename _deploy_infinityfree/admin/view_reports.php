<?php
/**
 * admin/view_reports.php
 * Clean View Reports page displaying executed tests and their reports
 * Session-protected admin page
 */

session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .reports-container {
            overflow: hidden;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .view-icon {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.08);
        }

        .view-icon:hover {
            background-color: rgba(13, 110, 253, 0.16);
            transform: scale(1.1);
        }

        .download-icon {
            color: #198754;
            background-color: rgba(25, 135, 84, 0.08);
        }

        .download-icon:hover {
            background-color: rgba(25, 135, 84, 0.16);
            transform: scale(1.1);
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 3rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
            display: none;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .error-alert {
            display: none;
            margin-bottom: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .test-link-cell {
            display: inline-block;
            max-width: 100%;
            word-break: break-word;
            color: #0d6efd;
            text-decoration: none;
        }

        .test-link-cell:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .action-icon {
                width: 1.75rem;
                height: 1.75rem;
                font-size: 0.95rem;
            }

            .action-buttons {
                gap: 0.5rem;
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

    <!-- Main content -->
    <main class="content">
        <div class="container-fluid page-shell">
            <section class="page-hero">
                <div>
                    <h3><i class="bi bi-file-earmark-pdf me-2"></i>View Reports</h3>
                    <p>Review generated reports, open the detailed view, and download completed outputs from a consistent reports workspace.</p>
                </div>
                <div class="page-hero-meta">
                    <div class="hero-tile"><span>Module</span><strong>Reports Library</strong></div>
                    <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
                </div>
            </section>

            <!-- Error Alert -->
            <div id="errorAlert" class="alert alert-danger alert-dismissible fade show error-alert" role="alert">
                <strong><i class="bi bi-exclamation-circle me-2"></i>Error!</strong>
                <span id="errorMessage"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <!-- Loading Spinner -->
            <div class="loading-spinner" id="loadingSpinner">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Loading reports...</p>
            </div>

            <!-- Reports Table Container -->
            <div class="reports-container surface-card" id="reportsContainer" style="display: none;">
                <div class="card-body">
                <div class="table-responsive data-card">
                    <table class="table data-table mb-0 reports-table">
                        <thead>
                            <tr>
                                <th style="width: 8%;">ID</th>
                                <th style="width: 50%;">Test Link</th>
                                <th style="width: 25%;">Execution Date</th>
                                <th style="width: 17%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reportsTableBody">
                            <!-- Reports loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="emptyState">
                <div class="empty-state-icon">
                    <i class="bi bi-inbox"></i>
                </div>
                <h5>No Reports Available</h5>
                <p class="mb-3">Run a test from the Quick Test page to generate your first report.</p>
                <a href="test_configuration.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-gear me-1"></i>Go to Quick Test
                </a>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Reports Loading Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadReports();
        });

        /**
         * Load reports from API and populate table
         */
        function loadReports() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            const reportsContainer = document.getElementById('reportsContainer');
            const reportsTableBody = document.getElementById('reportsTableBody');
            const emptyState = document.getElementById('emptyState');
            const errorAlert = document.getElementById('errorAlert');

            // Show loading state
            loadingSpinner.style.display = 'block';
            reportsContainer.style.display = 'none';
            emptyState.style.display = 'none';
            errorAlert.style.display = 'none';

            // Fetch reports from API
            fetch('../api/get_reports.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: Failed to fetch reports`);
                    }
                    return response.json();
                })
                .then(data => {
                    loadingSpinner.style.display = 'none';

                    if (!data.success) {
                        throw new Error(data.error || 'Unknown error occurred');
                    }

                    // Check if reports exist
                    if (data.reports && data.reports.length > 0) {
                        // Populate table with reports
                        reportsTableBody.innerHTML = '';
                        data.reports.forEach(report => {
                            const row = createReportRow(report);
                            reportsTableBody.appendChild(row);
                        });
                        reportsContainer.style.display = 'block';
                    } else {
                        // Show empty state
                        emptyState.style.display = 'block';
                    }
                })
                .catch(error => {
                    loadingSpinner.style.display = 'none';
                    console.error('Error loading reports:', error);

                    // Show error message but still display empty state UI
                    const errorMessage = document.getElementById('errorMessage');
                    errorMessage.textContent = 'Failed to load reports: ' + error.message;
                    errorAlert.style.display = 'block';

                    // Show empty state as fallback
                    emptyState.style.display = 'block';
                });
        }

        /**
         * Create a table row for a single report
         * @param {Object} report - Report data object
         * @returns {HTMLElement} - Table row element
         */
        function createReportRow(report) {
            const row = document.createElement('tr');

            // Format execution date
            const executionDate = report.execution_date
                ? new Date(report.execution_date).toLocaleString()
                : 'N/A';

            // Truncate long URLs for display
            const displayLink = report.test_link.length > 60
                ? report.test_link.substring(0, 60) + '...'
                : report.test_link;

            row.innerHTML = `
                <td><strong>${escapeHtml(report.id)}</strong></td>
                <td>
                    <a href="${escapeHtml(report.test_link)}" target="_blank" class="test-link-cell" title="${escapeHtml(report.test_link)}">
                        ${escapeHtml(displayLink)}
                    </a>
                </td>
                <td>${escapeHtml(executionDate)}</td>
                <td>
                    <div class="action-buttons">
                        <a href="view_report_details.php?id=${report.id}" class="action-icon view-icon" title="View Report" data-bs-toggle="tooltip">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="../actions/download_report.php?id=${report.id}" class="action-icon download-icon" title="Download Report" data-bs-toggle="tooltip">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                </td>
            `;

            return row;
        }

        /**
         * Escape HTML special characters to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string} - Escaped text
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
