<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

$allowedGroups = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
$allowedAreas = [
    'Morbi City',
    'Shanala Road',
    'Wankaner',
    'Maliya',
    'Tankara',
    'Halvad',
    'Ravapar',
    'Other',
];
$allowedStatuses = ['Active', 'Inactive'];

function donorEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function setDonorToast(string $message, string $type = 'success'): void
{
    $_SESSION['donor_toast_message'] = $message;
    $_SESSION['donor_toast_type'] = $type;
}

function validDonorDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

$values = [
    'name' => '',
    'group' => '',
    'phone' => '',
    'age' => '',
    'area' => '',
    'lastDonation' => '',
    'status' => '',
];

$errors = [];

if (empty($_SESSION['donor_manage_csrf_token'])) {
    $_SESSION['donor_manage_csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * Process update and delete requests.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['donor_manage_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        setDonorToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        setDonorToast('Invalid donor record.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('DELETE FROM donors WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                setDonorToast('Donor deleted successfully.');
            } else {
                setDonorToast('That donor record was not found.', 'error');
            }
        } catch (PDOException $e) {
            error_log('Donor delete failed: ' . $e->getMessage());
            setDonorToast('Could not delete the donor. Please try again.', 'error');
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action !== 'update') {
        setDonorToast('Unknown action.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    foreach ($values as $key => $unused) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (strlen($values['name']) < 2 || strlen($values['name']) > 120) {
        $errors[] = 'Name must be between 2 and 120 characters.';
    }

    if (!in_array($values['group'], $allowedGroups, true)) {
        $errors[] = 'Select a valid blood group.';
    }

    $phoneDigits = preg_replace('/\D/', '', $values['phone']);
    if (
        !preg_match('/^\+?[0-9][0-9\s().-]{5,18}[0-9]$/', $values['phone'])
        || strlen($phoneDigits) < 7
        || strlen($phoneDigits) > 15
    ) {
        $errors[] = 'Enter a valid phone number.';
    }

    $age = filter_var($values['age'], FILTER_VALIDATE_INT);
    if ($age === false || $age < 18 || $age > 60) {
        $errors[] = 'Age must be a whole number from 18 to 60.';
    }

    if (!in_array($values['area'], $allowedAreas, true)) {
        $errors[] = 'Select a valid area / taluka.';
    }

    $lastDonation = null;
    if ($values['lastDonation'] !== '') {
        if (!validDonorDate($values['lastDonation'])) {
            $errors[] = 'Enter a valid last donation date.';
        } elseif ($values['lastDonation'] > date('Y-m-d')) {
            $errors[] = 'Last donation date cannot be in the future.';
        } else {
            $lastDonation = $values['lastDonation'];
        }
    }

    if (!in_array($values['status'], $allowedStatuses, true)) {
        $errors[] = 'Select a valid donor status.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE donors
                 SET name = :name,
                     blood_group = :blood_group,
                     phone = :phone,
                     age = :age,
                     area = :area,
                     last_donation = :last_donation,
                     status = :status
                 WHERE id = :id'
            );

            $stmt->execute([
                ':name' => $values['name'],
                ':blood_group' => $values['group'],
                ':phone' => $values['phone'],
                ':age' => $age,
                ':area' => $values['area'],
                ':last_donation' => $lastDonation,
                ':status' => $values['status'],
                ':id' => $id,
            ]);

            setDonorToast('Donor updated successfully.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            error_log('Donor update failed: ' . $e->getMessage());
            $errors[] = 'Could not update the donor. Please try again.';
        }
    }

    if ($errors) {
        setDonorToast(implode(' ', $errors), 'error');
    }
}

$toastMessage = $_SESSION['donor_toast_message'] ?? '';
$toastType = $_SESSION['donor_toast_type'] ?? 'success';
unset($_SESSION['donor_toast_message'], $_SESSION['donor_toast_type']);

$donors = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT id, name, blood_group, phone, age, area, last_donation, status
         FROM donors
         ORDER BY created_at DESC, id DESC'
    );

    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load donors: ' . $e->getMessage());
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
        <section class="adm-page" id="page-donors-manage">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-user-group"></i>
              <span>Manage Donors</span>
              <span class="adm-chip">Donor Registry</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="donorSearch"
                  placeholder="Search donors..."
                  aria-label="Search donors"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-donors.php">
                  <i class="fa-solid fa-plus"></i> Add Donor
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load donors. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($donors) ?> donor(s) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Full Name</th>
                      <th>Blood Group</th>
                      <th>Phone / WhatsApp</th>
                      <th>Age</th>
                      <th>Area / Taluka</th>
                      <th>Last Donation</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$donors): ?>
                      <tr class="empty-row">
                        <td colspan="9">No donors have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($donors as $index => $donor): ?>
                        <tr class="donor-row">
                          <td class="donor-number"><?= $index + 1 ?></td>
                          <td><?= donorEscape($donor['name']) ?></td>
                          <td><?= donorEscape($donor['blood_group']) ?></td>
                          <td><?= donorEscape($donor['phone']) ?></td>
                          <td><?= donorEscape($donor['age']) ?></td>
                          <td><?= donorEscape($donor['area']) ?></td>
                          <td>
                            <?= !empty($donor['last_donation'])
                              ? donorEscape($donor['last_donation'])
                              : '—' ?>
                          </td>
                          <td><?= donorEscape($donor['status']) ?></td>
                          <td class="is-actions">
                            <button
                              type="button"
                              class="adm-btn adm-btn--sm edit-donor"
                              data-id="<?= donorEscape($donor['id']) ?>"
                              data-name="<?= donorEscape($donor['name']) ?>"
                              data-group="<?= donorEscape($donor['blood_group']) ?>"
                              data-phone="<?= donorEscape($donor['phone']) ?>"
                              data-age="<?= donorEscape($donor['age']) ?>"
                              data-area="<?= donorEscape($donor['area']) ?>"
                              data-last-donation="<?= donorEscape($donor['last_donation']) ?>"
                              data-status="<?= donorEscape($donor['status']) ?>"
                            >
                              <i class="fa-solid fa-pen"></i> Update
                            </button>

                            <form method="post" class="delete-donor-form">
                              <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= donorEscape($_SESSION['donor_manage_csrf_token']) ?>"
                              >
                              <input type="hidden" name="action" value="delete">
                              <input
                                type="hidden"
                                name="id"
                                value="<?= donorEscape($donor['id']) ?>"
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
                      <td colspan="9">No matching donors found.</td>
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

  <!-- Update donor modal -->
  <div class="adm-modal" id="editDonorModal" hidden>
    <div
      class="adm-modal__box is-wide"
      role="dialog"
      aria-modal="true"
      aria-labelledby="editDonorTitle"
    >
      <div class="adm-modal__head">
        <h3 id="editDonorTitle">
          <i class="fa-solid fa-pen-to-square"></i> Update Donor
        </h3>
        <button
          type="button"
          class="adm-x"
          data-close-donor-modal
          aria-label="Close"
        >&times;</button>
      </div>

      <div class="adm-modal__body">
        <form class="adm-form" id="editDonorForm" method="post">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= donorEscape($_SESSION['donor_manage_csrf_token']) ?>"
          >
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-donor-id">

          <div class="adm-field">
            <label for="edit-donor-name">Full Name *</label>
            <input
              id="edit-donor-name"
              name="name"
              type="text"
              minlength="2"
              maxlength="120"
              required
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-donor-group">Blood Group *</label>
            <select id="edit-donor-group" name="group" required>
              <option value="">Select...</option>
              <?php foreach ($allowedGroups as $group): ?>
                <option value="<?= donorEscape($group) ?>"><?= donorEscape($group) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-donor-phone">Phone / WhatsApp *</label>
            <input
              id="edit-donor-phone"
              name="phone"
              type="tel"
              minlength="7"
              maxlength="20"
              pattern="[0-9+(). -]{7,20}"
              title="Enter a phone number using digits and common phone characters."
              required
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-donor-age">Age (18-60) *</label>
            <input
              id="edit-donor-age"
              name="age"
              type="number"
              min="18"
              max="60"
              step="1"
              required
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-donor-area">Area / Taluka *</label>
            <select id="edit-donor-area" name="area" required>
              <option value="">Select...</option>
              <?php foreach ($allowedAreas as $area): ?>
                <option value="<?= donorEscape($area) ?>"><?= donorEscape($area) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-donor-lastDonation">Last Donation</label>
            <input
              id="edit-donor-lastDonation"
              name="lastDonation"
              type="date"
              max="<?= date('Y-m-d') ?>"
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-donor-status">Status *</label>
            <select id="edit-donor-status" name="status" required>
              <option value="">Select...</option>
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= donorEscape($status) ?>"><?= donorEscape($status) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>
        </form>
      </div>

      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-donor-modal>Cancel</button>
        <button type="submit" form="editDonorForm" class="adm-btn adm-btn--primary">
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
    data-message="<?= donorEscape($toastMessage) ?>"
    data-type="<?= donorEscape($toastType) ?>"
  ></div>

  <script>
    (() => {
      const searchInput = document.getElementById('donorSearch');
      const allRows = Array.from(document.querySelectorAll('.donor-row'));
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
          row.querySelector('.donor-number').textContent = start + index + 1;
        });

        if (noSearchResults) {
          noSearchResults.hidden = filteredRows.length !== 0 || allRows.length === 0;
        }

        const pageCountLabel = document.getElementById('pageNumber');
        const pageInfo = document.getElementById('pageInfo');
        const resultInfo = document.getElementById('resultInfo');
        const previousButton = document.getElementById('previousPage');
        const nextButton = document.getElementById('nextPage');

        if (pageCountLabel) {
          pageCountLabel.textContent = `Page ${currentPage} of ${pageCount}`;
        }

        if (pageInfo) {
          const first = filteredRows.length === 0 ? 0 : start + 1;
          const last = start + pageRows.length;
          pageInfo.textContent =
            `Showing ${first}–${last} of ${filteredRows.length} donor(s)`;
        }

        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} donor(s) found.`;
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

      const modal = document.getElementById('editDonorModal');
      const editForm = document.getElementById('editDonorForm');

      document.querySelectorAll('.edit-donor').forEach(button => {
        button.addEventListener('click', () => {
          document.getElementById('edit-donor-id').value = button.dataset.id;
          document.getElementById('edit-donor-name').value = button.dataset.name;
          document.getElementById('edit-donor-group').value = button.dataset.group;
          document.getElementById('edit-donor-phone').value = button.dataset.phone;
          document.getElementById('edit-donor-age').value = button.dataset.age;
          document.getElementById('edit-donor-area').value = button.dataset.area;
          document.getElementById('edit-donor-lastDonation').value =
            button.dataset.lastDonation || '';
          document.getElementById('edit-donor-status').value = button.dataset.status;
          modal.hidden = false;
        });
      });

      document.querySelectorAll('[data-close-donor-modal]').forEach(button => {
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

      document.querySelectorAll('.delete-donor-form').forEach(form => {
        form.addEventListener('submit', event => {
          if (!window.confirm('Are you sure you want to delete this donor?')) {
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

      renderRows();
    })();
  </script>

  <script src="admin.js"></script>
</body>
</html>