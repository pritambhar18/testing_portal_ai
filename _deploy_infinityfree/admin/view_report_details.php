<?php
/**
 * admin/view_report_details.php
 * Display detailed view of a specific test report
 */

session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];

// Get report ID from URL parameter
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
    header('Location: view_reports.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Fetch the report details
$report = null;
$error = '';

if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            test_link,
            execution_date,
            pdf_path
        FROM test_reports
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $report = $result->fetch_assoc();
        } else {
            $error = 'Report not found.';
        }

        $stmt->close();
    } else {
        $error = 'Database error: ' . $conn->error;
    }
} else {
    $error = 'Database connection failed.';
}

if (!$report) {
    $error = $error ?: 'Unable to load report.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Details - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .detail-card {
            padding: 2rem;
        }

        .detail-row {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            gap: 2rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
            flex-shrink: 0;
        }

        .detail-value {
            flex: 1;
            word-break: break-word;
        }

        .detail-value a {
            color: #0d6efd;
            text-decoration: none;
        }

        .detail-value a:hover {
            text-decoration: underline;
        }

        .back-button {
            margin-bottom: 1.5rem;
        }

        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .test-link-display {
            display: inline-block;
            max-width: 100%;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
        }

        @media (max-width: 768px) {
            .detail-card {
                padding: 1.5rem;
            }

            .detail-row {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons a {
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

    <!-- Main content -->
    <main class="content">
        <div class="container-fluid page-shell">
            <!-- Back Button -->
            <div class="back-button">
                <a href="view_reports.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Reports
                </a>
            </div>

            <?php if ($error): ?>
                <!-- Error Alert -->
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($report): ?>
                <!-- Report Details -->
                <div>
                    <section class="page-hero">
                        <div>
                            <h3><i class="bi bi-file-earmark-pdf me-2"></i>Test Report #<?php echo (int)$report['id']; ?></h3>
                            <p>Review the report metadata, tested link, execution timestamp, and available downloadable artifact.</p>
                        </div>
                        <div class="page-hero-meta">
                            <div class="hero-tile"><span>Report ID</span><strong><?php echo (int)$report['id']; ?></strong></div>
                            <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
                        </div>
                    </section>

                    <!-- Report Information Card -->
                    <div class="detail-card surface-card">
                        <h5 class="mb-3">
                            <i class="bi bi-info-circle me-2"></i>Report Details
                        </h5>

                        <div class="detail-row">
                            <div class="detail-label">Report ID:</div>
                            <div class="detail-value">
                                <strong><?php echo (int)$report['id']; ?></strong>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Test Link:</div>
                            <div class="detail-value">
                                <a href="<?php echo htmlspecialchars($report['test_link']); ?>" target="_blank" title="Open test link in new window">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>
                                    <?php echo htmlspecialchars($report['test_link']); ?>
                                </a>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Execution Date:</div>
                            <div class="detail-value">
                                <?php 
                                $executionDate = new DateTime($report['execution_date']);
                                echo htmlspecialchars($executionDate->format('F d, Y \a\t H:i:s'));
                                ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Report Path:</div>
                            <div class="detail-value">
                                <?php if ($report['pdf_path']): ?>
                                    <div class="test-link-display" title="<?php echo htmlspecialchars($report['pdf_path']); ?>">
                                        <?php echo htmlspecialchars($report['pdf_path']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-dash-circle me-1"></i>No report file available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Download Action -->
                    <div class="action-buttons">
                        <?php if ($report['pdf_path']): ?>
                            <a href="../actions/download_report.php?id=<?php echo (int)$report['id']; ?>" class="btn btn-success" download>
                                <i class="bi bi-download me-1"></i>Download Report
                            </a>
                        <?php endif; ?>
                        <a href="view_reports.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list me-1"></i>Back to All Reports
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
