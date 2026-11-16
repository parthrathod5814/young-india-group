<?php
require_once __DIR__ . '/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Handle "Delete all notifications" POSTs on the current admin page.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['header_action'] ?? '') === 'delete_all_notifications'
) {
    header('Content-Type: application/json; charset=utf-8');

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['notification_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid security token.']);
        exit;
    }

    try {
        $pdo = db();
        $pdo->exec('DELETE FROM notifications');

        // Deliberately retain notification_reads as read-history.
        echo json_encode([
            'ok' => true,
            'message' => 'All notifications deleted.',
        ]);
    } catch (Throwable $e) {
        error_log('Unable to delete notifications: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to delete notifications.',
        ]);
    }

    exit;
}

$admin = current_admin() ?? [];
$adminId = (int) ($admin['id'] ?? 0);
$adminName = $admin['name'] ?? 'Admin';
$adminRole = $admin['role'] ?? 'Admin';

if (empty($_SESSION['logout_csrf_token'])) {
    $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['notification_csrf_token'])) {
    $_SESSION['notification_csrf_token'] = bin2hex(random_bytes(32));
}

$notificationCsrfToken = $_SESSION['notification_csrf_token'];

$notifications = [];
$unreadNotificationCount = 0;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT
            n.id,
            n.item_key,
            n.type,
            n.title,
            n.message,
            n.entity,
            n.entity_id,
            n.created_at,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM notification_reads AS nr
                    WHERE nr.admin_id = :read_admin_id
                      AND nr.item_key = n.item_key
                ) THEN 1
                ELSE 0
            END AS is_read
         FROM notifications AS n
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT 50'
    );

    $stmt->execute([':read_admin_id' => $adminId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifications as $notification) {
        if (!(int) $notification['is_read']) {
            $unreadNotificationCount++;
        }
    }
} catch (Throwable $e) {
    error_log('Unable to load admin notifications: ' . $e->getMessage());
}
?>

<header class="adm-top">
  <div class="adm-top__brand">
    <button
      class="adm-menu-btn"
      id="menuBtn"
      type="button"
      aria-label="Open menu"
    >
      <i class="fa-solid fa-bars"></i>
    </button>

    <img src="../assets/logo.png" alt="Young India Group logo">

    <div>
      <h1>
        Young India Group – The Deven Rabari Foundation
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

    <button
      class="adm-bell"
      id="bellBtn"
      type="button"
      aria-label="Notifications"
    >
      <i class="fa-regular fa-bell"></i>
      <b id="bellCount" <?= $unreadNotificationCount > 0 ? '' : 'hidden' ?>>
        <?= (int) $unreadNotificationCount ?>
      </b>
    </button>

    <div class="adm-user">
      <div>
        <strong><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></strong>
        <small><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></small>
      </div>
      <span class="adm-avatar">
        <?= htmlspecialchars(
            strtoupper(substr((string) $adminName, 0, 1)),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
      </span>
    </div>

    <form id="logoutForm" method="post" action="logout.php">
      <input
        type="hidden"
        name="csrf"
        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
      >
      <button
        id="logoutBtn"
        type="button"
        class="adm-logout"
        aria-label="Logout"
      >
        <i class="fa-solid fa-right-from-bracket"></i>
      </button>
    </form>
  </div>
</header>

<!-- Shared confirmation popup. Include this only once per page. -->
<div class="adm-modal" id="modal-confirm" hidden>
  <div class="adm-modal__box" role="dialog" aria-modal="true">
    <div class="adm-modal__head">
      <h3>
        <i class="fa-solid fa-triangle-exclamation" data-slot="icon"></i>
        <span data-slot="title">Confirm</span>
      </h3>
      <button
        type="button"
        class="adm-x"
        data-close
        aria-label="Close"
      >&times;</button>
    </div>

    <div class="adm-modal__body">
      <p data-slot="message"></p>
    </div>

    <div class="adm-modal__foot">
      <button type="button" class="adm-btn" data-close>Cancel</button>
      <button
        type="button"
        class="adm-btn adm-btn--danger"
        data-slot="confirm"
      >Yes</button>
    </div>
  </div>
</div>

<!-- Shared notifications popup. Include this only once per page. -->
<div class="adm-modal" id="modal-notifications" hidden>
  <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
    <div class="adm-modal__head">
      <h3>
        <i class="fa-solid fa-bell" data-slot="icon"></i>
        <span data-slot="title">Notifications</span>
      </h3>
      <button
        type="button"
        class="adm-x"
        data-close
        aria-label="Close"
      >&times;</button>
    </div>

    <div class="adm-modal__body">
      <div class="adm-notes" data-slot="list">
        <?php if (!$notifications): ?>
          <p>No notifications.</p>
        <?php else: ?>
          <?php foreach ($notifications as $notification): ?>
            <div class="adm-notice <?= (int) $notification['is_read'] ? '' : 'is-unread' ?>">
              <span class="adm-notice__icon">
                <i class="fa-solid fa-bell"></i>
              </span>

              <div>
                <strong>
                  <?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?>
                </strong>
                <small>
                  <?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?>
                </small>
              </div>

              <time>
                <?= htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8') ?>
              </time>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="adm-modal__foot">
      <button type="button" class="adm-btn" data-close>Close</button>

      <button
        type="button"
        class="adm-btn adm-btn--primary"
        data-slot="markall"
        data-url="mark-notifications-read.php"
        data-csrf="<?= htmlspecialchars($notificationCsrfToken, ENT_QUOTES, 'UTF-8') ?>"
        <?= !$notifications ? 'disabled' : '' ?>
      >
        <i class="fa-solid fa-check-double"></i>
        Mark all as read
      </button>

      <button
        type="button"
        class="adm-btn adm-btn--danger"
        data-slot="deleteall"
        data-url="delete-all-notifications.php"
        data-csrf="<?= htmlspecialchars($notificationCsrfToken, ENT_QUOTES, 'UTF-8') ?>"
        <?= !$notifications ? 'disabled' : '' ?>
      >
        <i class="fa-solid fa-trash"></i>
        Delete all notifications
      </button>
    </div>
  </div>
</div>