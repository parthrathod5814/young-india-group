<?php
session_start();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

$errors = [];
$values = [
    'patient' => '',
    'group' => '',
    'patient_condition' => '',
    'units' => '',
    'hospital_address' => '',
    'ward' => '',
    'contact' => '',
    'runner' => '',
    'status' => '',
];

$allowedGroups = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
$allowedConditions = ['Emergency', 'Normal'];
$allowedStatuses = ['Active', 'In-Transit', 'Fulfilled'];

if (empty($_SESSION['blood_csrf_token'])) {
    $_SESSION['blood_csrf_token'] = bin2hex(random_bytes(32));
}

$toastMessage = $_SESSION['blood_toast_message'] ?? '';
$toastType = $_SESSION['blood_toast_type'] ?? 'success';
unset($_SESSION['blood_toast_message'], $_SESSION['blood_toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['blood_csrf_token'], $submittedToken)) {
        $errors[] = 'The form expired. Please reload the page and try again.';
    }

    if ($values['patient'] === '' || strlen($values['patient']) > 120) {
        $errors[] = 'Enter a patient name (up to 120 characters).';
    }

    if (!in_array($values['group'], $allowedGroups, true)) {
        $errors[] = 'Select a valid blood group.';
    }

    if (!in_array($values['patient_condition'], $allowedConditions, true)) {
        $errors[] = 'Select Emergency or Normal for the patient condition.';
    }

    $units = filter_var($values['units'], FILTER_VALIDATE_INT);
    if ($units === false || $units < 1 || $units > 10) {
        $errors[] = 'Units needed must be a whole number from 1 to 10.';
    }

    if ($values['hospital_address'] === '' || strlen($values['hospital_address']) > 255) {
        $errors[] = 'Enter a hospital name / address (up to 255 characters).';
    }

    if (strlen($values['ward']) > 120) {
        $errors[] = 'Ward / Bed must be 120 characters or fewer.';
    }

    if ($values['contact'] === '' || strlen($values['contact']) > 20) {
        $errors[] = 'Enter an attendant phone number (up to 20 characters).';
    }

    if (strlen($values['runner']) > 100) {
        $errors[] = 'Runner / Coordinator must be 100 characters or fewer.';
    }

    if (!in_array($values['status'], $allowedStatuses, true)) {
        $errors[] = 'Select a valid request status.';
    }

    if (!$errors) {
        try {
            $fulfilledAt = $values['status'] === 'Fulfilled'
                ? date('Y-m-d H:i:s')
                : null;

            $stmt = $pdo->prepare(
                'INSERT INTO blood_requests
                    (patient, blood_group, patient_condition, units, hospital_address,
                     ward, contact, runner, status, fulfilled_at)
                 VALUES
                    (:patient, :blood_group, :patient_condition, :units, :hospital_address,
                     :ward, :contact, :runner, :status, :fulfilled_at)'
            );

            $stmt->execute([
                ':patient' => $values['patient'],
                ':blood_group' => $values['group'],
                ':patient_condition' => $values['patient_condition'],
                ':units' => $units,
                ':hospital_address' => $values['hospital_address'],
                ':ward' => $values['ward'] !== '' ? $values['ward'] : null,
                ':contact' => $values['contact'],
                ':runner' => $values['runner'] !== '' ? $values['runner'] : null,
                ':status' => $values['status'],
                ':fulfilled_at' => $fulfilledAt,
            ]);

            $_SESSION['blood_toast_message'] = 'Blood request saved successfully.';
            $_SESSION['blood_toast_type'] = 'success';

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            error_log('Blood request save failed: ' . $e->getMessage());
            $errors[] = 'The request could not be saved. Please try again.';
        }
    }

    if ($errors) {
        $toastMessage = $errors[0];
        $toastType = 'error';
    }
}

function escapeOutput($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function selectedOption(string $current, string $option): string
{
    return $current === $option ? ' selected' : '';
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
        <section class="adm-page" id="page-blood-add" data-module="blood">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-droplet"></i>
              Add New Blood Request
              <span class="adm-chip">Blood Emergency Hub</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-panel__head">
              <h3>Blood Request Details</h3>
              <a class="adm-btn adm-btn--sm" href="#/blood/list">
                <i class="fa-solid fa-table-list"></i> View all Blood Requests
              </a>
            </div>

            <div class="adm-panel__body">
              <?php if ($errors): ?>
                <div class="adm-alert adm-alert--error" role="alert">
                  <ul>
                    <?php foreach ($errors as $error): ?>
                      <li><?= escapeOutput($error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form
                class="adm-form"
                id="form-blood"
                method="post"
                action=""
                data-module="blood"
                data-php-submit
              >
                <input
                  type="hidden"
                  name="csrf_token"
                  value="<?= escapeOutput($_SESSION['blood_csrf_token']) ?>"
                >

                <div class="adm-field">
                  <label for="blood-patient">Patient Name *</label>
                  <input
                    id="blood-patient"
                    name="patient"
                    type="text"
                    maxlength="120"
                    required
                    value="<?= escapeOutput($values['patient']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-group">Blood Group *</label>
                  <select id="blood-group" name="group" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedGroups as $group): ?>
                      <option
                        value="<?= escapeOutput($group) ?>"
                        <?= selectedOption($values['group'], $group) ?>
                      ><?= escapeOutput($group) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-condition">Patient Condition *</label>
                  <select id="blood-condition" name="patient_condition" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedConditions as $condition): ?>
                      <option
                        value="<?= escapeOutput($condition) ?>"
                        <?= selectedOption($values['patient_condition'], $condition) ?>
                      ><?= escapeOutput($condition) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-units">Units Needed *</label>
                  <input
                    id="blood-units"
                    name="units"
                    type="number"
                    min="1"
                    max="10"
                    required
                    value="<?= escapeOutput($values['units']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-hospital-address">Hospital Name / Address *</label>
                  <input
                    id="blood-hospital-address"
                    name="hospital_address"
                    type="text"
                    maxlength="255"
                    required
                    value="<?= escapeOutput($values['hospital_address']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-ward">Ward / Bed</label>
                  <input
                    id="blood-ward"
                    name="ward"
                    type="text"
                    maxlength="120"
                    value="<?= escapeOutput($values['ward']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-contact">Attendant Phone *</label>
                  <input
                    id="blood-contact"
                    name="contact"
                    type="tel"
                    maxlength="20"
                    required
                    value="<?= escapeOutput($values['contact']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-runner">Runner / Coordinator</label>
                  <input
                    id="blood-runner"
                    name="runner"
                    type="text"
                    maxlength="100"
                    value="<?= escapeOutput($values['runner']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="blood-status">Status *</label>
                  <select id="blood-status" name="status" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                      <option
                        value="<?= escapeOutput($status) ?>"
                        <?= selectedOption($values['status'], $status) ?>
                      ><?= escapeOutput($status) ?></option>
                    <?php endforeach; ?>
                  </select>
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
    data-message="<?= escapeOutput($toastMessage) ?>"
    data-type="<?= escapeOutput($toastType) ?>"
  ></div>

  <!-- admin-data.js is required by admin.js; keep this order -->
  <!-- <script src="admin-data.js"></script> -->
  <script src="admin.js"></script>
</body>
</html>