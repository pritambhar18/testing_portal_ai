<?php
// admin/sidebar.php - reusable sidebar and offcanvas for mobile
$current = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar (desktop) -->
<nav class="sidebar">
  <div class="mb-4 text-center brand">Testing Portal</div>
  <ul class="nav nav-pills flex-column">
    <li class="nav-item mb-1">
      <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    </li>
    <li class="nav-item mb-1">
      <a class="nav-link <?php echo $current === 'create_user.php' ? 'active' : ''; ?>" href="create_user.php"><i class="bi bi-person-plus me-2"></i> Create User</a>
    </li>
    <li class="nav-item mb-1">
      <a class="nav-link <?php echo $current === 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php"><i class="bi bi-people me-2"></i> Manage Users</a>
    </li>
    <li class="nav-item mb-1">
      <a class="nav-link <?php echo $current === 'test_configuration.php' ? 'active' : ''; ?>" href="test_configuration.php"><i class="bi bi-gear me-2"></i> Quick Test</a>
    </li>
    <li class="nav-item mb-1">
      <a class="nav-link <?php echo $current === 'view_reports.php' ? 'active' : ''; ?>" href="view_reports.php"><i class="bi bi-file-earmark-pdf me-2"></i> View Reports</a>
    </li>
    <li class="nav-item mb-1">
      <a class="nav-link <?php echo $current === 'order_flow_check.php' ? 'active' : ''; ?>" href="order_flow_check.php"><i class="bi bi-shuffle me-2"></i> 88startech</a>
    </li>
    <li class="nav-item mt-3">
      <a class="nav-link text-danger" href="../actions/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
    </li>
  </ul>
</nav>

<!-- Offcanvas sidebar for small screens -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="mobileSidebarLabel">Testing Portal</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="nav nav-pills flex-column">
      <li class="nav-item mb-1">
        <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link <?php echo $current === 'create_user.php' ? 'active' : ''; ?>" href="create_user.php"><i class="bi bi-person-plus me-2"></i> Create User</a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link <?php echo $current === 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php"><i class="bi bi-people me-2"></i> Manage Users</a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link <?php echo $current === 'test_configuration.php' ? 'active' : ''; ?>" href="test_configuration.php"><i class="bi bi-gear me-2"></i> Quick Test</a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link <?php echo $current === 'view_reports.php' ? 'active' : ''; ?>" href="view_reports.php"><i class="bi bi-file-earmark-pdf me-2"></i> View Reports</a>
      </li>
      <li class="nav-item mb-1">
        <a class="nav-link <?php echo $current === 'order_flow_check.php' ? 'active' : ''; ?>" href="order_flow_check.php"><i class="bi bi-shuffle me-2"></i> 88startech</a>
      </li>
      <li class="nav-item mt-3">
        <a class="nav-link text-danger" href="../actions/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
      </li>
    </ul>
  </div>
</div>
