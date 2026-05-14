<?php
// admin/sidebar.php - reusable sidebar and offcanvas for mobile
$current = basename($_SERVER['PHP_SELF']);
$isReportsSection = in_array($current, ['view_reports.php', 'view_report_details.php'], true);
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
      <a class="nav-link <?php echo $isReportsSection ? 'active' : ''; ?>" href="view_reports.php"><i class="bi bi-file-earmark-pdf me-2"></i> View Reports</a>
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
        <a class="nav-link <?php echo $isReportsSection ? 'active' : ''; ?>" href="view_reports.php"><i class="bi bi-file-earmark-pdf me-2"></i> View Reports</a>
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

<div class="modal fade portal-logout-modal" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <div class="logout-modal-icon">
          <i class="bi bi-box-arrow-right"></i>
        </div>
        <h5 class="modal-title" id="logoutConfirmTitle">Logout from portal?</h5>
        <p class="logout-modal-copy">Your current admin session will be closed and you will return to the login page.</p>
        <div class="logout-modal-actions">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <a href="../actions/logout.php" class="btn btn-danger" id="confirmLogoutButton">
            <i class="bi bi-box-arrow-right"></i>Logout
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const logoutModalElement = document.getElementById('logoutConfirmModal');
    const confirmLogoutButton = document.getElementById('confirmLogoutButton');
    let pendingLogoutHref = '../actions/logout.php';

    document.querySelectorAll('a[href$="logout.php"]').forEach(function (link) {
      if (link.id === 'confirmLogoutButton') {
        return;
      }

      link.addEventListener('click', function (event) {
        event.preventDefault();
        pendingLogoutHref = link.getAttribute('href') || '../actions/logout.php';
        confirmLogoutButton.setAttribute('href', pendingLogoutHref);

        if (window.bootstrap && logoutModalElement) {
          const openSidebar = document.getElementById('mobileSidebar');
          if (openSidebar) {
            const sidebarInstance = bootstrap.Offcanvas.getInstance(openSidebar);
            if (sidebarInstance) {
              sidebarInstance.hide();
            }
          }
          bootstrap.Modal.getOrCreateInstance(logoutModalElement).show();
        } else if (window.confirm('Are you sure you want to logout from the portal?')) {
          window.location.href = pendingLogoutHref;
        }
      });
    });
  });
</script>
