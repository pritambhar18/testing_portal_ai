<?php
// admin/manage_users.php
session_start();

// Check admin session
if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['admin_email'];

// Flash messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../config/db.php';
$users = [];
$totalUsers = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $countQuery = $conn->query('SELECT COUNT(*) AS total_users FROM users');
    if ($countQuery) {
        $row = $countQuery->fetch_assoc();
        $totalUsers = (int)$row['total_users'];
    }

    $result = $conn->query('SELECT id, name, email, created_at FROM users ORDER BY created_at DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users</title>
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
            <h3>Manage Users</h3>
            <p>Create, review, and maintain access for portal users from a single administration screen.</p>
          </div>
          <div class="page-hero-meta">
            <div class="hero-tile"><span>Total Users</span><strong><?php echo number_format($totalUsers); ?></strong></div>
            <div class="hero-tile"><span>Signed In</span><strong><?php echo htmlspecialchars($admin_email); ?></strong></div>
          </div>
        </section>

        <section class="surface-card">
          <div class="card-body">
            <div class="section-head">
              <div>
                <h5>User Directory</h5>
                <p>Review user records and manage edit or delete actions.</p>
              </div>
              <a href="create_user.php" class="btn btn-primary">Create User</a>
            </div>
            <div class="data-card table-responsive">
            <table class="table data-table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Created Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr><td colspan="5" class="text-center empty-panel">No users found.</td></tr>
                <?php else: ?>
                  <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($u['id']); ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))); ?></td>
                    <td>
                      <a href="edit_user.php?id=<?php echo urlencode($u['id']); ?>" class="btn btn-sm btn-primary me-1">Edit</a>
                      <a href="delete_user.php?id=<?php echo urlencode($u['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
            </div>
          </div>
        </section>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
