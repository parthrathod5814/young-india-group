<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

$allowedGroups = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
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
$allowedStatuses = ['Active', 'Inactive'];

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
$toastMessage = $_SESSION['donor_toast_message'] ?? '';
$toastType = $_SESSION['donor_toast_type'] ?? 'success';
unset($_SESSION['donor_toast_message'], $_SESSION['donor_toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Your form expired. Reload the page and try again.';
    }

    if (strlen($values['name']) < 2 || strlen($values['name']) > 120) {
        $errors[] = 'Enter a name between 2 and 120 characters.';
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
        $date = DateTime::createFromFormat('Y-m-d', $values['lastDonation']);

        if (!$date || $date->format('Y-m-d') !== $values['lastDonation']) {
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
                'INSERT INTO donors
                    (name, blood_group, phone, age, area, last_donation, status)
                 VALUES
                    (:name, :blood_group, :phone, :age, :area, :last_donation, :status)'
            );

            $stmt->execute([
                ':name' => $values['name'],
                ':blood_group' => $values['group'],
                ':phone' => $values['phone'],
                ':age' => $age,
                ':area' => $values['area'],
                ':last_donation' => $lastDonation,
                ':status' => $values['status'],
            ]);

            $_SESSION['donor_toast_message'] = 'Donor saved successfully.';
            $_SESSION['donor_toast_type'] = 'success';

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            error_log('Donor save failed: ' . $e->getMessage());
            $errors[] = 'The donor could not be saved. Check the donors table and try again.';
        }
    }

    if ($errors) {
        $toastMessage = $errors[0];
        $toastType = 'error';
    }
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
        <section class="adm-page" id="page-donors-add">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-user-group"></i>
              Add New Donor
              <span class="adm-chip">Donor Registry</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-panel__head">
              <h3>Donor Details</h3>
              <a class="adm-btn adm-btn--sm" href="view-donors.php">
                <i class="fa-solid fa-table-list"></i> View all Donors
              </a>
            </div>

            <div class="adm-panel__body">
              <?php if ($errors): ?>
                <div class="adm-alert adm-alert--error" role="alert">
                  <ul>
                    <?php foreach ($errors as $error): ?>
                      <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form class="adm-form" id="form-donors" method="post" action="">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="adm-field">
                  <label for="donors-name">Full Name *</label>
                  <input
                    id="donors-name"
                    name="name"
                    type="text"
                    minlength="2"
                    maxlength="120"
                    required
                    value="<?= e($values['name']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="donors-group">Blood Group *</label>
                  <select id="donors-group" name="group" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedGroups as $group): ?>
                      <option
                        value="<?= e($group) ?>"
                        <?= $values['group'] === $group ? 'selected' : '' ?>
                      ><?= e($group) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="donors-phone">Phone / WhatsApp *</label>
                  <input
                    id="donors-phone"
                    name="phone"
                    type="tel"
                    minlength="10"
                    maxlength="10"
                    pattern="[0-9+() .-]{10,20}
                    title="Enter a valid phone number"
                    autocomplete="tel"
                    required
                    value="<?= e($values['phone']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="donors-age">Age (18-60) *</label>
                  <input
                    id="donors-age"
                    name="age"
                    type="number"
                    min="18"
                    max="60"
                    step="1"
                    required
                    value="<?= e($values['age']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="donors-area">Area / Taluka *</label>
                  <select id="donors-area" name="area" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedAreas as $area): ?>
                      <option
                        value="<?= e($area) ?>"
                        <?= $values['area'] === $area ? 'selected' : '' ?>
                      ><?= e($area) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="donors-lastDonation">Last Donation</label>
                  <input
                    id="donors-lastDonation"
                    name="lastDonation"
                    type="date"
                    max="<?= date('Y-m-d') ?>"
                    value="<?= e($values['lastDonation']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="donors-status">Status *</label>
                  <select id="donors-status" name="status" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                      <option
                        value="<?= e($status) ?>"
                        <?= $values['status'] === $status ? 'selected' : '' ?>
                      ><?= e($status) ?></option>
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
    data-message="<?= e($toastMessage) ?>"
    data-type="<?= e($toastType) ?>"
  ></div>

  <script>
    const donorToast = document.getElementById('toast');

    if (donorToast && donorToast.dataset.message) {
      donorToast.textContent = donorToast.dataset.message;
      donorToast.classList.add('is-show');

      if (donorToast.dataset.type === 'error') {
        donorToast.classList.add('is-error');
      }

      window.setTimeout(() => {
        donorToast.classList.remove('is-show', 'is-error');
      }, 5000);
    }
  </script>

  <script src="admin.js"></script>
</body>
</html>