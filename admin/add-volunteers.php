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

$allowedStatuses = [
    'Active',
    'Inactive',
];

$values = [
    'name' => '',
    'phone' => '',
    'area' => '',
    'role' => '',
    'availability' => '',
    'status' => '',
];

$errors = [];

if (empty($_SESSION['volunteer_csrf_token'])) {
    $_SESSION['volunteer_csrf_token'] = bin2hex(random_bytes(32));
}

$toastMessage = $_SESSION['volunteer_toast_message'] ?? '';
$toastType = $_SESSION['volunteer_toast_type'] ?? 'success';
unset($_SESSION['volunteer_toast_message'], $_SESSION['volunteer_toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['volunteer_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        $errors[] = 'Your form expired. Reload the page and try again.';
    }

    if (strlen($values['name']) < 2 || strlen($values['name']) > 100) {
        $errors[] = 'Enter a name between 2 and 100 characters.';
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $values['phone'])) {
        $errors[] = 'Enter a valid 10-digit mobile number.';
    }

    if (!in_array($values['area'], $allowedAreas, true)) {
        $errors[] = 'Select a valid area / ward.';
    }

    if (!in_array($values['role'], $allowedRoles, true)) {
        $errors[] = 'Select a valid volunteer role.';
    }

    if (!in_array($values['availability'], $allowedAvailabilities, true)) {
        $errors[] = 'Select a valid availability.';
    }

    if (!in_array($values['status'], $allowedStatuses, true)) {
        $errors[] = 'Select a valid status.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO volunteers
                    (name, phone, area, role, availability, status)
                 VALUES
                    (:name, :phone, :area, :role, :availability, :status)'
            );

            $stmt->execute([
                ':name' => $values['name'],
                ':phone' => $values['phone'],
                ':area' => $values['area'],
                ':role' => $values['role'],
                ':availability' => $values['availability'],
                ':status' => $values['status'],
            ]);

            $_SESSION['volunteer_toast_message'] = 'Volunteer saved successfully.';
            $_SESSION['volunteer_toast_type'] = 'success';

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            error_log('Volunteer save failed: ' . $e->getMessage());
            $errors[] = 'The volunteer could not be saved. Please try again.';
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

<body data-app-mode="php">
  <?php include __DIR__ . '/inc/header.php'; ?>

  <div class="adm-shell">
    <?php include __DIR__ . '/inc/sidebar.php'; ?>

    <main class="adm-main">
      <div class="adm-view" id="view">
        <section class="adm-page" id="page-volunteers-add">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-hand-holding-heart"></i>
              Add New Volunteer
              <span class="adm-chip">Volunteer Network</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-panel__head">
              <h3>Volunteer Details</h3>
              <a class="adm-btn adm-btn--sm" href="view-volunteers.php">
                <i class="fa-solid fa-table-list"></i> View all Volunteers
              </a>
            </div>

            <div class="adm-panel__body">

              <form
                class="adm-form"
                id="form-volunteers"
                method="post"
                action=""
                data-php-submit
              >
                <input
                  type="hidden"
                  name="csrf_token"
                  value="<?= escapeOutput($_SESSION['volunteer_csrf_token']) ?>"
                >

                <div class="adm-field">
                  <label for="volunteers-name">Full Name *</label>
                  <input
                    id="volunteers-name"
                    name="name"
                    type="text"
                    minlength="2"
                    maxlength="100"
                    required
                    value="<?= escapeOutput($values['name']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="volunteers-phone">Phone / WhatsApp *</label>
                  <input
                    id="volunteers-phone"
                    name="phone"
                    type="tel"
                    inputmode="numeric"
                    pattern="[6-9][0-9]{9}"
                    minlength="10"
                    maxlength="10"
                    title="Enter a valid 10-digit mobile number"
                    required
                    value="<?= escapeOutput($values['phone']) ?>"
                  >
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="volunteers-area">Area / Ward *</label>
                  <select id="volunteers-area" name="area" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedAreas as $area): ?>
                      <option
                        value="<?= escapeOutput($area) ?>"
                        <?= selectedOption($values['area'], $area) ?>
                      ><?= escapeOutput($area) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="volunteers-role">Primary Role *</label>
                  <select id="volunteers-role" name="role" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedRoles as $role): ?>
                      <option
                        value="<?= escapeOutput($role) ?>"
                        <?= selectedOption($values['role'], $role) ?>
                      ><?= escapeOutput($role) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="volunteers-availability">Availability *</label>
                  <select id="volunteers-availability" name="availability" required>
                    <option value="">Select...</option>
                    <?php foreach ($allowedAvailabilities as $availability): ?>
                      <option
                        value="<?= escapeOutput($availability) ?>"
                        <?= selectedOption($values['availability'], $availability) ?>
                      ><?= escapeOutput($availability) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small></small>
                </div>

                <div class="adm-field">
                  <label for="volunteers-status">Status *</label>
                  <select id="volunteers-status" name="status" required>
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

  <script>
    const volunteerToast = document.getElementById('toast');

    if (volunteerToast && volunteerToast.dataset.message) {
      volunteerToast.textContent = volunteerToast.dataset.message;
      volunteerToast.classList.add('is-show');

      if (volunteerToast.dataset.type === 'error') {
        volunteerToast.classList.add('is-error');
      }

      window.setTimeout(() => {
        volunteerToast.classList.remove('is-show', 'is-error');
      }, 5000);
    }
  </script>

  <script src="admin.js"></script>
</body>
</html>