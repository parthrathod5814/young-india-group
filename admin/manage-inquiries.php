<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

$allowedTypes = [
    'Blood Donor Verification',
    'Urgent Blood Request',
    'Volunteer Registration',
    'Welfare / Scholarship Help',
];

$allowedStatuses = ['New', 'In Progress', 'Resolved'];

function inquiryManageEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function setInquiryManageToast(string $message, string $type = 'success'): void
{
    $_SESSION['inquiry_manage_toast_message'] = $message;
    $_SESSION['inquiry_manage_toast_type'] = $type;
}

if (empty($_SESSION['inquiry_manage_csrf_token'])) {
    $_SESSION['inquiry_manage_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['inquiry_manage_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        setInquiryManageToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        setInquiryManageToast('Invalid inquiry record.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM inquiries WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                setInquiryManageToast('Inquiry deleted successfully.');
            } else {
                setInquiryManageToast('That inquiry was not found.', 'error');
            }

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($action !== 'update') {
            setInquiryManageToast('Unknown action.', 'error');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        $errors = [];

        $nameLength = function_exists('mb_strlen')
            ? mb_strlen($name, 'UTF-8')
            : strlen($name);

        if ($nameLength < 2 || $nameLength > 100) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }

        if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
            $errors[] = 'Enter a valid 10-digit mobile number.';
        }

        if (!in_array($type, $allowedTypes, true)) {
            $errors[] = 'Select a valid inquiry type.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = 'Select a valid inquiry status.';
        }

        $messageLength = function_exists('mb_strlen')
            ? mb_strlen($message, 'UTF-8')
            : strlen($message);

        if ($messageLength > 5000) {
            $errors[] = 'Message must be 5,000 characters or fewer.';
        }

        if ($errors) {
            setInquiryManageToast(implode(' ', $errors), 'error');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE inquiries
                 SET name = :name,
                     phone = :phone,
                     type = :type,
                     status = :status,
                     message = :message
                 WHERE id = :id'
            );

            $stmt->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':type' => $type,
                ':status' => $status,
                ':message' => $message !== '' ? $message : null,
                ':id' => $id,
            ]);

            setInquiryManageToast('Inquiry updated successfully.');
        }
    } catch (PDOException $e) {
        error_log('Inquiry operation failed: ' . $e->getMessage());
        setInquiryManageToast('The operation failed. Please try again.', 'error');
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$toastMessage = $_SESSION['inquiry_manage_toast_message'] ?? '';
$toastType = $_SESSION['inquiry_manage_toast_type'] ?? 'success';
unset($_SESSION['inquiry_manage_toast_message'], $_SESSION['inquiry_manage_toast_type']);

$inquiries = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT id, name, phone, type, status, message, created_at
         FROM inquiries
         ORDER BY created_at DESC, id DESC'
    );

    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load inquiries: ' . $e->getMessage());
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
        <section class="adm-page" id="page-inquiries-manage">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-envelope"></i>
              <span>Manage Inquiries</span>
              <span class="adm-chip">Contact Inquiries</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="inquirySearch"
                  placeholder="Search inquiries..."
                  aria-label="Search inquiries"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-inquiry.php">
                  <i class="fa-solid fa-plus"></i> Add Inquiry
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load inquiries. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($inquiries) ?> inquiry(ies) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Name</th>
                      <th>Phone</th>
                      <th>Inquiry Type</th>
                      <th>Status</th>
                      <th>Message</th>
                      <th>Created</th>
                      <th>Action</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$inquiries): ?>
                      <tr class="empty-row">
                        <td colspan="8">No inquiries have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($inquiries as $index => $inquiry): ?>
                        <tr class="inquiry-row">
                          <td class="inquiry-number"><?= $index + 1 ?></td>
                          <td><?= inquiryManageEscape($inquiry['name']) ?></td>
                          <td><?= inquiryManageEscape($inquiry['phone']) ?></td>
                          <td><?= inquiryManageEscape($inquiry['type']) ?></td>
                          <td><?= inquiryManageEscape($inquiry['status']) ?></td>
                          <td><?= inquiryManageEscape($inquiry['message'] ?: '—') ?></td>
                          <td><?= inquiryManageEscape($inquiry['created_at']) ?></td>

                          <td class="is-actions">
                            <button
                              type="button"
                              class="adm-btn adm-btn--sm edit-inquiry"
                              data-id="<?= inquiryManageEscape($inquiry['id']) ?>"
                              data-name="<?= inquiryManageEscape($inquiry['name']) ?>"
                              data-phone="<?= inquiryManageEscape($inquiry['phone']) ?>"
                              data-type="<?= inquiryManageEscape($inquiry['type']) ?>"
                              data-status="<?= inquiryManageEscape($inquiry['status']) ?>"
                              data-message="<?= inquiryManageEscape($inquiry['message']) ?>"
                            >
                              <i class="fa-solid fa-pen"></i> Update
                            </button>

                            <form method="post" class="delete-inquiry-form" 
                            data-confirm="Are you sure you want to delete this inquiry?">
                              <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= inquiryManageEscape($_SESSION['inquiry_manage_csrf_token']) ?>"
                              >
                              <input type="hidden" name="action" value="delete">
                              <input
                                type="hidden"
                                name="id"
                                value="<?= inquiryManageEscape($inquiry['id']) ?>"
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
                      <td colspan="8">No matching inquiries found.</td>
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

  <!-- Update inquiry modal -->
  <div class="adm-modal" id="editInquiryModal" hidden>
    <div
      class="adm-modal__box is-wide"
      role="dialog"
      aria-modal="true"
      aria-labelledby="editInquiryTitle"
    >
      <div class="adm-modal__head">
        <h3 id="editInquiryTitle">
          <i class="fa-solid fa-pen-to-square"></i> Update Inquiry
        </h3>
        <button
          type="button"
          class="adm-x"
          data-close-inquiry-modal
          aria-label="Close"
        >&times;</button>
      </div>

      <div class="adm-modal__body">
        <form class="adm-form" id="editInquiryForm" method="post">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= inquiryManageEscape($_SESSION['inquiry_manage_csrf_token']) ?>"
          >
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-inquiry-id">

          <div class="adm-field">
            <label for="edit-inquiry-name">Full Name *</label>
            <input
              id="edit-inquiry-name"
              name="name"
              type="text"
              minlength="2"
              maxlength="100"
              required
            >
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-inquiry-phone">Phone / WhatsApp *</label>
            <input
              id="edit-inquiry-phone"
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
            <label for="edit-inquiry-type">Nature of Inquiry *</label>
            <select id="edit-inquiry-type" name="type" required>
              <option value="">Select...</option>
              <?php foreach ($allowedTypes as $type): ?>
                <option value="<?= inquiryManageEscape($type) ?>"><?= inquiryManageEscape($type) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field">
            <label for="edit-inquiry-status">Status *</label>
            <select id="edit-inquiry-status" name="status" required>
              <option value="">Select...</option>
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= inquiryManageEscape($status) ?>"><?= inquiryManageEscape($status) ?></option>
              <?php endforeach; ?>
            </select>
            <small></small>
          </div>

          <div class="adm-field is-wide">
            <label for="edit-inquiry-message">Message</label>
            <textarea
              id="edit-inquiry-message"
              name="message"
              maxlength="5000"
              rows="5"
            ></textarea>
            <small></small>
          </div>
        </form>
      </div>

      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-inquiry-modal>Cancel</button>
        <button type="submit" form="editInquiryForm" class="adm-btn adm-btn--primary">
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
    data-message="<?= inquiryManageEscape($toastMessage) ?>"
    data-type="<?= inquiryManageEscape($toastType) ?>"
  ></div>


  <script src="admin.js"></script>
  <script>
    (() => {
      const searchInput = document.getElementById('inquirySearch');
      const allRows = Array.from(document.querySelectorAll('.inquiry-row'));
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
          row.querySelector('.inquiry-number').textContent = start + index + 1;
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
            `Showing ${first}–${last} of ${filteredRows.length} inquiry(ies)`;
        }

        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} inquiry(ies) found.`;
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

      const modal = document.getElementById('editInquiryModal');

      document.querySelectorAll('.edit-inquiry').forEach(button => {
        button.addEventListener('click', () => {
          document.getElementById('edit-inquiry-id').value = button.dataset.id;
          document.getElementById('edit-inquiry-name').value = button.dataset.name;
          document.getElementById('edit-inquiry-phone').value = button.dataset.phone;
          document.getElementById('edit-inquiry-type').value = button.dataset.type;
          document.getElementById('edit-inquiry-status').value = button.dataset.status;
          document.getElementById('edit-inquiry-message').value = button.dataset.message || '';
          modal.hidden = false;
        });
      });

      document.querySelectorAll('[data-close-inquiry-modal]').forEach(button => {
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
    })();

  </script>
</body>
</html>