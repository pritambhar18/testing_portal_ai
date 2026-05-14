<?php
/**
 * setup/insert_sample_reports.php
 * Insert sample test reports for quick View Reports module testing
 * 
 * Run this ONCE to populate sample data
 * Usage: Open in browser or run from command line (if session headers not required)
 */

session_start();

// Configuration
$numSamples = 5;
$sampleUrls = [
    'https://example.com',
    'https://shopify-demo.myshopify.com',
    'https://www.amazon.com',
    'https://demo.litmus.com/emails',
    'https://example.org/checkout'
];

$result_message = '';
$error_message = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';
    
    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            throw new Exception('Database connection failed');
        }

        // Check if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'test_reports'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            throw new Exception('Table test_reports does not exist. Please run create_test_reports_table.php first.');
        }

        $insertCount = 0;
        
        for ($i = 1; $i <= $numSamples; $i++) {
            $url = $sampleUrls[($i - 1) % count($sampleUrls)];
            $executionDate = date('Y-m-d H:i:s', time() - ($i * 3600)); // Each report is 1 hour apart
            $pdfPath = 'reports/generated/test_report_sample_' . $i . '.html';
            
            $stmt = $conn->prepare("
                INSERT INTO test_reports (test_link, execution_date, pdf_path)
                VALUES (?, ?, ?)
            ");
            
            if ($stmt) {
                $stmt->bind_param('sss', $url, $executionDate, $pdfPath);
                
                if ($stmt->execute()) {
                    $insertCount++;
                    $stmt->close();
                } else {
                    throw new Exception('Failed to insert record: ' . $conn->error);
                }
            } else {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
        }

        $result_message = "✅ Successfully inserted $insertCount sample reports!";

        $conn->close();

    } catch (Exception $e) {
        $error_message = "❌ Error: " . htmlspecialchars($e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Sample Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        .card-body {
            padding: 2rem;
        }
        .sample-table {
            font-size: 0.9rem;
        }
        .sample-table thead {
            background: #f8f9fa;
        }
        .alert {
            border-radius: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="card" style="max-width: 600px; width: 100%;">
        <div class="card-header">
            <h4 class="mb-0">
                <i class="bi bi-plus-circle me-2"></i>Insert Sample Test Reports
            </h4>
        </div>
        <div class="card-body">
            <?php if ($result_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $result_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Next step:</strong> 
                    <a href="../admin/view_reports.php">Go to View Reports</a> to see the sample data.
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!$result_message): ?>
                <div class="info-box">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>What does this do?</strong>
                    <p class="mb-0">This script will insert <?php echo $numSamples; ?> sample test reports into the database with different URLs and timestamps. Use this for quick testing of the View Reports module.</p>
                </div>

                <h5 class="mb-3">Sample Data to be Inserted:</h5>
                <div class="table-responsive">
                    <table class="table table-sm sample-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Test Link</th>
                                <th>Execution Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $timeOffset = time();
                            for ($i = 1; $i <= $numSamples; $i++): 
                                $url = $sampleUrls[($i - 1) % count($sampleUrls)];
                                $date = date('Y-m-d H:i:s', $timeOffset - ($i * 3600));
                            ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><small><?php echo htmlspecialchars(strlen($url) > 40 ? substr($url, 0, 37) . '...' : $url); ?></small></td>
                                    <td><small><?php echo $date; ?></small></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-2"></i>Insert Sample Reports
                    </button>
                </form>

                <hr class="my-3">

                <h5 class="mb-3">Already have sample data?</h5>
                <p class="mb-2">If you already inserted sample data before, you can manually delete them:</p>
                <code style="background: #f8f9fa; padding: 0.75rem; border-radius: 0.375rem; display: block;">
                    DELETE FROM test_reports WHERE pdf_path LIKE '%sample%';
                </code>
                <p class="small text-muted mt-2">Then run this script again to insert fresh sample data.</p>
            <?php endif; ?>

            <hr class="my-3">

            <p class="mb-0 text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                <a href="test_view_reports_module.php">Run diagnostics</a> | 
                <a href="../admin/view_reports.php">View Reports page</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
