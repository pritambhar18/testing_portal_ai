<?php
// admin/dashboard.php
session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];

$usersCount = 0;
$reportsCount = 0;
require_once __DIR__ . '/../config/db.php';
if (isset($conn) && $conn instanceof mysqli) {
    $result = $conn->query('SELECT COUNT(*) AS total FROM users');
    if ($result) {
        $row = $result->fetch_assoc();
        $usersCount = (int)($row['total'] ?? 0);
        $result->free();
    }

    $reportsResult = $conn->query('SELECT COUNT(*) AS total FROM test_reports');
    if ($reportsResult) {
        $row = $reportsResult->fetch_assoc();
        $reportsCount = (int)($row['total'] ?? 0);
        $reportsResult->free();
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="../assets/css/style.css" rel="stylesheet">
  </head>
  <body>
    <!-- Topbar -->
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
      <div class="container-fluid page-shell">
          <?php
          // Show flash messages from session
          if (!empty($_SESSION['success'])) {
              echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
              unset($_SESSION['success']);
          }
          if (!empty($_SESSION['error'])) {
              echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
              unset($_SESSION['error']);
          }
          ?>
          <section class="page-hero">
            <div>
              <h3>Dashboard</h3>
              <p>Overview of the testing portal workspace, user access, and the core admin areas you manage from one place.</p>
            </div>
            <div class="page-hero-meta">
              <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
              <div class="hero-tile"><span>Total Users</span><strong><?php echo number_format($usersCount); ?></strong></div>
            </div>
          </section>

          <div class="dashboard-grid">
            <section class="stat-card surface-card">
              <div>
                <div class="stat-number"><?php echo number_format($usersCount); ?></div>
                <div class="stat-label">Total Users</div>
              </div>
              <span class="stat-icon"><i class="bi bi-people"></i></span>
            </section>

            <section class="stat-card surface-card">
              <div>
                <div class="stat-number"><?php echo number_format($reportsCount); ?></div>
                <div class="stat-label">Generated Reports</div>
              </div>
              <span class="stat-icon"><i class="bi bi-file-earmark-text"></i></span>
            </section>

            <section class="dashboard-welcome surface-card">
              <div class="card-body">
                <h5 class="card-title">Welcome</h5>
                <p class="card-text">Use the sidebar to manage users, configure tests, and review generated reports without layout breaks.</p>
              </div>
            </section>
          </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
