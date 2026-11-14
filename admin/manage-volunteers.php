<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

$allowedAreas = [
    'Morbi City',
    'Morbi-2',
    'Shanala Road',
    'Wankaner',
    'Maliya',
    'Tankara',
    'Halvad',
    'Ravapar',
    'Other',
];

$allowedRoles = [
    'Activity & Event Organizer',
    'Blood Donor & Mobilizer',
    'Emergency Responder',
    'Education & Mentorship',
    'Medical Camp Support',
    'Tree Plantation',
    'Disaster Relief Team',
    'Digital & Media',
];

$allowedAvailabilities = [
    'Anytime',
    'Weekend',
    'Emergency SOS',
    '4-8 hrs / week',
];

$allowedStatuses = ['Active', 'Inactive'];

function volunteerManageEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function setVolunteerManageToast(string $message, string $type = 'success'): void
{
    $_SESSION['volunteer_manage_toast_message'] = $message;
    $_SESSION['volunteer_manage_toast_type'] = $type;
}

if (empty($_SESSION['volunteer_manage_csrf_token'])) {
    $_SESSION['volunteer_manage_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['volunteer_manage_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        setVolunteerManageToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        setVolunteerManageToast('Invalid volunteer record.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM volunteers WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                setVolunteerManageToast('Volunteer deleted successfully.');
            } else {
                setVolunteerManageToast('That volunteer was not found.', 'error');
            }

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action !== 'update') {
            setVolunteerManageToast('Unknown action.', 'error');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $area = trim((string) ($_POST['area'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $availability = trim((string) ($_POST['availability'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));

        $errors = [];

        if (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }

        if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
            $errors[] = 'Enter a valid 10-digit mobile number.';
        }

        if (!in_array($area, $allowedAreas, true)) {
            $errors[] = 'Select a valid area / ward.';
        }

        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Select a valid volunteer role.';
        }

        if (!in_array($availability, $allowedAvailabilities, true)) {
            $errors[] = 'Select a valid availability.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = 'Select a valid status.';
        }

        if ($errors) {
            setVolunteerManageToast(implode(' ', $errors), 'error');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE volunteers
                 SET name = :name,
                     phone = :phone,
                     area = :area,
                     role = :role,
                     availability = :availability,
                     status = :status
                 WHERE id = :id'
            );

            $stmt->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':area' => $area,
                ':role' => $role,
                ':availability' => $availability,
                ':status' => $status,
                ':id' => $id,
            ]);

            setVolunteerManageToast('Volunteer updated successfully.');
        }
    } catch (PDOException $e) {
        error_log('Volunteer operation failed: ' . $e->getMessage());
        setVolunteerManageToast('The operation failed. Please try again.', 'error');
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$toastMessage = $_SESSION['volunteer_manage_toast_message'] ?? '';
$toastType = $_SESSION['volunteer_manage_toast_type'] ?? 'success';
unset($_SESSION['volunteer_manage_toast_message'], $_SESSION['volunteer_manage_toast_type']);

$volunteers = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT id, name, phone, area, role, availability, status
         FROM volunteers
         ORDER BY created_at DESC, id DESC'
    );

    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load volunteers: ' . $e->getMessage());
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
        <section class="adm-page" id="page-volunteers-manage">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-hand-holding-heart"></i>
              <span>Manage Volunteers</span>
              <span class="adm-chip">Volunteer Network</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="volunteerSearch"
                  placeholder="Search volunteers..."
                  aria-label="Search volunteers"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-volunteers.php">
                  <i class="fa-solid fa-plus"></i> Add Volunteer
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load volunteers. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($volunteers) ?> volunteer(s) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Full Name</th>
                      <th>Phone / WhatsApp</th>
                      <th>Area / Ward</th>
                      <th>Primary Role</th>
                      <th>Availability</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$volunteers): ?>
                      <tr class="empty-row">
                        <td colspan="8">No volunteers have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($volunteers as $index => $volunteer): ?>
                        <tr class="volunteer-row">
                          <td class="volunteer-number"><?= $index + 1 ?></td>
                          <td><?= volunteerManageEscape($volunteer['name']) ?></td>
                          <td><?= volunteerManageEscape($volunteer['phone']) ?></td>
                          <td><?= volunteerManageEscape($volunteer['area']) ?></td>
                          <td><?= volunteerManageEscape($volunteer['role']) ?></td>
                          <td><?= volunteerManageEscape($volunteer['availability']) ?></td>
                          <td><?= volunteerManageEscape($volunteer['status']) ?></td>
                          <td class="is-actions">
                            <button
                              type="button"
                              class="adm-btn adm-btn--sm edit-volunteer"
                              data-id="<?= volunteerManageEscape($volunteer['id']) ?>"
                              data-name="<?= volunteerManageEscape($volunteer['name']) ?>"
                              data-phone="<?= volunteerManageEscape($volunteer['phone']) ?>"
                              data-area="<?= volunteerManageEscape($volunteer['area']) ?>"
                              data-role="<?= volunteerManageEscape($volunteer['role']) ?>"
                              data-availability="<?= volunteerManageEscape($volunteer['availability']) ?>"
                              data-status="<?= volunteerManageEscape($volunteer['status']) ?>"
                            >
                              <i class="fa-solid fa-pen"></i> Update
                            </button>

                            <form method="post" class="delete-volunteer-form">
                              <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= volunteerManageEscape($_SESSION['volunteer_manage_csrf_token']) ?>"
                              >
                              <input type="hidden" name="action" value="delete">
                              <input
                                type="hidden"
                                name="id"
                                value="<?= volunteerManageEscape($volunteer['id']) ?>"
                              >
                              <button type="submit" class="adm-btn adm-btn--danger adm-btn--sm">
                                <i class="fa-solid fa-trash"></i> Delete
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="noSearchResults" hidden>
                      <td colspan="8">No matching volunteers found.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span id="pageInfo"></span>

                <div class="adm-pager">
                  <button type="button" class="adm-btn adm-btn--sm" id="previousPage">
                    <i class="fa-solid fa-chevron-left"></i> Previous
                  </button>
                  <span id="pageNumber"></span>
                  <button type="button" class="adm-btn adm-btn--sm" id="nextPage">
                    Next <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </main>
  </div>

  <div class="adm-modal" id="editVolunteerModal" hidden>
    <div
      class="adm-modal__box is-wide"
      role="dialog"
      aria-modal="true"
      aria-labelledby="editVolunteerTitle"
    >
      <div class="adm-modal__head">
        <h3 id="editVolunteerTitle">
          <i class="fa-solid fa-pen-to-square"></i> Update Volunteer
        </h3>
        <button
          type="button"
          class="adm-x"
          data-close-volunteer-modal
          aria-label="Close"
        >&times;</button>
      </div>

      <div class="adm-modal__body">
        <form class="adm-form" id="editVolunteerForm" method="post">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= volunteerManageEscape($_SESSION['volunteer_manage_csrf_token']) ?>"
          >
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-volunteer-id">

          <div class="adm-field">
            <label for="edit-volunteer-name">Full Name *</label>
            <input
              id="edit-volunteer-name"
              name="name"
              type="text"
              minlength="2"
              maxlength="100"
              required
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-volunteer-phone">Phone / WhatsApp *</label>
            <input
              id="edit-volunteer-phone"
              name="phone"
              type="tel"
              inputmode="numeric"
              pattern="[6-9][0-9]{9}"
              minlength="10"
              maxlength="10"
              title="Enter a valid 10-digit mobile number"
              required
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-volunteer-area">Area / Ward *</label>
            <select id="edit-volunteer-area" name="area" required>
              <option value="">Select...</option>
              <?php foreach ($allowedAreas as $area): ?>
                <option value="<?= volunteerManageEscape($area) ?>"><?= volunteerManageEscape($area) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-volunteer-role">Primary Role *</label>
            <select id="edit-volunteer-role" name="role" required>
              <option value="">Select...</option>
              <?php foreach ($allowedRoles as $role): ?>
                <option value="<?= volunteerManageEscape($role) ?>"><?= volunteerManageEscape($role) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-volunteer-availability">Availability *</label>
            <select id="edit-volunteer-availability" name="availability" required>
              <option value="">Select...</option>
              <?php foreach ($allowedAvailabilities as $availability): ?>
                <option value="<?= volunteerManageEscape($availability) ?>"><?= volunteerManageEscape($availability) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-volunteer-status">Status *</label>
            <select id="edit-volunteer-status" name="status" required>
              <option value="">Select...</option>
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= volunteerManageEscape($status) ?>"><?= volunteerManageEscape($status) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>
        </form>
      </div>

      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-volunteer-modal>Cancel</button>
        <button type="submit" form="editVolunteerForm" class="adm-btn adm-btn--primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </div>
  </div>

  <div
    class="adm-toast"
    id="toast"
    role="status"
    aria-live="polite"
    data-message="<?= volunteerManageEscape($toastMessage) ?>"
    data-type="<?= volunteerManageEscape($toastType) ?>"
  ></div>

  <script>
    (() => {
      const searchInput = document.getElementById('volunteerSearch');
      const allRows = Array.from(document.querySelectorAll('.volunteer-row'));
      const noSearchResults = document.getElementById('noSearchResults');
      const pageSize = 10;
      let currentPage = 1;
      let filteredRows = allRows;

      function renderRows() {
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);

        allRows.forEach(row => {
          row.hidden = true;
        });

        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);

        pageRows.forEach((row, index) => {
          row.hidden = false;
          row.querySelector('.volunteer-number').textContent = start + index + 1;
        });

        if (noSearchResults) {
          noSearchResults.hidden = filteredRows.length !== 0 || allRows.length === 0;
        }

        const pageNumber = document.getElementById('pageNumber');
        const pageInfo = document.getElementById('pageInfo');
        const resultInfo = document.getElementById('resultInfo');
        const previousButton = document.getElementById('previousPage');
        const nextButton = document.getElementById('nextPage');

        if (pageNumber) {
          pageNumber.textContent = `Page ${currentPage} of ${pageCount}`;
        }

        if (pageInfo) {
          const first = filteredRows.length === 0 ? 0 : start + 1;
          const last = start + pageRows.length;
          pageInfo.textContent =
            `Showing ${first}–${last} of ${filteredRows.length} volunteer(s)`;
        }

        if (resultInfo) {
          resultInfo.textContent =
            `${filteredRows.length} volunteer(s) found.`;
        }

        if (previousButton) previousButton.disabled = currentPage <= 1;
        if (nextButton) nextButton.disabled = currentPage >= pageCount;
      }

      searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();

        filteredRows = allRows.filter(row =>
          row.textContent.toLowerCase().includes(query)
        );

        currentPage = 1;
        renderRows();
      });

      document.getElementById('previousPage')?.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderRows();
        }
      });

      document.getElementById('nextPage')?.addEventListener('click', () => {
        if (currentPage < Math.ceil(filteredRows.length / pageSize)) {
          currentPage++;
          renderRows();
        }
      });

      const modal = document.getElementById('editVolunteerModal');

      document.querySelectorAll('.edit-volunteer').forEach(button => {
        button.addEventListener('click', () => {
          document.getElementById('edit-volunteer-id').value = button.dataset.id;
          document.getElementById('edit-volunteer-name').value = button.dataset.name;
          document.getElementById('edit-volunteer-phone').value = button.dataset.phone;
          document.getElementById('edit-volunteer-area').value = button.dataset.area;
          document.getElementById('edit-volunteer-role').value = button.dataset.role;
          document.getElementById('edit-volunteer-availability').value =
            button.dataset.availability;
          document.getElementById('edit-volunteer-status').value = button.dataset.status;
          modal.hidden = false;
        });
      });

      document.querySelectorAll('[data-close-volunteer-modal]').forEach(button => {
        button.addEventListener('click', () => {
          modal.hidden = true;
        });
      });

      modal?.addEventListener('click', event => {
        if (event.target === modal) modal.hidden = true;
      });

      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal) modal.hidden = true;
      });

      document.querySelectorAll('.delete-volunteer-form').forEach(form => {
        form.addEventListener('submit', event => {
          if (!window.confirm('Are you sure you want to delete this volunteer?')) {
            event.preventDefault();
          }
        });
      });

      const toast = document.getElementById('toast');
      if (toast?.dataset.message) {
        toast.textContent = toast.dataset.message;
        toast.classList.add('is-show');

        if (toast.dataset.type === 'error') {
          toast.classList.add('is-error');
        }

        window.setTimeout(() => {
          toast.classList.remove('is-show', 'is-error');
        }, 5000);
      }

      // Sidebar behavior for this PHP page.
      const menuButton = document.getElementById('menuBtn');
      const sidebar = document.getElementById('sidebar');

      if (menuButton && sidebar) {
        menuButton.addEventListener('click', () => {
          sidebar.classList.toggle('is-open');
        });
      }

      document.querySelectorAll('.adm-group__btn').forEach(button => {
        button.addEventListener('click', () => {
          const group = button.closest('.adm-group');
          if (group) group.classList.toggle('is-open');
        });
      });

      renderRows();
    })();
  </script>
</body>
</html>