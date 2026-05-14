<?php
// admin/test_configuration.php
session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quick Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
      .test-config-section {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
      }

      .quick-test-rows {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
      }

      .quick-test-row {
        display: grid;
        grid-template-columns: minmax(150px, 0.45fr) minmax(220px, 1fr) auto auto auto;
        gap: 0.75rem;
        align-items: flex-start;
        min-width: 0;
        padding: 1rem;
        border: 1px solid #e3ebf4;
        border-radius: 8px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
      }

      .quick-test-row .quick-add,
      .quick-test-row .quick-remove {
        width: 44px;
        min-width: 44px;
        height: 44px;
        padding: 0;
        margin-top: 1.95rem;
      }

      .quick-test-row .quick-row-test {
        min-width: 92px;
        height: 44px;
        margin-top: 1.95rem;
        white-space: nowrap;
      }

      .quick-section-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .quick-section-head h5 {
        margin: 0;
        font-weight: 850;
      }

      .quick-section-head p {
        margin: 0.35rem 0 0;
        color: #64748b;
      }

      .quick-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.7rem;
        border-radius: 8px;
        background: #e7f8f4;
        color: #0f766e;
        font-size: 0.82rem;
        font-weight: 850;
        white-space: nowrap;
      }

      @media (max-width: 991px) {
        .quick-test-row {
          grid-template-columns: 1fr;
        }

        .quick-test-row .btn {
          width: 100%;
          margin-top: 0;
        }
      }

      @media (max-width: 768px) {
        .quick-section-head {
          flex-direction: column;
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
      <div class="container-fluid page-shell quick-test-page">
        <section class="page-hero">
          <div>
            <h3><i class="bi bi-gear me-2"></i>Quick Test</h3>
            <p>Choose a page label, enter the URL, and run validation checks from a repeatable quick testing workspace.</p>
          </div>
          <div class="page-hero-meta">
            <div class="hero-tile"><span>Section</span><strong>Quick Test</strong></div>
            <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
          </div>
        </section>

        <div class="test-config-section">
          <div class="card config-card surface-card">
            <div class="card-body">
              <div class="quick-section-head">
                <div>
                  <h5 class="card-title">
                    <i class="bi bi-link-45deg me-2"></i>Website Testing URLs
                  </h5>
                  <p>Add only the page URLs you want to test. Use + when you want to run multiple pages together.</p>
                </div>
                <span class="quick-chip"><i class="bi bi-ui-checks"></i>Flexible Pages</span>
              </div>

              <form id="siteConfigForm" class="site-config-form" novalidate>
                <div id="quickTestRows" class="quick-test-rows">
                  <div class="quick-test-row">
                    <div>
                      <label class="form-label">Page Label</label>
                      <select class="form-select quick-page-label" name="page_labels[]">
                        <option value="Index Page">Index Page</option>
                        <option value="Presell Page">Presell Page</option>
                        <option value="Thank You Page">Thank You Page</option>
                        <option value="Checkout Page">Checkout Page</option>
                      </select>
                    </div>

                    <div>
                      <label class="form-label">Page URL</label>
                      <input type="url" class="form-control quick-page-url" name="page_urls[]" placeholder="https://example.com/page" required>
                      <div class="invalid-feedback quick-url-error" style="display: none;"></div>
                    </div>

                    <button type="button" class="btn btn-primary btn-sm quick-row-test">
                      <i class="bi bi-play-circle me-1"></i>Test
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm quick-add" title="Add page" aria-label="Add page">
                      <i class="bi bi-plus-lg"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm quick-remove" title="Remove page" aria-label="Remove page">
                      <i class="bi bi-dash-lg"></i>
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/upsell.js?v=20260508-form-scenarios"></script>
    <script src="../assets/js/test_report.js?v=20260508-form-scenarios"></script>
  </body>
</html>
