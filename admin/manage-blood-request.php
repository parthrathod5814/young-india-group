
<?php
require_once __DIR__ . '/inc/auth.php';
require_login();


require_once __DIR__ . '/inc/config.php'; // Must provide a PDO connection in $pdo
$pdo = db();

$allowedGroups = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
$allowedConditions = ['Emergency', 'Normal'];
$allowedStatuses = ['Active', 'In-Transit', 'Fulfilled'];

if (empty($_SESSION['blood_csrf_token'])) {
    $_SESSION['blood_csrf_token'] = bin2hex(random_bytes(32));
}

function escapeOutput($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function setBloodToast(string $message, string $type = 'success'): void
{
    $_SESSION['blood_toast_message'] = $message;
    $_SESSION['blood_toast_type'] = $type;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['blood_csrf_token'], $csrfToken)) {
        setBloodToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        setBloodToast('Invalid blood request ID.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM blood_requests WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                setBloodToast('Blood request deleted successfully.');
            } else {
                setBloodToast('That blood request was not found.', 'error');
            }
        } elseif ($action === 'update') {
            $patient = trim((string) ($_POST['patient'] ?? ''));
            $group = trim((string) ($_POST['group'] ?? ''));
            $patientCondition = trim((string) ($_POST['patient_condition'] ?? ''));
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            $hospitalAddress = trim((string) ($_POST['hospital_address'] ?? ''));
            $ward = trim((string) ($_POST['ward'] ?? ''));
            $contact = trim((string) ($_POST['contact'] ?? ''));
            $runner = trim((string) ($_POST['runner'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? ''));

            $errors = [];

            if ($patient === '' || strlen($patient) > 120) {
                $errors[] = 'Patient name is required and must be 120 characters or fewer.';
            }

            if (!in_array($group, $allowedGroups, true)) {
                $errors[] = 'Select a valid blood group.';
            }

            if (!in_array($patientCondition, $allowedConditions, true)) {
                $errors[] = 'Select Emergency or Normal for patient condition.';
            }

            if ($units === false || $units < 1 || $units > 10) {
                $errors[] = 'Units must be a whole number from 1 to 10.';
            }

            if ($hospitalAddress === '' || strlen($hospitalAddress) > 255) {
                $errors[] = 'Hospital name / address is required and must be 255 characters or fewer.';
            }

            if (strlen($ward) > 120) {
                $errors[] = 'Ward / Bed must be 120 characters or fewer.';
            }

            
            if ($contact === '' || strlen($contact) > 20) {
                $errors[] = 'Attendant phone is required and must be 20 characters or fewer.';
            }

            if (strlen($runner) > 100) {
                $errors[] = 'Runner / Coordinator must be 100 characters or fewer.';
            }

            if (!in_array($status, $allowedStatuses, true)) {
                $errors[] = 'Select a valid status.';
            }

            if ($errors) {
                setBloodToast(implode(' ', $errors), 'error');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE blood_requests
                     SET patient = :patient,
                         blood_group = :blood_group,
                         patient_condition = :patient_condition,
                         units = :units,
                         hospital_address = :hospital_address,
                         ward = :ward,
                         contact = :contact,
                         runner = :runner,
                         status = :status,
                         fulfilled_at = CASE
                             WHEN :fulfilled_status = 'Fulfilled'
                                 THEN COALESCE(fulfilled_at, NOW())
                             ELSE NULL
                         END
                     WHERE id = :id"
                );

                $stmt->execute([
                    ':patient' => $patient,
                    ':blood_group' => $group,
                    ':patient_condition' => $patientCondition,
                    ':units' => $units,
                    ':hospital_address' => $hospitalAddress,
                    ':ward' => $ward !== '' ? $ward : null,
                    ':contact' => $contact,
                    ':runner' => $runner !== '' ? $runner : null,
                    ':status' => $status,
                    ':fulfilled_status' => $status,
                    ':id' => $id,
                ]);

                setBloodToast('Blood request updated successfully.');
            }
        } else {
            setBloodToast('Unknown action.', 'error');
        }
    } catch (PDOException $e) {
        error_log('Blood request operation failed: ' . $e->getMessage());
        setBloodToast('The operation failed. Please try again.', 'error');
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$toastMessage = $_SESSION['blood_toast_message'] ?? '';
$toastType = $_SESSION['blood_toast_type'] ?? 'success';
unset($_SESSION['blood_toast_message'], $_SESSION['blood_toast_type']);

$requests = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT id, patient, blood_group, patient_condition, units,
                hospital_address, ward, contact, runner, status
         FROM blood_requests
         ORDER BY created_at DESC'
    );

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load blood requests: ' . $e->getMessage());
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/inc/head.php'; ?>

<body>
  <?php include __DIR__ . '/inc/header.php'; ?>

  <div class="adm-shell">
    <?php include __DIR__ . '/inc/sidebar.php'; ?>

    <main class="adm-main">
      <div class="adm-view" id="view">
        <section class="adm-page" id="page-blood-manage">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-droplet"></i>
              Manage Blood Requests
              <span class="adm-chip">Blood Emergency Hub</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="bloodSearch"
                  placeholder="Search blood requests..."
                  aria-label="Search blood requests"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-blood-request.php">
                  <i class="fa-solid fa-plus"></i> Add Blood Request
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load blood requests. Check the PHP error log for details.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($requests) ?> blood request(s) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Patient Name</th>
                      <th>Blood Group</th>
                      <th>Patient Condition</th>
                      <th>Units Needed</th>
                      <th>Hospital Name / Address</th>
                      <th>Ward / Bed</th>
                      <th>Attendant Phone</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                    <tbody id="bloodRows">
                      <?php if (!$requests): ?>
                        <tr class="empty-row">
                          <td colspan="10">No blood requests have been added yet.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($requests as $index => $request): ?>
                          <tr class="blood-row">
                            <td><?= $index + 1 ?></td>
                            <td><?= escapeOutput($request['patient']) ?></td>
                            <td><?= escapeOutput($request['blood_group']) ?></td>
                            <td><?= escapeOutput($request['patient_condition']) ?></td>
                            <td><?= escapeOutput($request['units']) ?></td>
                            <td><?= escapeOutput($request['hospital_address']) ?></td>
                            <td><?= escapeOutput($request['ward'] ?: '—') ?></td>
                            <td><?= escapeOutput($request['contact']) ?></td>
                            <td><?= escapeOutput($request['status']) ?></td>

                            <td class="is-actions">
                              <button
                                type="button"
                                class="adm-btn adm-btn--sm edit-button"
                                data-id="<?= escapeOutput($request['id']) ?>"
                                data-patient="<?= escapeOutput($request['patient']) ?>"
                                data-group="<?= escapeOutput($request['blood_group']) ?>"
                                data-condition="<?= escapeOutput($request['patient_condition']) ?>"
                                data-units="<?= escapeOutput($request['units']) ?>"
                                data-hospital-address="<?= escapeOutput($request['hospital_address']) ?>"
                                data-ward="<?= escapeOutput($request['ward']) ?>"
                                data-contact="<?= escapeOutput($request['contact']) ?>"
                                data-runner="<?= escapeOutput($request['runner']) ?>"
                                data-status="<?= escapeOutput($request['status']) ?>"
                              >
                                <i class="fa-solid fa-pen"></i> Update
                              </button>

                              <form method="post" class="delete-form"
                              data-confirm="Are you sure you want to delete this blood request?">

                                <input
                                  type="hidden"
                                  name="csrf_token"
                                  value="<?= escapeOutput($_SESSION['blood_csrf_token']) ?>"
                                >
                                <input type="hidden" name="action" value="delete">
                                <input
                                  type="hidden"
                                  name="id"
                                  value="<?= escapeOutput($request['id']) ?>"
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
                        <td colspan="10">No matching blood requests.</td>
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

  <!-- Update request modal -->
  <div class="adm-modal" id="editModal" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true" aria-labelledby="editTitle">
      <div class="adm-modal__head">
        <h3 id="editTitle">
          <i class="fa-solid fa-pen-to-square"></i> Update Blood Request
        </h3>
        <button type="button" class="adm-x" data-close-edit aria-label="Close">&times;</button>
      </div>

      <div class="adm-modal__body">
        <form class="adm-form" id="editForm" method="post">
          <input type="hidden" name="csrf_token" value="<?= escapeOutput($_SESSION['blood_csrf_token']) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-id">

          <div class="adm-field">
            <label for="edit-patient">Patient Name *</label>
            <input
                id="edit-patient"
                name="patient"
                type="text"
                minlength="2"
                maxlength="120"
                required
              >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-group">Blood Group *</label>
            <select id="edit-group" name="group" required>
              <option value="">Select...</option>
              <?php foreach ($allowedGroups as $group): ?>
                <option value="<?= escapeOutput($group) ?>"><?= escapeOutput($group) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-condition">Patient Condition *</label>
            <select id="edit-condition" name="patient_condition" required>
              <option value="">Select...</option>
              <?php foreach ($allowedConditions as $condition): ?>
                <option value="<?= escapeOutput($condition) ?>"><?= escapeOutput($condition) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-units">Units Needed *</label>
            <input
                id="edit-units"
                name="units"
                type="number"
                min="1"
                max="10"
                step="1"
                required
              >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-hospital-address">Hospital Name / Address *</label>
             <input
                  id="edit-hospital-address"
                  name="hospital_address"
                  type="text"
                  minlength="2"
                  maxlength="255"
                  required
                >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-ward">Ward / Bed</label>
            <input id="edit-ward" name="ward" type="text" maxlength="120">
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-contact">Attendant Phone *</label>
            <input
                    id="edit-contact"
                    name="contact"
                    type="tel"
                    minlength="10"
                    maxlength="20"
                    pattern="[0-9+() .-]{10,20}"
                    title="Enter a valid phone number"
                    required
                  >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-runner">Runner / Coordinator</label>
            <input id="edit-runner" name="runner" type="text" maxlength="100">
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-status">Status *</label>
            <select id="edit-status" name="status" required>
              <option value="">Select...</option>
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= escapeOutput($status) ?>"><?= escapeOutput($status) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>
        </form>
      </div>

      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-edit>Cancel</button>
        <button type="submit" form="editForm" class="adm-btn adm-btn--primary">
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
    data-message="<?= escapeOutput($toastMessage) ?>"
    data-type="<?= escapeOutput($toastType) ?>"
  ></div>

  <script>
    (() => {
      const modal = document.getElementById('editModal');
      const searchInput = document.getElementById('bloodSearch');
      const allRows = Array.from(document.querySelectorAll('.blood-row'));
      const noSearchResults = document.getElementById('noSearchResults');
      const pageSize = 10;
      let currentPage = 1;
      let filteredRows = allRows;

      function renderRows() {
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);

        allRows.forEach(row => { row.hidden = true; });

        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);
        pageRows.forEach(row => { row.hidden = false; });

        if (noSearchResults) {
          noSearchResults.hidden = filteredRows.length !== 0 || allRows.length === 0;
        }

        const pageNumber = document.getElementById('pageNumber');
        const pageInfo = document.getElementById('pageInfo');
        const resultInfo = document.getElementById('resultInfo');
        const previousButton = document.getElementById('previousPage');
        const nextButton = document.getElementById('nextPage');

        if (pageNumber) pageNumber.textContent = `Page ${currentPage} of ${pageCount}`;
        if (pageInfo) {
          pageInfo.textContent = `Showing ${pageRows.length} of ${filteredRows.length} request(s)`;
        }
        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} blood request(s) found.`;
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

      document.querySelectorAll('.edit-button').forEach(button => {
        button.addEventListener('click', () => {
          document.getElementById('edit-id').value = button.dataset.id;
          document.getElementById('edit-patient').value = button.dataset.patient;
          document.getElementById('edit-group').value = button.dataset.group;
          document.getElementById('edit-condition').value = button.dataset.condition;
          document.getElementById('edit-units').value = button.dataset.units;
          document.getElementById('edit-hospital-address').value = button.dataset.hospitalAddress;
          document.getElementById('edit-ward').value = button.dataset.ward;
          document.getElementById('edit-contact').value = button.dataset.contact;
          document.getElementById('edit-runner').value = button.dataset.runner;
          document.getElementById('edit-status').value = button.dataset.status;
          modal.hidden = false;
        });
      });

      document.querySelectorAll('[data-close-edit]').forEach(button => {
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