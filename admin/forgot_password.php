<?php
session_start();
if (isset($_GET['reset'])) {
    unset($_SESSION['reset_account_email'], $_SESSION['reset_account_type']);
}

$step = 'email';
$email = trim($_POST['email'] ?? ($_SESSION['reset_account_email'] ?? ''));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        $error = 'Database connection failed.';
    } elseif (isset($_POST['check_email'])) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("
                SELECT id, 'admin' AS account_type
                FROM admin
                WHERE email = ?
                UNION ALL
                SELECT id, 'user' AS account_type
                FROM users
                WHERE email = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('ss', $email, $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($accountId, $accountType);
                    $stmt->fetch();
                    $_SESSION['reset_account_email'] = $email;
                    $_SESSION['reset_account_type'] = $accountType === 'admin' ? 'admin' : 'users';
                    $step = 'password';
                } else {
                    unset($_SESSION['reset_account_email'], $_SESSION['reset_account_type']);
                    $error = 'User does not exist.';
                }

                $stmt->close();
            } else {
                $error = 'Unable to check user email.';
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $email = trim($_SESSION['reset_account_email'] ?? $email);
        $accountType = $_SESSION['reset_account_type'] ?? '';
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $step = 'password';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($accountType, ['admin', 'users'], true)) {
            $step = 'email';
            $error = 'Please verify your email again.';
        } elseif ($password === '' || strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password and confirm password do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE {$accountType} SET password = ? WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $hash, $email);
                $stmt->execute();

                if ($stmt->affected_rows >= 0) {
                    unset($_SESSION['reset_account_email'], $_SESSION['reset_account_type']);
                    $_SESSION['success'] = 'Password changed successfully. Please login with your new password.';
                    $stmt->close();
                    header('Location: login.php');
                    exit;
                }

                $stmt->close();
                $error = 'Unable to update password.';
            } else {
                $error = 'Unable to update password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
      .reset-shell {
        width: min(94vw, 480px);
      }

      .reset-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        height: 46px;
        border-radius: 8px;
        background: #e7f8f4;
        color: #0f766e;
        font-size: 1.35rem;
        margin-bottom: 0.75rem;
      }

      .was-validated .input-group:has(.form-control:invalid) + .invalid-feedback {
        visibility: visible;
      }
    </style>
  </head>
  <body>
    <main class="auth-bg d-flex align-items-center justify-content-center">
      <div class="card shadow-sm card-login reset-shell">
        <div class="card-body p-4">
          <div class="text-center">
            <span class="reset-icon"><i class="bi bi-key"></i></span>
            <h4 class="card-title mb-2">Forgot Password</h4>
            <p class="text-muted small mb-3">
              <?php echo $step === 'password' ? 'Create a new password for your admin account.' : 'Enter your admin email to continue.'; ?>
            </p>
          </div>

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

          <?php if ($step === 'password'): ?>
            <form method="post" class="needs-validation" novalidate>
              <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
              <div class="mb-3">
                <label class="form-label">Verified Email</label>
                <div class="form-control bg-light"><?php echo htmlspecialchars($email); ?></div>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Minimum 8 characters" minlength="8" required>
                </div>
                <div class="invalid-feedback">Please enter a password with at least 8 characters.</div>
              </div>

              <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" minlength="8" required>
                </div>
                <div class="invalid-feedback">Please confirm your password.</div>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" name="reset_password" class="btn btn-primary">
                  <i class="bi bi-check2-circle"></i>Submit
                </button>
                <a href="forgot_password.php?reset=1" class="btn btn-outline-secondary">Use Different Email</a>
              </div>
            </form>
          <?php else: ?>
            <form method="post" class="needs-validation" novalidate>
              <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="name@example.com" required>
                </div>
                <div class="invalid-feedback">Please enter a valid email address.</div>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" name="check_email" class="btn btn-primary">
                  <i class="bi bi-arrow-right-circle"></i>Submit
                </button>
                <a href="login.php" class="btn btn-outline-secondary">Back to Login</a>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      (() => {
        'use strict';
        document.querySelectorAll('.needs-validation').forEach((form) => {
          form.addEventListener('submit', (event) => {
            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');

            if (password && confirmPassword) {
              confirmPassword.setCustomValidity(
                password.value === confirmPassword.value ? '' : 'Passwords do not match.'
              );
            }

            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }

            form.classList.add('was-validated');
          }, false);
        });
      })();
    </script>
  </body>
</html>
