<?php
require_once __DIR__ . '/auth.php';


$admin = current_admin() ?? [];
$adminName = $admin['name'] ?? 'Admin';
$adminRole = $admin['role'] ?? 'Admin';

if (empty($_SESSION['logout_csrf_token'])) {
    $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
}

$logoutCsrfToken = $_SESSION['logout_csrf_token'];
?>

<!-- ===== TOP BAR ===== -->
<header class="adm-top">
  <div class="adm-top__brand">
    <button class="adm-menu-btn" id="menuBtn" aria-label="Open menu">
      <i class="fa-solid fa-bars"></i>
    </button>

    <img src="../assets/logo.png" alt="Young India Group logo">

    <div>
      <h1>
        Young India Group – Deven Rabari Foundation
        <span class="adm-tag">MORBI HQ</span>
      </h1>
      <small>
        <i class="fa-solid fa-circle" style="color:#4ade80;font-size:8px"></i>
        Admin Command Portal • Morbi Secretariat • Category:
        <?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?>
      </small>
    </div>
  </div>

  <div class="adm-top__right">
    <span class="adm-sync">
      <i class="fa-solid fa-rotate"></i> Live Sync: Operational 24/7
    </span>

    <button class="adm-bell" id="bellBtn" aria-label="Notifications">
      <i class="fa-regular fa-bell"></i>
      <b id="bellCount" hidden>0</b>
    </button>

    <div class="adm-user">
      <div>
        <strong><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></strong>
        <small><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></small>
      </div>
      <span class="adm-avatar">
        <?= htmlspecialchars(strtoupper(substr($adminName, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>

<form id="logoutForm" method="post" action="logout.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <button id="logoutBtn" type="button" class="adm-logout" aria-label="Logout">
    <i class="fa-solid fa-right-from-bracket"></i>
  </button>
</form>
  </div>
</header>