<?php
// Simple Admin Login Page
session_start();
$old_email = '';
if (!empty($_SESSION['old']['email'])) {
    $old_email = htmlspecialchars($_SESSION['old']['email']);
    unset($_SESSION['old']);
}
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
      .login-shell {
        width: min(94vw, 460px);
      }

      .login-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        margin-bottom: 1rem;
        color: #111827;
        font-weight: 850;
        font-size: 1.35rem;
      }

      .login-brand-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: #e7f8f4;
        color: #0f766e;
      }

      .login-helper {
        color: #64748b;
        line-height: 1.55;
      }

      .auth-link-row {
        display: flex;
        justify-content: flex-end;
      }

      .was-validated .input-group:has(.form-control:invalid) + .invalid-feedback {
        visibility: visible;
      }
    </style>
  </head>
  <body>
    <main class="auth-bg d-flex align-items-center justify-content-center">
      <div class="card shadow-sm card-login login-shell">
        <div class="card-body p-4">
          <div class="login-brand">
            <span class="login-brand-icon"><i class="bi bi-shield-lock"></i></span>
            <span>Testing Portal</span>
          </div>
          <h4 class="card-title mb-2 text-center">Admin Login</h4>
          <p class="login-helper small text-center mb-3">Sign in with your admin account to manage users, tests, and reports.</p>
          <div class="alert-placeholder mb-3">
            <?php if ($error): ?>
              <div class="alert alert-danger mb-0">
                <i class="bi bi-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?>
              </div>
            <?php elseif ($success): ?>
              <div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($success); ?>
              </div>
            <?php endif; ?>
          </div>

          <form method="post" action="../actions/login_action.php" class="needs-validation" novalidate>
            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo $old_email; ?>" placeholder="name@example.com" required>
              </div>
              <div class="invalid-feedback">Please enter a valid email.</div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-key"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required minlength="6">
              </div>
              <div class="invalid-feedback">Please enter your password (min 6 characters).</div>
            </div>

            <div class="auth-link-row mb-3">
              <a href="forgot_password.php" class="small">Forgot Password?</a>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i>Login</button>
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
