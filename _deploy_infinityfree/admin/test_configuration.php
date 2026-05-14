<?php
// admin/test_configuration.php
session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];

// Flash messages for saved values (no DB functionality yet)
$resultMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['run_test_index']) || isset($_POST['save_index'])) {
        $_SESSION['index_url'] = trim($_POST['index_url'] ?? '');
        $resultMessage = 'Index Page URL saved (temporarily in session).';
    } elseif (isset($_POST['run_test_presell']) || isset($_POST['save_presell'])) {
        $_SESSION['presell_url'] = trim($_POST['presell_url'] ?? '');
        $resultMessage = 'Presell Page URL saved (temporarily in session).';
    } elseif (isset($_POST['run_test_checkout']) || isset($_POST['save_checkout'])) {
        $_SESSION['checkout_url'] = trim($_POST['checkout_url'] ?? '');
        $resultMessage = 'Checkout Page URL saved (temporarily in session).';
    } elseif (isset($_POST['run_test_thankyou']) || isset($_POST['save_thankyou'])) {
        $_SESSION['thankyou_url'] = trim($_POST['thankyou_url'] ?? '');
        $resultMessage = 'Thank You Page URL saved (temporarily in session).';
    }
}

function getValue($key) {
    return isset($_SESSION[$key]) ? htmlspecialchars($_SESSION[$key]) : '';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quick Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
      .test-config-section {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
      }
      .form-group-horizontal {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
      }
      .form-group-horizontal .form-input {
        flex: 1;
        min-width: 250px;
      }
      .form-group-horizontal .test-btn {
        flex-shrink: 0;
      }
      @media (max-width: 768px) {
        .form-group-horizontal {
          flex-direction: column;
          align-items: stretch;
        }
        .form-group-horizontal .form-input {
          min-width: 100%;
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
        <section class="page-hero">
          <div>
            <h3><i class="bi bi-gear me-2"></i>Quick Test</h3>
            <p>Configure key testing URLs and manage upsell paths from a cleaner workspace built for repeat execution.</p>
          </div>
          <div class="page-hero-meta">
            <div class="hero-tile"><span>Section</span><strong>Quick Test</strong></div>
            <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
          </div>
        </section>

        <?php if ($resultMessage): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($resultMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="test-config-section">
          <div class="card config-card surface-card">
            <div class="card-body">
              <h5 class="card-title">
                <i class="bi bi-link-45deg me-2"></i>Website Testing URLs
              </h5>
              <p class="text-muted">Enter the URLs for each website page you want to test.</p>

              <form id="siteConfigForm" class="site-config-form" novalidate>
                <!-- Index Page -->
                <div class="mb-4">
                  <label for="index_url" class="form-label">Index Page URL</label>
                  <div class="form-group-horizontal">
                    <div class="form-input">
                      <input type="url" class="form-control" id="index_url" name="index_url" placeholder="https://example.com" value="<?php echo getValue('index_url'); ?>" required>
                      <div class="invalid-feedback" id="index_url_error"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary test-btn" data-target="index_url">
                      <i class="bi bi-play-circle me-1"></i>Test
                    </button>
                  </div>
                </div>

                <!-- Presell Page -->
                <div class="mb-4">
                  <label for="presell_url" class="form-label">Presell Page URL</label>
                  <div class="form-group-horizontal">
                    <div class="form-input">
                      <input type="url" class="form-control" id="presell_url" name="presell_url" placeholder="https://example.com/presell" value="<?php echo getValue('presell_url'); ?>" required>
                      <div class="invalid-feedback" id="presell_url_error"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary test-btn" data-target="presell_url">
                      <i class="bi bi-play-circle me-1"></i>Test
                    </button>
                  </div>
                </div>

                <!-- Checkout Page -->
                <div class="mb-4">
                  <label for="checkout_url" class="form-label">Checkout Page URL</label>
                  <div class="form-group-horizontal">
                    <div class="form-input">
                      <input type="url" class="form-control" id="checkout_url" name="checkout_url" placeholder="https://example.com/checkout" value="<?php echo getValue('checkout_url'); ?>" required>
                      <div class="invalid-feedback" id="checkout_url_error"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary test-btn" data-target="checkout_url">
                      <i class="bi bi-play-circle me-1"></i>Test
                    </button>
                  </div>
                </div>

                <!-- Thank You Page -->
                <div class="mb-4">
                  <label for="thankyou_url" class="form-label">Thank You Page URL</label>
                  <div class="form-group-horizontal">
                    <div class="form-input">
                      <input type="url" class="form-control" id="thankyou_url" name="thankyou_url" placeholder="https://example.com/thank-you" value="<?php echo getValue('thankyou_url'); ?>" required>
                      <div class="invalid-feedback" id="thankyou_url_error"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary test-btn" data-target="thankyou_url">
                      <i class="bi bi-play-circle me-1"></i>Test
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <div class="card config-card surface-card">
            <div class="card-body">
              <h5 class="card-title">
                <i class="bi bi-star me-2"></i>Upsell Pages
              </h5>
              <p class="text-muted">Add additional upsell URLs. Click + to add a row, - to remove.</p>
              <div id="upsellContainer" class="upsell-list"></div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/upsell.js"></script>
    <script src="../assets/js/test_report.js"></script>
  </body>
</html>
