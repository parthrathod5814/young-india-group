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
    'Other'
];

$allowedStatuses = ['New', 'In Progress', 'Resolved'];

$values = [
    'name' => '',
    'phone' => '',
    'type' => '',
    'status' => '',
    'message' => '',
];

$errors = [];

if (empty($_SESSION['inquiry_csrf_token'])) {
    $_SESSION['inquiry_csrf_token'] = bin2hex(random_bytes(32));
}

$toastMessage = $_SESSION['inquiry_toast_message'] ?? '';
$toastType = $_SESSION['inquiry_toast_type'] ?? 'success';
unset($_SESSION['inquiry_toast_message'], $_SESSION['inquiry_toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $sessionToken = (string) ($_SESSION['inquiry_csrf_token'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        $errors[] = 'Your form expired. Reload the page and try again.';
    }

    $nameLength = function_exists('mb_strlen')
        ? mb_strlen($values['name'], 'UTF-8')
        : strlen($values['name']);

    if ($nameLength < 2 || $nameLength > 100) {
        $errors[] = 'Enter a name between 2 and 100 characters.';
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $values['phone'])) {
        $errors[] = 'Enter a valid 10-digit mobile number.';
    }

    if (!in_array($values['type'], $allowedTypes, true)) {
        $errors[] = 'Select a valid inquiry type.';
    }

    if (!in_array($values['status'], $allowedStatuses, true)) {
        $errors[] = 'Select a valid inquiry status.';
    }

    $messageLength = function_exists('mb_strlen')
        ? mb_strlen($values['message'], 'UTF-8')
        : strlen($values['message']);

    if ($messageLength > 5000) {
        $errors[] = 'The message must be 5,000 characters or fewer.';
    }

    if (!$errors) {
        try {
            // Change 'admin_id' if auth.php stores the logged-in admin ID under another session key.
            $adminId = filter_var(
                $_SESSION['admin_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ipAddress !== null && strlen($ipAddress) > 45) {
                $ipAddress = null;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO inquiries
                    (name, phone, type, status, message, ip_address, created_by)
                 VALUES
                    (:name, :phone, :type, :status, :message, :ip_address, :created_by)'
            );

            $stmt->execute([
                ':name' => $values['name'],
                ':phone' => $values['phone'],
                ':type' => $values['type'],
                ':status' => $values['status'],
                ':message' => $values['message'] !== '' ? $values['message'] : null,
                ':ip_address' => $ipAddress,
                ':created_by' => $adminId !== false ? $adminId : null,
            ]);

            $_SESSION['inquiry_toast_message'] = 'Inquiry saved successfully.';
            $_SESSION['inquiry_toast_type'] = 'success';

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            error_log('Inquiry save failed: ' . $e->getMessage());
            $errors[] = 'The inquiry could not be saved. Please try again.';
        }
    }

    if ($errors) {
        $toastMessage = $errors[0];
        $toastType = 'error';
    }
}

function inquiryEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function inquirySelected(string $current, string $option): string
{
    return $current === $option ? ' selected' : '';
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
        <section class="adm-page" id="page-inquiries-add">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-envelope"></i>
              Add New Inquiry
              <span class="adm-chip">Contact Inquiries</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-panel__head">
              <h3>Inquiry Details</h3>
              <a class="adm-btn adm-btn--sm" href="view-inquiries.php">
                <i class="fa-solid fa-table-list"></i> View all Inquiries
              </a>
            </div>

            <div class="adm-panel__body">
              <?php if ($errors): ?>
                <div class="adm-alert adm-alert--error" role="alert">
                  <ul>
                    <?php foreach ($errors as $error): ?>
                      <li><?= inquiryEscape($error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form
                class="adm-form"
                id="form-inquiries"
                method="post"
                action=""
                data-php-submit
              >
                <input
                  type="hidden"
                  name="csrf_token"
                  value="<?= inquiryEscape($_SESSION['inquiry_csrf_token']) ?>"
                >

                <div class="adm-field">
                  <label for="inquiries-name">Full Name *</label>
                  <input
                    id="inquiries-name"
                    name="name"
                    type="text"
                    minlength="2"
                    maxlength="100"
                    autocomplete="name"
                    required
                    value="<?= inquiryEscape($values['name']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="inquiries-phone">Phone / WhatsApp *</label>
                  <input
                    id="inquiries-phone"
                    name="phone"
                    type="tel"
                    inputmode="numeric"
                    pattern="[6-9][0-9]{9}"
                    minlength="10"
                    maxlength="10"
                    autocomplete="tel"
                    title="Enter a valid 10-digit mobile number"
                    required
                    value="<?= inquiryEscape($values['phone']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="inquiries-type">Nature of Inquiry *</label>
                  <select id="inquiries-type" name="type" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedTypes as $type): ?>
                      <option
                        value="<?= inquiryEscape($type) ?>"
                        <?= inquirySelected($values['type'], $type) ?>
                      ><?= inquiryEscape($type) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="inquiries-status">Status *</label>
                  <select id="inquiries-status" name="status" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                      <option
                        value="<?= inquiryEscape($status) ?>"
                        <?= inquirySelected($values['status'], $status) ?>
                      ><?= inquiryEscape($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field is-wide">
                  <label for="inquiries-message">Message</label>
                  <textarea
                    id="inquiries-message"
                    name="message"
                    maxlength="5000"
                    rows="5"
                  ><?= inquiryEscape($values['message']) ?></textarea>
                  <small></small>
                </div>

                <div class="adm-form__actions">
                  <button type="submit" class="adm-btn adm-btn--primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Record
                  </button>
                  <button type="reset" class="adm-btn">Reset</button>
                </div>
              </form>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <div
    class="adm-toast"
    id="toast"
    role="status"
    aria-live="polite"
    data-message="<?= inquiryEscape($toastMessage) ?>"
    data-type="<?= inquiryEscape($toastType) ?>"
  ></div>

  <script>
    const inquiryToast = document.getElementById('toast');

    if (inquiryToast?.dataset.message) {
      inquiryToast.textContent = inquiryToast.dataset.message;
      inquiryToast.classList.add('is-show');

      if (inquiryToast.dataset.type === 'error') {
        inquiryToast.classList.add('is-error');
      }

      window.setTimeout(() => {
        inquiryToast.classList.remove('is-show', 'is-error');
      }, 5000);
    }
  </script>

  <script src="admin.js"></script>
</body>
</html>