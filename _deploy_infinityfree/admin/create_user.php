<?php
// admin/create_user.php
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
    <title>Create User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
          // Show flash messages
          if (!empty($_SESSION['success'])) {
              echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
              unset($_SESSION['success']);
          }
          if (!empty($_SESSION['error'])) {
              echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
              unset($_SESSION['error']);
          }
          // old inputs
          $old = [];
          if (!empty($_SESSION['old'])) { $old = $_SESSION['old']; unset($_SESSION['old']); }
          ?>
          <section class="page-hero">
            <div>
              <h3>Create User</h3>
              <p>Add a new portal user with the required account details and assign access cleanly from this screen.</p>
            </div>
            <div class="page-hero-meta">
              <div class="hero-tile"><span>Section</span><strong>User Provisioning</strong></div>
              <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
            </div>
          </section>

          <section class="surface-card">
            <div class="card-body form-surface">
              <div class="section-head">
                <div>
                  <h5>New User Details</h5>
                  <p>Complete the user profile fields below to create a new account.</p>
                </div>
              </div>
              <form method="post" action="../actions/create_user_action.php" class="needs-validation" novalidate>
                <div class="form-grid">
                <div class="mb-3">
                  <label for="name" class="form-label">Name</label>
                  <input type="text" class="form-control" id="name" name="name" placeholder="Full name" value="<?php echo isset($old['name'])?htmlspecialchars($old['name']):''; ?>" required>
                  <div class="invalid-feedback">Please enter the name.</div>
                </div>

                <div class="mb-3">
                  <label for="email" class="form-label">Email</label>
                  <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?php echo isset($old['email'])?htmlspecialchars($old['email']):''; ?>" required>
                  <div class="invalid-feedback">Please enter a valid email.</div>
                </div>

                <div class="mb-3">
                  <label for="password" class="form-label">Password</label>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Password (min 8 chars)" minlength="8" required>
                  <div class="invalid-feedback">Please enter a password (min 8 characters).</div>
                </div>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn btn-primary">Create User</button>
                  <a href="manage_users.php" class="btn btn-outline-secondary">Back to Users</a>
                </div>
              </form>
            </div>
          </section>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })()
    </script>
  </body>
</html>
