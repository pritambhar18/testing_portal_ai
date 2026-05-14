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
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Override body to prevent overall scrolling */
        body {
            overflow: hidden;
            height: 100vh;
        }

        main.content {
            height: calc(100vh - var(--header-height) - 2rem);
            overflow-y: auto;
            padding-bottom: 1rem;
        }

        .page-shell {
            display: grid;
            grid-template-rows: auto 1fr;
            gap: 1rem;
            height: 100%;
        }

        .page-hero {
            flex-shrink: 0;
        }

        .reports-container {
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .reports-container .card-body {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 0;
            flex: 1;
        }

        .reports-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f4f8;
        }

        .reports-toolbar h5 {
            margin: 0;
            font-weight: 850;
            font-size: 1.1rem;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reports-count {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #e7f8f4 0%, #d4f5f0 100%);
            color: #0f766e;
            font-size: 0.85rem;
            font-weight: 800;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(15, 118, 110, 0.1);
        }

        /* Table Container - Optimized for single screen */
        .data-card {
            display: flex;
            flex-direction: column;
            min-height: 0;
            border: 1px solid #e5eef5;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .table-responsive {
            overflow: auto;
            flex: 1;
            min-height: 0;
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 0.9rem;
        }

        .reports-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: linear-gradient(135deg, #f6f9fc 0%, #eff4f8 100%);
            border-bottom: 2px solid #d9e4f0;
        }

        .reports-table th {
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 800;
            color: #2d3748;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: inherit;
            white-space: nowrap;
        }

        .reports-table td {
            padding: 0.9rem 0.75rem;
            border-bottom: 1px solid #e5eef5;
            color: #374151;
            vertical-align: middle;
        }

        .reports-table tbody tr {
            transition: background-color 0.15s ease, box-shadow 0.15s ease;
        }

        .reports-table tbody tr:nth-child(even) {
            background-color: #f9fbfd;
        }

        .reports-table tbody tr:hover {
            background-color: #f0f7ff;
            box-shadow: inset 0 0 10px rgba(37, 99, 235, 0.05);
        }

        .reports-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Column sizing - optimized for fitting content */
        .reports-table th:nth-child(1),
        .reports-table td:nth-child(1) {
            width: 8%;
            min-width: 70px;
            text-align: center;
        }

        .reports-table th:nth-child(2),
        .reports-table td:nth-child(2) {
            width: 12%;
            min-width: 85px;
        }

        .reports-table th:nth-child(3),
        .reports-table td:nth-child(3) {
            width: 45%;
            min-width: 250px;
        }

        .reports-table th:nth-child(4),
        .reports-table td:nth-child(4) {
            width: 25%;
            min-width: 180px;
        }

        .reports-table th:nth-child(5),
        .reports-table td:nth-child(5) {
            width: 10%;
            min-width: 100px;
            text-align: center;
        }

        /* Badges - Modern Style */
        .report-id-badge,
        .serial-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.2rem;
            height: 2rem;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.78rem;
            letter-spacing: 0.3px;
        }

        .serial-badge {
            background: linear-gradient(135deg, #d4f5f0 0%, #c0ebe5 100%);
            color: #0a5d52;
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.15);
        }

        .report-id-badge {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15);
        }

        /* Links and Text Cells */
        .test-link-cell {
            display: block;
            color: #2563eb;
            text-decoration: none;
            word-break: break-word;
            line-height: 1.4;
            font-weight: 500;
        }

        .test-link-cell:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .date-cell {
            color: #4b5563;
            font-weight: 600;
            font-size: 0.88rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: inline-flex;
            gap: 0.4rem;
            align-items: center;
            justify-content: center;
        }

        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 7px;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid transparent;
        }

        .view-icon {
            color: #2563eb;
            background-color: rgba(37, 99, 235, 0.1);
            border-color: rgba(37, 99, 235, 0.2);
        }

        .view-icon:hover {
            background-color: rgba(37, 99, 235, 0.18);
            border-color: rgba(37, 99, 235, 0.3);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .download-icon {
            color: #16a34a;
            background-color: rgba(22, 163, 74, 0.1);
            border-color: rgba(22, 163, 74, 0.2);
        }

        .download-icon:hover {
            background-color: rgba(22, 163, 74, 0.18);
            border-color: rgba(22, 163, 74, 0.3);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
        }

        /* Loading and Empty States */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 3rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
            display: none;
            border: 2px dashed #d9e4f0;
            border-radius: 10px;
            background: linear-gradient(135deg, #f9fbfd 0%, #f0f7ff 100%);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .empty-state-icon {
            font-size: 3.5rem;
            color: #c4cfe1;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .error-alert {
            display: none;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background-color: #fef2f2;
            color: #991b1b;
            padding: 1rem;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            padding: 1rem;
            background: #f9fbfd;
            border-top: 1px solid #e5eef5;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #f3f4f6;
            border-color: #9ca3af;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .delete-icon {
            color: #dc2626;
            background-color: rgba(220, 38, 38, 0.1);
            border-color: rgba(220, 38, 38, 0.2);
        }

        .delete-icon:hover {
            background-color: rgba(220, 38, 38, 0.18);
            border-color: rgba(220, 38, 38, 0.3);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .reports-table th:nth-child(3),
            .reports-table td:nth-child(3) {
                width: 35%;
                min-width: 180px;
            }

            .reports-table th:nth-child(4),
            .reports-table td:nth-child(4) {
                width: 30%;
                min-width: 140px;
            }
        }

        @media (max-width: 768px) {
            body {
                overflow: auto;
                height: auto;
            }

            main.content {
                height: auto;
                overflow-y: auto;
            }

            .reports-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .reports-toolbar h5 {
                width: 100%;
            }

            .reports-count {
                width: 100%;
                justify-content: center;
            }

            .reports-table th,
            .reports-table td {
                padding: 0.7rem 0.5rem;
                font-size: 0.85rem;
            }

            .reports-table th:nth-child(1),
            .reports-table td:nth-child(1) {
                width: 10%;
                min-width: 50px;
            }

            .reports-table th:nth-child(2),
            .reports-table td:nth-child(2) {
                width: 15%;
                min-width: 60px;
            }

            .reports-table th:nth-child(3),
            .reports-table td:nth-child(3) {
                width: 40%;
                min-width: 120px;
            }

            .reports-table th:nth-child(4),
            .reports-table td:nth-child(4) {
                width: 25%;
                min-width: 100px;
            }

            .action-icon {
                width: 1.9rem;
                height: 1.9rem;
                font-size: 0.85rem;
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
                <div class="reports-toolbar">
                    <div>
                        <h5><i class="bi bi-folder2-open me-2"></i>Generated Reports</h5>
                    </div>
                    <span class="reports-count" id="reportsCount">
                        <i class="bi bi-database-check"></i>
                        0 Reports
                    </span>
                </div>
                <div class="table-responsive data-card">
                    <table class="table data-table mb-0 reports-table">
                        <thead>
                            <tr>
                                <th>SL No</th>
                                <th>Report ID</th>
                                <th>Test Link</th>
                                <th>Execution Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reportsTableBody">
                            <!-- Reports loaded by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Controls -->
                <div class="pagination-container" id="paginationContainer" style="display: none;">
                    <span class="pagination-info">
                        Showing <span id="pageStart">1</span> - <span id="pageEnd">10</span> of <span id="totalReports">0</span>
                    </span>
                    <button class="pagination-btn" id="prevBtn" onclick="goToPreviousPage()" title="Previous page">
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                    <span id="pageIndicator" class="pagination-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
                    <button class="pagination-btn" id="nextBtn" onclick="goToNextPage()" title="Next page">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
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
        const ITEMS_PER_PAGE = 10;
        let currentPage = 1;
        let totalReports = 0;
        let allReports = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadReports();
        });

        /**
         * Load reports from API and populate table with pagination
         */
        function loadReports() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            const reportsContainer = document.getElementById('reportsContainer');
            const emptyState = document.getElementById('emptyState');
            const errorAlert = document.getElementById('errorAlert');

            // Show loading state
            loadingSpinner.style.display = 'block';
            reportsContainer.style.display = 'none';
            emptyState.style.display = 'none';
            errorAlert.style.display = 'none';

            // Fetch all reports from API
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
                        allReports = data.reports;
                        totalReports = allReports.length;
                        currentPage = 1;
                        displayPageReports();
                        reportsContainer.style.display = 'block';
                    } else {
                        // Show empty state
                        emptyState.style.display = 'block';
                    }
                })
                .catch(error => {
                    loadingSpinner.style.display = 'none';
                    console.error('Error loading reports:', error);

                    const errorMessage = document.getElementById('errorMessage');
                    errorMessage.textContent = 'Failed to load reports: ' + error.message;
                    errorAlert.style.display = 'block';
                    emptyState.style.display = 'block';
                });
        }

        /**
         * Display reports for current page
         */
        function displayPageReports() {
            const reportsTableBody = document.getElementById('reportsTableBody');
            const paginationContainer = document.getElementById('paginationContainer');
            const reportsCount = document.getElementById('reportsCount');

            // Calculate pagination
            const totalPages = Math.ceil(totalReports / ITEMS_PER_PAGE);
            const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalReports);
            const pageReports = allReports.slice(startIndex, endIndex);

            // Populate table with page reports
            reportsTableBody.innerHTML = '';
            pageReports.forEach((report, pageIndex) => {
                const serialNumber = startIndex + pageIndex + 1;
                const row = createReportRow(report, serialNumber);
                reportsTableBody.appendChild(row);
            });

            // Update pagination info
            document.getElementById('pageStart').textContent = startIndex + 1;
            document.getElementById('pageEnd').textContent = endIndex;
            document.getElementById('totalReports').textContent = totalReports;
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;

            // Update button states
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage === totalPages;

            // Keep pagination visible for the Generated Reports list so users can always see record range and page state.
            paginationContainer.style.display = totalReports > 0 ? 'flex' : 'none';

            // Update reports count
            reportsCount.innerHTML = `<i class="bi bi-database-check"></i>${totalReports} ${totalReports === 1 ? 'Report' : 'Reports'}`;
        }

        /**
         * Go to next page
         */
        function goToNextPage() {
            const totalPages = Math.ceil(totalReports / ITEMS_PER_PAGE);
            if (currentPage < totalPages) {
                currentPage++;
                displayPageReports();
                scrollToTable();
            }
        }

        /**
         * Go to previous page
         */
        function goToPreviousPage() {
            if (currentPage > 1) {
                currentPage--;
                displayPageReports();
                scrollToTable();
            }
        }

        /**
         * Scroll to table for better UX
         */
        function scrollToTable() {
            document.getElementById('reportsContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /**
         * Create a table row for a single report
         * @param {Object} report - Report data object
         * @param {number} serialNumber - Serial number for display
         * @returns {HTMLElement} - Table row element
         */
        function createReportRow(report, serialNumber) {
            const row = document.createElement('tr');
            const rawLink = typeof report.test_link === 'string' && report.test_link.trim()
                ? report.test_link.trim()
                : '#';

            // Format execution date
            const parsedDate = report.execution_date ? new Date(report.execution_date) : null;
            const executionDate = parsedDate && !Number.isNaN(parsedDate.getTime())
                ? parsedDate.toLocaleString()
                : 'N/A';

            const safeId = Number.parseInt(report.id, 10) || 0;

            row.innerHTML = `
                <td><span class="serial-badge">${serialNumber}</span></td>
                <td><span class="report-id-badge">${escapeHtml(report.id)}</span></td>
                <td>
                    <a href="${escapeHtml(rawLink)}" target="_blank" rel="noopener" class="test-link-cell" title="${escapeHtml(rawLink)}">
                        ${escapeHtml(rawLink)}
                    </a>
                </td>
                <td><span class="date-cell">${escapeHtml(executionDate)}</span></td>
                <td>
                    <div class="action-buttons">
                        <a href="../actions/view_report.php?id=${safeId}" target="_blank" class="action-icon view-icon" title="View Report" aria-label="View report ${safeId}" data-bs-toggle="tooltip">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="../actions/download_report.php?id=${safeId}" class="action-icon download-icon" title="Download Report" aria-label="Download report ${safeId}" data-bs-toggle="tooltip">
                            <i class="bi bi-download"></i>
                        </a>
                        <button type="button" class="action-icon delete-icon" title="Delete Report" aria-label="Delete report ${safeId}" onclick="confirmDeleteReport(${safeId})" data-bs-toggle="tooltip">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;

            return row;
        }

        /**
         * Confirm and delete a report
         * @param {number} reportId - Report ID to delete
         */
        function confirmDeleteReport(reportId) {
            if (confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
                deleteReport(reportId);
            }
        }

        /**
         * Delete a report via API
         * @param {number} reportId - Report ID to delete
         */
        function deleteReport(reportId) {


                // Delete via AJAX and update UI
            const deleteBtn = document.querySelector(`button[aria-label="Delete report ${reportId}"]`);
            const originalHtml = deleteBtn ? deleteBtn.innerHTML : null;
            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            }

            fetch(`../actions/delete_report.php?id=${reportId}`, { method: 'GET', headers: { 'Accept': 'application/json' } })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: Failed to delete report`);
                    }
                    return response.text();
                })
                .then(() => {
                    // Reload reports after successful deletion
                    loadReports();
                    showSuccessMessage('Report deleted successfully!');
                })
                .catch(error => {
                    console.error('Error deleting report:', error);
                    if (deleteBtn) {
                        deleteBtn.innerHTML = originalHtml;
                        deleteBtn.disabled = false;
                    }
                    showErrorMessage('Failed to delete report: ' + error.message);
                });
        }

        /**
         * Show success message
         * @param {string} message - Success message
         */
        function showSuccessMessage(message) {
            // You can integrate with Bootstrap toast or alert
            console.log('Success:', message);
        }

        /**
         * Show error message
         * @param {string} message - Error message
         */
        function showErrorMessage(message) {
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = message;
            errorAlert.style.display = 'block';
            setTimeout(() => {
                errorAlert.style.display = 'none';
            }, 5000);
        }

        /**
         * Escape HTML special characters to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string} - Escaped text
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }
    </script>
</body>
</html>
