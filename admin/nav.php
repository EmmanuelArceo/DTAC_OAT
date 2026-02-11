<?php

if (!function_exists('active')) {
    function active($page, $current) { return $page === $current ? 'active' : ''; }
}
if (session_status() === PHP_SESSION_NONE) session_start();

$current = basename($_SERVER['PHP_SELF'] ?? '');

// Allow both admin and super_admin roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header("Location: ../login.php");
    exit;
}

// Avatar fallback and DB fetch
$avatar = 'uploads/noimg.png';
if (isset($_SESSION['avatar']) && $_SESSION['avatar'] && file_exists('../' . $_SESSION['avatar'])) {
    $avatar = $_SESSION['avatar'];
} else if (isset($_SESSION['user_id'])) {
    // Fetch from DB if session avatar is missing
    include_once '../db.php';
    $admin_id = (int)$_SESSION['user_id'];
    $result = $oat->query("SELECT profile_img FROM users WHERE id = $admin_id LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        if (!empty($row['profile_img']) && file_exists('../' . $row['profile_img'])) {
            $avatar = $row['profile_img'];
            $_SESSION['avatar'] = $avatar; // update session for future requests
        }
    }
}

// Fetch user info if not set in session
if (
    empty($_SESSION['fname']) ||
    empty($_SESSION['lname']) ||
    !isset($_SESSION['mname'])
) {
    include_once '../db.php';
    $admin_id = (int)$_SESSION['user_id'];
    $result = $oat->query("SELECT fname, mname, lname, profile_img FROM users WHERE id = $admin_id LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $_SESSION['fname'] = $row['fname'];
        $_SESSION['lname'] = $row['lname'];
        $_SESSION['mname'] = $row['mname'];
        // Optionally update avatar if not set
        if (!empty($row['profile_img']) && file_exists('../' . $row['profile_img'])) {
            $avatar = $row['profile_img'];
            $_SESSION['avatar'] = $avatar;
        }
    }
}

?>
<!-- restore collapsed state early to avoid flicker -->
<script>
(function(){
  try {
    if (localStorage.getItem('sidebarCollapsed') === '1') document.documentElement.classList.add('sb-collapsed');
    document.documentElement.classList.add('no-transitions');
  } catch(e){}
})();
</script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{ --sb-w:220px; --sb-cw:72px; }

/* Desktop: fixed sidebar and push page content by padding on body */
#sb-sidebar {
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  width: var(--sb-w);
  z-index: 1040;
  transition: transform .22s ease, width .18s ease;
}

/* Push content so it doesn't go under the sidebar (no need for pageContent margin) */
body {
  padding-left: var(--sb-w);
  transition: padding-left .18s ease;
  font-family: 'Inter', sans-serif;
}
#pageContent { margin-left: 0; }

/* Collapsed state: reduce the body padding to collapsed width */
html.sb-collapsed body {
  padding-left: var(--sb-cw);
}

/* Mobile: remove left padding and use offcanvas-like show/hide for sidebar */
@media (max-width: 991.98px) {
  body { padding-left: 0 !important; }
  #sb-sidebar { display: none !important; }
  #top-navbar { display: flex !important; }
}

/* Hide top nav on desktop */
#top-navbar { display: none; }

@media (max-width: 991.98px) {
  #top-navbar { display: flex !important; }
}

/* Sidebar styles remain unchanged */
#sb-sidebar{ position:fixed; top:0; left:0; height:100vh; width:var(--sb-w); padding:1rem; background:#fff; border-right:1px solid rgba(0,0,0,.04); box-shadow:0 8px 24px rgba(10,10,10,.04); transition:width .18s ease; z-index:1040; overflow:hidden; }
html.sb-collapsed #sb-sidebar{ width:var(--sb-cw); }
#pageContent{ margin-left:calc(var(--sb-w) + 16px); transition:margin-left .18s ease; padding:1.25rem; }
html.sb-collapsed #pageContent{ margin-left:calc(var(--sb-cw) + 16px); }
html.no-transitions #sb-sidebar, html.no-transitions #pageContent{ transition:none !important; }
.sb-icon { width:36px; height:36px; display:grid; place-items:center; border-radius:.6rem; background:#f1f5f9; color:#0f172a; flex:0 0 36px; }
.nav-label{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:opacity .12s ease, max-width .18s ease; max-width:160px; opacity:1; }
html.sb-collapsed .nav-label{ max-width:0; opacity:0; }
.nav-link.active{ background:linear-gradient(90deg, rgba(6,182,212,.08), rgba(16,185,129,.04)); box-shadow: inset 3px 0 0 #06b6d4; color:#042022; font-weight:600; border-radius:.5rem; }
.nav-link:hover{ transform: translateX(4px); transition: transform .12s ease; }
.sb-nav{ overflow-y:auto; max-height:calc(100vh - 220px); scrollbar-width:none; -ms-overflow-style:none; }
.sb-nav::-webkit-scrollbar{ display:none; }
.sb-actions .label{ transition:opacity .12s ease, max-width .18s ease; }
html.sb-collapsed .sb-actions .label{ max-width:0; opacity:0; }
</style>

<!-- Top Navbar for Mobile -->
<nav id="top-navbar" class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-2 py-2" style="z-index:1050;">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="adminprofileedit.php">
      <img src="../<?php echo htmlspecialchars($avatar); ?>?t=<?php echo time(); ?>" alt="avatar" class="rounded me-2" style="width:36px;height:36px;object-fit:cover">
      <span class="fw-semibold"><?php echo htmlspecialchars(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')); ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
  </div>
</nav>

<nav id="sb-sidebar" class="d-none d-lg-flex flex-column" aria-label="Sidebar">
  <div class="d-flex align-items-center mb-2">
    <button id="sbToggleBtn" class="btn btn-sm btn-outline-secondary d-lg-none" aria-label="Toggle sidebar" aria-pressed="false"><i class="bi bi-list"></i></button>
  </div>

  <a href="adminprofileedit.php" class="d-flex align-items-center text-decoration-none mb-3">
    <img src="../<?php echo htmlspecialchars($avatar); ?>?t=<?php echo time(); ?>" alt="avatar" class="rounded me-2" style="width:44px;height:44px;object-fit:cover">
    <div class="d-flex flex-column">
      <span class="fw-semibold"><?php echo htmlspecialchars(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')); ?></span>
      <small class="text-muted">Administrator</small>
    </div>
  </a>

  <div class="sb-nav nav flex-column gap-1" role="menu">
    <a class="nav-link d-flex align-items-center <?php echo active('admin.php',$current); ?>" href="admin.php" title="Dashboard" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-speedometer2"></i></div>
      <div class="nav-label ms-2">Dashboard</div>
    </a>

    <a class="nav-link d-flex align-items-center <?php echo active('manageojt.php',$current); ?>" href="manageojt.php" title="Manage OJT" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-people"></i></div>
      <div class="nav-label ms-2">Manage OJT</div>
    </a>

    <a class="nav-link d-flex align-items-center <?php echo active('activeojt.php',$current); ?>" href="activeojt.php" title="Active OJT" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-person-check"></i></div>
      <div class="nav-label ms-2">Active OJT</div>
    </a>

    <a class="nav-link d-flex align-items-center <?php echo active('otreports.php',$current); ?>" href="otreports.php" title="OT Reports" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-journal-text"></i></div>
      <div class="nav-label ms-2">OT Reports</div>
    </a>

    <a class="nav-link d-flex align-items-center <?php echo active('dtruserview.php',$current); ?>" href="dtruserview.php" title="DTR View" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-clock-history"></i></div>
      <div class="nav-label ms-2">DTR View</div>
    </a>

    <a class="nav-link d-flex align-items-center <?php echo active('sitesettings.php',$current); ?>" href="sitesettings.php" title="Site Settings" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-gear"></i></div>
      <div class="nav-label ms-2">Site Settings</div>
    </a>

    <a class="nav-link d-flex align-items-center <?php echo active('qr_generator.php',$current); ?>" href="qr_generator.php" title="QR Generator" data-bs-toggle="tooltip" data-bs-placement="right">
      <div class="sb-icon"><i class="bi bi-upc-scan"></i></div>
      <div class="nav-label ms-2">QR Generator</div>
    </a>
  </div>

  <div class="sb-actions d-flex gap-2 mt-auto">
    <a href="logout.php" class="btn btn-danger d-flex align-items-center justify-content-center" title="Logout" data-bs-toggle="tooltip" data-bs-placement="right">
      <i class="bi bi-box-arrow-right"></i>
      <span class="label ms-2">Logout</span>
    </a>
  </div>
</nav>

<!-- mobile offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="mobileSidebarLabel">Menu</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <a href="adminprofileedit.php" class="d-flex align-items-center mb-3 text-decoration-none">
      <img src="../<?php echo htmlspecialchars($avatar); ?>?t=<?php echo time(); ?>" class="rounded me-2" style="width:44px;height:44px;object-fit:cover" alt="avatar">
      <div>
        <div class="fw-semibold"><?php echo htmlspecialchars(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')); ?></div>
        <div class="text-muted" style="font-size:.82rem">Administrator</div>
      </div>
    </a>

    <div class="list-group">
      <a class="list-group-item list-group-item-action <?php echo active('admin.php',$current); ?>" href="admin.php">Dashboard</a>
      <a class="list-group-item list-group-item-action <?php echo active('manageojt.php',$current); ?>" href="manageojt.php">Manage OJT</a>
      <a class="list-group-item list-group-item-action <?php echo active('activeojt.php',$current); ?>" href="activeojt.php">Active OJT</a>
      <a class="list-group-item list-group-item-action <?php echo active('otreports.php',$current); ?>" href="otreports.php">OT Reports</a>
      <a class="list-group-item list-group-item-action <?php echo active('dtruserview.php',$current); ?>" href="dtruserview.php">DTR View</a>
      <a class="list-group-item list-group-item-action <?php echo active('sitesettings.php',$current); ?>" href="sitesettings.php">Site Settings</a>
      <a class="list-group-item list-group-item-action <?php echo active('qr_generator.php',$current); ?>" href="qr_generator.php">QR Generator</a>
    </div>

    <div class="mt-3 d-flex gap-2">
      <a href="../logout.php" class="btn btn-danger flex-fill">Logout</a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const root = document.documentElement;
  requestAnimationFrame(()=> root.classList.remove('no-transitions'));
  if (localStorage.getItem('sidebarCollapsed') === '1') root.classList.add('sb-collapsed');
  const sbToggle = document.getElementById('sbToggleBtn');
  if (sbToggle) {
    sbToggle.addEventListener('click', () => {
      const collapsed = root.classList.toggle('sb-collapsed');
      localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
      sbToggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
    });
  }
});
</script>