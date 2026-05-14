<?php
// Simple Admin Login Page
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
  </head>
  <body>
    <main class="auth-bg d-flex align-items-center justify-content-center">
      <div class="card shadow-sm card-login">
        <div class="card-body p-4">
          <h4 class="card-title mb-3 text-center">Admin Login</h4>
          <p class="text-muted small text-center">Sign in with your admin account</p>
          <?php
          session_start();
          // Show inline error if exists - place inside placeholder to reserve space
          $old_email = '';
          if (!empty($_SESSION['old']['email'])) { $old_email = htmlspecialchars($_SESSION['old']['email']); }
          ?>
          <div class="alert-placeholder mb-3">
            <?php
            if (!empty($_SESSION['error'])) {
                echo '<div class="alert alert-danger mb-0">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            ?>
          </div>

          <form method="post" action="../actions/login_action.php" class="needs-validation" novalidate>
            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input type="email" class="form-control" id="email" name="email" value="<?php echo $old_email; ?>" placeholder="name@example.com" required>
              <div class="invalid-feedback">Please enter a valid email.</div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required minlength="6">
              <div class="invalid-feedback">Please enter your password (min 6 characters).</div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <a href="forgot_password.php" class="small">Forgot Password?</a>
              </div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Login</button>
            </div>
          </form>
        </div>
        <div class="card-footer text-center py-2">
          <small class="text-muted">&copy; <?php echo date('Y'); ?> Testing Portal</small>
        </div>
      </div>
    </main>

    <!-- Bootstrap JS bundle (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Bootstrap client-side validation
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
