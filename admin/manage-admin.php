<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';

function manageAdminEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function manageAdminDeny(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>';
    echo manageAdminEscape($message);
    echo '</title><body><h1>' . manageAdminEscape($message) . '</h1>';
    echo '<p>You do not have permission to access this page.</p></body></html>';
    exit;
}

/*
 * Verify the logged-in admin against the database before rendering anything.
 * Your login code stores the ID at $_SESSION['admin']['id'].
 */
$currentSessionAdmin = current_admin();
$currentAdminId = filter_var(
    $currentSessionAdmin['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($currentAdminId === false || $currentAdminId === null) {
    manageAdminDeny(403, 'Access denied');
}

$pdo = db();

try {
    $authStmt = $pdo->prepare(
        'SELECT id, name, role, status
         FROM admins
         WHERE id = :id
         LIMIT 1'
    );
    $authStmt->execute([':id' => $currentAdminId]);
    $loggedInAdmin = $authStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin authorization check failed: ' . $e->getMessage());
    manageAdminDeny(500, 'Unable to verify access');
}

if (
    !$loggedInAdmin
    || $loggedInAdmin['status'] !== 'Active'
    || $loggedInAdmin['role'] !== 'Super Admin'
) {
    manageAdminDeny(403, 'Access denied');
}

$allowedRoles = ['Super Admin', 'Content Editor'];
$allowedStatuses = ['Active', 'Suspended'];

if (empty($_SESSION['manage_admin_csrf_token'])) {
    $_SESSION['manage_admin_csrf_token'] = bin2hex(random_bytes(32));
}

function setManageAdminToast(string $message, string $type = 'success'): void
{
    $_SESSION['manage_admin_toast_message'] = $message;
    $_SESSION['manage_admin_toast_type'] = $type;
}

function validateAdminFields(
    string $name,
    string $email,
    string $phone,
    string $role,
    string $status,
    array $allowedRoles,
    array $allowedStatuses
): array {
    $errors = [];
    $nameLength = function_exists('mb_strlen')
        ? mb_strlen($name, 'UTF-8')
        : strlen($name);

    if ($nameLength < 2 || $nameLength > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }

    if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address (maximum 150 characters).';
    }

    if (
        strlen($phone) < 10
        || strlen($phone) > 10
        || !preg_match('/^[0-9+(). -]+$/', $phone)
    ) {
        $errors[] = 'Enter a valid phone number (10 characters).';
    }

    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Select a valid admin role.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Select a valid admin status.';
    }

    return $errors;
}

/*
 * Password policy: 8–72 bytes. The byte limit is compatible with bcrypt,
 * which may be used by PASSWORD_DEFAULT on some PHP versions.
 */
function validateAdminPassword(string $password): bool
{
    return strlen($password) >= 8 && strlen($password) <= 72;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['manage_admin_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        setManageAdminToast('Your form expired. Reload the page and try again.', 'error');
        header('Location: manage-admin.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = validateAdminFields(
            $name,
            $email,
            $phone,
            $role,
            $status,
            $allowedRoles,
            $allowedStatuses
        );

        $targetId = null;

        if ($action === 'create') {
            if (!validateAdminPassword($password)) {
                $errors[] = 'Password must be 8–72 characters long.';
            }
            if (!hash_equals($password, $passwordConfirm)) {
                $errors[] = 'Passwords do not match.';
            }
        } else {
            $targetId = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($targetId === false || $targetId === null) {
                $errors[] = 'Invalid admin account.';
            }

            // Password is optional when updating; if provided, validate it.
            if ($password !== '' || $passwordConfirm !== '') {
                if (!validateAdminPassword($password)) {
                    $errors[] = 'New password must be 8–72 characters long.';
                }
                if (!hash_equals($password, $passwordConfirm)) {
                    $errors[] = 'New passwords do not match.';
                }
            }
        }

        if ($errors) {
            setManageAdminToast(implode(' ', $errors), 'error');
            header('Location: manage-admin.php');
            exit;
        }

        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO admins
                        (name, email, phone, role, status, password_hash)
                     VALUES
                        (:name, :email, :phone, :role, :status, :password_hash)'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':status' => $status,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                setManageAdminToast('Admin account created successfully.');
                header('Location: manage-admin.php');
                exit;
            }

            $pdo->beginTransaction();

            $targetStmt = $pdo->prepare(
                'SELECT id, role, status
                 FROM admins
                 WHERE id = :id
                 FOR UPDATE'
            );
            $targetStmt->execute([':id' => $targetId]);
            $targetAdmin = $targetStmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetAdmin) {
                $pdo->rollBack();
                setManageAdminToast('That admin account was not found.', 'error');
                header('Location: manage-admin.php');
                exit;
            }

            $willRemainActiveSuperAdmin =
                $role === 'Super Admin' && $status === 'Active';

            if (
                (int) $targetId === (int) $loggedInAdmin['id']
                && !$willRemainActiveSuperAdmin
            ) {
                $pdo->rollBack();
                setManageAdminToast(
                    'You cannot change your own account so it loses Super Admin access.',
                    'error'
                );
                header('Location: manage-admin.php');
                exit;
            }

            $currentlyActiveSuperAdmin =
                $targetAdmin['role'] === 'Super Admin'
                && $targetAdmin['status'] === 'Active';

            if ($currentlyActiveSuperAdmin && !$willRemainActiveSuperAdmin) {
                $countStmt = $pdo->query(
                    "SELECT id
                     FROM admins
                     WHERE role = 'Super Admin' AND status = 'Active'
                     FOR UPDATE"
                );

                if (count($countStmt->fetchAll(PDO::FETCH_COLUMN)) <= 1) {
                    $pdo->rollBack();
                    setManageAdminToast(
                        'The last active Super Admin cannot be demoted or suspended.',
                        'error'
                    );
                    header('Location: manage-admin.php');
                    exit;
                }
            }

            if ($password !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE admins
                     SET name = :name,
                         email = :email,
                         phone = :phone,
                         role = :role,
                         status = :status,
                         password_hash = :password_hash
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':status' => $status,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $targetId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE admins
                     SET name = :name,
                         email = :email,
                         phone = :phone,
                         role = :role,
                         status = :status
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':status' => $status,
                    ':id' => $targetId,
                ]);
            }

            $pdo->commit();
            setManageAdminToast(
                $password !== ''
                    ? 'Admin account and password updated successfully.'
                    : 'Admin account updated successfully.'
            );
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Admin account save failed: ' . $e->getMessage());
            setManageAdminToast(
                $e->getCode() === '23000'
                    ? 'That email address is already in use.'
                    : 'Could not save the admin account. Please try again.',
                'error'
            );
        }

        header('Location: manage-admin.php');
        exit;
    }

    if ($action === 'delete') {
        $targetId = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($targetId === false || $targetId === null) {
            setManageAdminToast('Invalid admin account.', 'error');
            header('Location: manage-admin.php');
            exit;
        }

        if ((int) $targetId === (int) $loggedInAdmin['id']) {
            setManageAdminToast('You cannot delete your own account.', 'error');
            header('Location: manage-admin.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $targetStmt = $pdo->prepare(
                'SELECT id, role, status
                 FROM admins
                 WHERE id = :id
                 FOR UPDATE'
            );
            $targetStmt->execute([':id' => $targetId]);
            $targetAdmin = $targetStmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetAdmin) {
                $pdo->rollBack();
                setManageAdminToast('That admin account was not found.', 'error');
                header('Location: manage-admin.php');
                exit;
            }

            if (
                $targetAdmin['role'] === 'Super Admin'
                && $targetAdmin['status'] === 'Active'
            ) {
                $activeStmt = $pdo->query(
                    "SELECT id
                     FROM admins
                     WHERE role = 'Super Admin' AND status = 'Active'
                     FOR UPDATE"
                );

                if (count($activeStmt->fetchAll(PDO::FETCH_COLUMN)) <= 1) {
                    $pdo->rollBack();
                    setManageAdminToast(
                        'The last active Super Admin cannot be deleted.',
                        'error'
                    );
                    header('Location: manage-admin.php');
                    exit;
                }
            }

            $deleteStmt = $pdo->prepare('DELETE FROM admins WHERE id = :id');
            $deleteStmt->execute([':id' => $targetId]);
            $pdo->commit();

            setManageAdminToast('Admin account deleted successfully.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Admin account deletion failed: ' . $e->getMessage());
            setManageAdminToast('Could not delete the admin account. Please try again.', 'error');
        }

        header('Location: manage-admin.php');
        exit;
    }

    setManageAdminToast('Unknown action.', 'error');
    header('Location: manage-admin.php');
    exit;
}

$toastMessage = $_SESSION['manage_admin_toast_message'] ?? '';
$toastType = $_SESSION['manage_admin_toast_type'] ?? 'success';
unset($_SESSION['manage_admin_toast_message'], $_SESSION['manage_admin_toast_type']);

try {
    $stmt = $pdo->query(
        'SELECT id, name, email, phone, role, status, last_login_at, created_at
         FROM admins
         ORDER BY created_at DESC, id DESC'
    );
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $loadError = false;
} catch (PDOException $e) {
    error_log('Could not load admin accounts: ' . $e->getMessage());
    $admins = [];
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/inc/head.php'; ?>

<body data-app-mode="php">
  <?php include __DIR__ . '/inc/header.php'; ?>

  <div class="adm-shell">
    <?php include __DIR__ . '/inc/sidebar.php'; ?>

    <main class="adm-main">
      <div class="adm-view" id="view">
        <section class="adm-page">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-user-shield"></i>
              Manage Admins
              <span class="adm-chip">Super Admin Only</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="adminSearch" type="search" placeholder="Search admins..." aria-label="Search admins">
              </label>
              <div class="adm-actions">
                <button type="button" class="adm-btn adm-btn--primary" id="openAddAdmin">
                  <i class="fa-solid fa-plus"></i> Add Admin
                </button>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">Could not load admin accounts.</div>
            <?php else: ?>
              <div class="adm-info"><span id="resultInfo"><?= count($admins) ?> admin(s) found.</span></div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th><th>Name</th><th>Email</th><th>Phone</th>
                      <th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$admins): ?>
                      <tr><td colspan="9">No admin accounts found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($admins as $i => $admin): ?>
                        <tr class="admin-row">
                          <td class="admin-number"><?= $i + 1 ?></td>
                          <td><?= manageAdminEscape($admin['name']) ?></td>
                          <td><?= manageAdminEscape($admin['email']) ?></td>
                          <td><?= manageAdminEscape($admin['phone']) ?></td>
                          <td><?= manageAdminEscape($admin['role']) ?></td>
                          <td><?= manageAdminEscape($admin['status']) ?></td>
                          <td><?= manageAdminEscape($admin['last_login_at'] ?: 'Never') ?></td>
                          <td><?= manageAdminEscape($admin['created_at']) ?></td>
                          <td class="is-actions">
                            <button
                              type="button"
                              class="adm-btn adm-btn--sm edit-admin"
                              data-id="<?= (int) $admin['id'] ?>"
                              data-name="<?= manageAdminEscape($admin['name']) ?>"
                              data-email="<?= manageAdminEscape($admin['email']) ?>"
                              data-phone="<?= manageAdminEscape($admin['phone']) ?>"
                              data-role="<?= manageAdminEscape($admin['role']) ?>"
                              data-status="<?= manageAdminEscape($admin['status']) ?>"
                            ><i class="fa-solid fa-pen"></i> Update</button>

                            <form method="post" class="delete-admin-form" data-confirm="Are you sure you want to delete this admin?">
                              <input type="hidden" name="csrf_token" value="<?= manageAdminEscape($_SESSION['manage_admin_csrf_token']) ?>">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                              <button type="submit" class="adm-btn adm-btn--danger adm-btn--sm">
                                <i class="fa-solid fa-trash"></i> Delete
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="noSearchResults" hidden><td colspan="9">No matching admins found.</td></tr>
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span id="pageInfo"></span>
                <div class="adm-pager">
                  <button type="button" class="adm-btn adm-btn--sm" id="previousPage">Previous</button>
                  <span id="pageNumber"></span>
                  <button type="button" class="adm-btn adm-btn--sm" id="nextPage">Next</button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- Add Admin modal -->
  <div class="adm-modal" id="addAdminModal" hidden>
    <div class="adm-modal__box" role="dialog" aria-modal="true" aria-labelledby="addAdminTitle">
      <div class="adm-modal__head">
        <h3 id="addAdminTitle">Add Admin</h3>
        <button type="button" class="adm-x" data-close-modal aria-label="Close">&times;</button>
      </div>
      <div class="adm-modal__body">
        <form class="adm-form" id="addAdminForm" method="post">
          <input type="hidden" name="csrf_token" value="<?= manageAdminEscape($_SESSION['manage_admin_csrf_token']) ?>">
          <input type="hidden" name="action" value="create">

          <div class="adm-field"><label>Name *</label><input name="name" type="text" minlength="2" maxlength="100" required></div>
          <div class="adm-field"><label>Email *</label><input name="email" type="email" maxlength="150" required></div>
          <div class="adm-field"><label>Phone *</label><input name="phone" type="tel" minlength="10" maxlength="10" required></div>

          <div class="adm-field">
            <label>Role *</label>
            <select name="role" required>
              <option value="Content Editor">Content Editor</option>
              <option value="Super Admin">Super Admin</option>
            </select>
          </div>

          <div class="adm-field">
            <label>Status *</label>
            <select name="status" required>
              <option value="Active">Active</option>
              <option value="Suspended">Suspended</option>
            </select>
          </div>

          <div class="adm-field"><label>Password * (8–72 characters)</label><input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
          <div class="adm-field"><label>Confirm Password *</label><input name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
        </form>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-modal>Cancel</button>
        <button type="submit" form="addAdminForm" class="adm-btn adm-btn--primary">Create Admin</button>
      </div>
    </div>
  </div>

  <!-- Update Admin modal -->
  <div class="adm-modal" id="editAdminModal" hidden>
    <div class="adm-modal__box" role="dialog" aria-modal="true" aria-labelledby="editAdminTitle">
      <div class="adm-modal__head">
        <h3 id="editAdminTitle">Update Admin</h3>
        <button type="button" class="adm-x" data-close-modal aria-label="Close">&times;</button>
      </div>
      <div class="adm-modal__body">
        <form class="adm-form" id="editAdminForm" method="post">
          <input type="hidden" name="csrf_token" value="<?= manageAdminEscape($_SESSION['manage_admin_csrf_token']) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-admin-id">

          <div class="adm-field"><label>Name *</label><input id="edit-admin-name" name="name" type="text" minlength="2" maxlength="100" required></div>
          <div class="adm-field"><label>Email *</label><input id="edit-admin-email" name="email" type="email" maxlength="150" required></div>
          <div class="adm-field"><label>Phone *</label><input id="edit-admin-phone" name="phone" type="tel" minlength="7" maxlength="20" required></div>

          <div class="adm-field">
            <label>Role *</label>
            <select id="edit-admin-role" name="role" required>
              <option value="Content Editor">Content Editor</option>
              <option value="Super Admin">Super Admin</option>
            </select>
          </div>

          <div class="adm-field">
            <label>Status *</label>
            <select id="edit-admin-status" name="status" required>
              <option value="Active">Active</option>
              <option value="Suspended">Suspended</option>
            </select>
          </div>

          <div class="adm-field">
            <label>New Password (leave blank to keep current)</label>
            <input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password">
          </div>
          <div class="adm-field">
            <label>Confirm New Password</label>
            <input name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password">
          </div>
        </form>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-modal>Cancel</button>
        <button type="submit" form="editAdminForm" class="adm-btn adm-btn--primary">Save Changes</button>
      </div>
    </div>
  </div>

  <div class="adm-toast" id="toast" role="status" aria-live="polite"
       data-message="<?= manageAdminEscape($toastMessage) ?>"
       data-type="<?= manageAdminEscape($toastType) ?>"></div>

  <style>
    [hidden] { display: none !important; }
    .adm-modal {
      position: fixed; inset: 0; z-index: 9999; display: grid;
      place-items: center; padding: 20px; background: rgb(0 0 0 / 45%);
    }
    .adm-modal__box {
      width: min(760px, 100%); max-height: 90vh; overflow: auto;
      background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgb(0 0 0 / 25%);
    }
    .delete-admin-form { display: inline; }
  </style>

  <script>
    (() => {
      const rows = [...document.querySelectorAll('.admin-row')];
      const search = document.getElementById('adminSearch');
      const noResults = document.getElementById('noSearchResults');
      const pageSize = 10;
      let page = 1;
      let filtered = rows;

      function render() {
        const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
        page = Math.min(page, pages);
        rows.forEach(row => row.hidden = true);

        const start = (page - 1) * pageSize;
        const shown = filtered.slice(start, start + pageSize);
        shown.forEach((row, i) => {
          row.hidden = false;
          row.querySelector('.admin-number').textContent = start + i + 1;
        });

        if (noResults) noResults.hidden = filtered.length !== 0 || rows.length === 0;
        document.getElementById('pageNumber').textContent = `Page ${page} of ${pages}`;
        document.getElementById('pageInfo').textContent =
          `Showing ${filtered.length ? start + 1 : 0}–${start + shown.length} of ${filtered.length} admin(s)`;
        document.getElementById('resultInfo').textContent = `${filtered.length} admin(s) found.`;
        document.getElementById('previousPage').disabled = page <= 1;
        document.getElementById('nextPage').disabled = page >= pages;
      }

      search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        filtered = rows.filter(row => row.textContent.toLowerCase().includes(q));
        page = 1;
        render();
      });

      document.getElementById('previousPage')?.addEventListener('click', () => {
        if (page > 1) { page--; render(); }
      });
      document.getElementById('nextPage')?.addEventListener('click', () => {
        if (page < Math.ceil(filtered.length / pageSize)) { page++; render(); }
      });

      const addModal = document.getElementById('addAdminModal');
      const editModal = document.getElementById('editAdminModal');

      document.getElementById('openAddAdmin')?.addEventListener('click', () => {
        addModal.hidden = false;
      });

      document.querySelectorAll('.edit-admin').forEach(button => {
        button.addEventListener('click', () => {
          document.getElementById('edit-admin-id').value = button.dataset.id;
          document.getElementById('edit-admin-name').value = button.dataset.name;
          document.getElementById('edit-admin-email').value = button.dataset.email;
          document.getElementById('edit-admin-phone').value = button.dataset.phone;
          document.getElementById('edit-admin-role').value = button.dataset.role;
          document.getElementById('edit-admin-status').value = button.dataset.status;
          editModal.hidden = false;
        });
      });

      document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => {
          addModal.hidden = true;
          editModal.hidden = true;
        });
      });

      [addModal, editModal].forEach(modal => {
        modal?.addEventListener('click', event => {
          if (event.target === modal) modal.hidden = true;
        });
      });

      document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
          addModal.hidden = true;
          editModal.hidden = true;
        }
      });

      const toast = document.getElementById('toast');
      if (toast?.dataset.message) {
        toast.textContent = toast.dataset.message;
        toast.classList.add('is-show');
        if (toast.dataset.type === 'error') toast.classList.add('is-error');
        setTimeout(() => toast.classList.remove('is-show', 'is-error'), 5000);
      }


      render();
    })();
  </script>

    <script src="admin.js"></script>
</body>
</html>