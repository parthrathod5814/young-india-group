<?php
require_once __DIR__ . '/auth.php';

$sidebarAdmin = current_admin() ?? [];
$sidebarRole = $sidebarAdmin['role'] ?? '';

/*
 * Assumes auth.php loads the project database connection and db()
 * returns a PDO instance, as used by the dashboard.
 */
$sidebarCounts = [
    'blood' => 0,
    'activities' => 0,
    'donors' => 0,
    'volunteers' => 0,
    'inquiries' => 0,
];

try {
    $sidebarPdo = db();

    $sidebarCounts['blood'] = (int) $sidebarPdo->query(
        "SELECT COUNT(*)
         FROM blood_requests
         WHERE status IN ('Active', 'In-Transit')"
    )->fetchColumn();

    $sidebarCounts['activities'] = (int) $sidebarPdo->query(
        "SELECT COUNT(*)
         FROM activities
         WHERE status = 'Published'"
    )->fetchColumn();

    $sidebarCounts['donors'] = (int) $sidebarPdo->query(
        "SELECT COUNT(*)
         FROM donors
         WHERE status = 'Active'"
    )->fetchColumn();

    $sidebarCounts['volunteers'] = (int) $sidebarPdo->query(
        "SELECT COUNT(*)
         FROM volunteers
         WHERE status = 'Active'"
    )->fetchColumn();

    $sidebarCounts['inquiries'] = (int) $sidebarPdo->query(
        "SELECT COUNT(*)
         FROM inquiries
         WHERE status = 'New'"
    )->fetchColumn();
} catch (Throwable $e) {
    error_log('Sidebar count query failed: ' . $e->getMessage());
}

$showAdminControls = $sidebarRole === 'Super Admin';
?>

<!-- ===== SIDEBAR ===== -->
<aside class="adm-side">
  <p class="adm-side__label">OPERATIONS CORE</p>

  <a class="adm-link" href="index.php">
    <i class="fa-solid fa-table-columns"></i> Overview Dashboard
  </a>

  <div class="adm-group">
    <button type="button" class="adm-group__btn">
      <i class="fa-solid fa-droplet"></i>
      Blood Emergency
      <span class="adm-count is-red" data-badge="blood"><?= $sidebarCounts['blood'] ?></span>
      <i class="fa-solid fa-chevron-down adm-caret"></i>
    </button>
    <div class="adm-sub">
      <a href="add-blood-request.php"><i class="fa-solid fa-plus"></i> Add Request</a>
      <a href="view-blood-request.php"><i class="fa-regular fa-eye"></i> View Requests</a>
      <a href="manage-blood-request.php"><i class="fa-solid fa-table-list"></i> Manage Requests</a>
    </div>
  </div>

  <div class="adm-group">
    <button type="button" class="adm-group__btn">
      <i class="fa-solid fa-user-group"></i>
      Donor Registry
      <span class="adm-count" data-badge="donors"><?= $sidebarCounts['donors'] ?></span>
      <i class="fa-solid fa-chevron-down adm-caret"></i>
    </button>
    <div class="adm-sub">
      <a href="add-donors.php"><i class="fa-solid fa-plus"></i> Add Donor</a>
      <a href="view-donors.php"><i class="fa-regular fa-eye"></i> View Donors</a>
      <a href="manage-donors.php"><i class="fa-solid fa-table-list"></i> Manage Donors</a>
    </div>
  </div>

  <div class="adm-group">
    <button type="button" class="adm-group__btn">
      <i class="fa-solid fa-hand-holding-heart"></i>
      Volunteer Network
      <span class="adm-count" data-badge="volunteers"><?= $sidebarCounts['volunteers'] ?></span>
      <i class="fa-solid fa-chevron-down adm-caret"></i>
    </button>
    <div class="adm-sub">
      <a href="add-volunteers.php"><i class="fa-solid fa-plus"></i> Add Volunteer</a>
      <a href="view-volunteers.php"><i class="fa-regular fa-eye"></i> View Volunteers</a>
      <a href="manage-volunteers.php"><i class="fa-solid fa-table-list"></i> Manage Volunteers</a>
    </div>
  </div>

  <div class="adm-group">
    <button type="button" class="adm-group__btn">
      <i class="fa-solid fa-bullhorn"></i>
      Activities &amp; News
      <span class="adm-count" data-badge="activities"><?= $sidebarCounts['activities'] ?></span>
      <i class="fa-solid fa-chevron-down adm-caret"></i>
    </button>
    <div class="adm-sub">
      <a href="add-activities.php"><i class="fa-solid fa-plus"></i> Add Activity</a>
      <a href="view-activities.php"><i class="fa-regular fa-eye"></i> View Activities</a>
      <a href="manage-activities.php"><i class="fa-solid fa-table-list"></i> Manage Activities</a>
    </div>
  </div>

  <div class="adm-group">
    <button type="button" class="adm-group__btn">
      <i class="fa-solid fa-envelope"></i>
      Contact Inquiries
      <span class="adm-count" data-badge="inquiries"><?= $sidebarCounts['inquiries'] ?></span>
      <i class="fa-solid fa-chevron-down adm-caret"></i>
    </button>
    <div class="adm-sub">
      <a href="add-inquiry.php"><i class="fa-solid fa-plus"></i> Add Inquiry</a>
      <a href="view-inquiries.php"><i class="fa-regular fa-eye"></i> View Inquiries</a>
      <a href="manage-inquiries.php"><i class="fa-solid fa-table-list"></i> Manage Inquiries</a>
    </div>
  </div>

  <hr>
  <p class="adm-side__label">SYSTEM CONTROL</p>

  <?php if ($showAdminControls): ?>
    <div class="adm-group">
      <button type="button" class="adm-group__btn">
        <i class="fa-solid fa-user-shield"></i>
        Admin Controls
        <i class="fa-solid fa-chevron-down adm-caret"></i>
      </button>
      <div class="adm-sub">
        <a href="manage-admin.php"><i class="fa-solid fa-lock"></i> Manage Admins</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="adm-group">
    <button type="button" class="adm-group__btn">
      <i class="fa-solid fa-database"></i>
      Backup &amp; Recovery
      <i class="fa-solid fa-chevron-down adm-caret"></i>
    </button>
    <div class="adm-sub">
      <a href="backup-create.php"><i class="fa-solid fa-cloud-arrow-up"></i> Create Backup</a>
      <a href="backup-restore.php"><i class="fa-solid fa-clock-rotate-left"></i> Restore Data</a>
    </div>
  </div>
</aside>