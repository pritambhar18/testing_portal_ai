<?php
// admin/edit_user.php
session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: manage_users.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $validationErrors = [];
    if ($name === '') { $validationErrors[] = 'Name is required.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $validationErrors[] = 'Valid email is required.'; }

    if (empty($validationErrors)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $validationErrors[] = 'Email is already taken by another user.';
            }
            $stmt->close();
        }
    }

    if (empty($validationErrors)) {
        $stmt = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ssi', $name, $email, $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'User updated successfully.';
                $stmt->close();
                header('Location: manage_users.php');
                exit;
            }
            $validationErrors[] = 'Unable to update user.';
            $stmt->close();
        } else {
            $validationErrors[] = 'Database error preparing update.';
        }
    }

    $error = implode(' ', $validationErrors);
}

// Fetch user data for initial fill
$stmt = $conn->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    die('Unable to load user data.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($name, $email);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: manage_users.php');
    exit;
}
$stmt->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
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
      <div class="container-fluid page-shell">
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <section class="page-hero">
          <div>
            <h3>Edit User</h3>
            <p>Update the user record carefully and keep account details accurate across the portal.</p>
          </div>
          <div class="page-hero-meta">
            <div class="hero-tile"><span>User ID</span><strong><?php echo (int)$id; ?></strong></div>
            <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
          </div>
        </section>

        <section class="surface-card">
          <div class="card-body form-surface">
            <div class="section-head">
              <div>
                <h5>User Profile</h5>
                <p>Adjust the core profile fields below and save the updated record.</p>
              </div>
            </div>
            <form method="post" class="needs-validation" novalidate>
              <div class="form-grid">
              <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                <div class="invalid-feedback">Enter a name.</div>
              </div>

              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <div class="invalid-feedback">Enter a valid email.</div>
              </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="manage_users.php" class="btn btn-outline-secondary">Cancel</a>
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
        Array.prototype.slice.call(forms).forEach(function (form) {
          form.addEventListener('submit', function (event) {
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
