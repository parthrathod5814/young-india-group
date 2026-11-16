<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

function dashboardEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function dashboardRedirect(string $popup = ''): never
{
    $url = 'index.php';
    if ($popup !== '') {
        $url .= '?popup=' . rawurlencode($popup);
    }

    header('Location: ' . $url, true, 303);
    exit;
}

if (empty($_SESSION['dashboard_csrf_token'])) {
    $_SESSION['dashboard_csrf_token'] = bin2hex(random_bytes(32));
}

$dashboardFlash = $_SESSION['dashboard_flash'] ?? null;
unset($_SESSION['dashboard_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['dashboard_csrf_token'] ?? '');
    $popup = (string) ($_POST['popup'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        $_SESSION['dashboard_flash'] = [
            'type' => 'error',
            'message' => 'Your session expired. Reload the page and try again.',
        ];
        dashboardRedirect($popup);
    }

    $action = (string) ($_POST['dashboard_action'] ?? '');
    $recordId = filter_var(
        $_POST['record_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $adminId = $_SESSION['admin_id'] ?? ($_SESSION['admin']['id'] ?? null);
    $adminId = filter_var($adminId, FILTER_VALIDATE_INT) ?: null;

    try {
        if (!$recordId) {
            throw new RuntimeException('Invalid record ID.');
        }

        if ($action === 'approve_donor') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT name, blood_group, phone, age, area, last_donation
                 FROM pending_donors
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $recordId]);
            $donor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$donor) {
                throw new RuntimeException('Pending donor was not found.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO donors
                    (name, blood_group, phone, age, area, last_donation,
                     status, approved_by, approved_at, created_by)
                 VALUES
                    (:name, :blood_group, :phone, :age, :area, :last_donation,
                     'Active', :approved_by, NOW(), :created_by)"
            );
            $insert->execute([
                ':name' => $donor['name'],
                ':blood_group' => $donor['blood_group'],
                ':phone' => $donor['phone'],
                ':age' => $donor['age'],
                ':area' => $donor['area'],
                ':last_donation' => $donor['last_donation'],
                ':approved_by' => $adminId,
                ':created_by' => $adminId,
            ]);

            $delete = $pdo->prepare('DELETE FROM pending_donors WHERE id = :id');
            $delete->execute([':id' => $recordId]);

            $pdo->commit();

            $_SESSION['dashboard_flash'] = [
                'type' => 'success',
                'message' => 'Donor approved and added to the donor registry.',
            ];
            dashboardRedirect('donors');
        }

        if ($action === 'remove_donor') {
            $stmt = $pdo->prepare('DELETE FROM pending_donors WHERE id = :id');
            $stmt->execute([':id' => $recordId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Pending donor was not found or was already removed.');
            }

            $_SESSION['dashboard_flash'] = [
                'type' => 'success',
                'message' => 'Pending donor removed.',
            ];
            dashboardRedirect('donors');
        }

        if ($action === 'approve_volunteer') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT name, phone, area, role, availability
                 FROM pending_volunteers
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $recordId]);
            $volunteer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$volunteer) {
                throw new RuntimeException('Pending volunteer was not found.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO volunteers
                    (name, phone, area, role, availability, status,
                     approved_by, approved_at, created_by)
                 VALUES
                    (:name, :phone, :area, :role, :availability, 'Active',
                     :approved_by, NOW(), :created_by)"
            );
            $insert->execute([
                ':name' => $volunteer['name'],
                ':phone' => $volunteer['phone'],
                ':area' => $volunteer['area'],
                ':role' => $volunteer['role'],
                ':availability' => $volunteer['availability'],
                ':approved_by' => $adminId,
                ':created_by' => $adminId,
            ]);

            $delete = $pdo->prepare('DELETE FROM pending_volunteers WHERE id = :id');
            $delete->execute([':id' => $recordId]);

            $pdo->commit();

            $_SESSION['dashboard_flash'] = [
                'type' => 'success',
                'message' => 'Volunteer approved and added to the volunteer registry.',
            ];
            dashboardRedirect('volunteers');
        }

        if ($action === 'remove_volunteer') {
            $stmt = $pdo->prepare('DELETE FROM pending_volunteers WHERE id = :id');
            $stmt->execute([':id' => $recordId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Pending volunteer was not found or was already removed.');
            }

            $_SESSION['dashboard_flash'] = [
                'type' => 'success',
                'message' => 'Pending volunteer removed.',
            ];
            dashboardRedirect('volunteers');
        }

        if ($action === 'add_interest_to_registry') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id, donor_id, donor_name, blood_group, phone, age, area
                 FROM donation_interests
                 WHERE id = :id
                   AND status IN ('Pending', 'Contacted', 'Accepted')
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $recordId]);
            $interest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$interest) {
                throw new RuntimeException('Donation interest was not found.');
            }

            $donorId = (int) ($interest['donor_id'] ?? 0);

            if ($donorId === 0 && trim((string) ($interest['phone'] ?? '')) !== '') {
                $existing = $pdo->prepare(
                    'SELECT id FROM donors WHERE phone = :phone LIMIT 1'
                );
                $existing->execute([':phone' => $interest['phone']]);
                $donorId = (int) ($existing->fetchColumn() ?: 0);
            }

            if ($donorId === 0) {
                foreach (['donor_name', 'blood_group', 'phone', 'age', 'area'] as $field) {
                    if (
                        !array_key_exists($field, $interest)
                        || $interest[$field] === null
                        || trim((string) $interest[$field]) === ''
                    ) {
                        throw new RuntimeException(
                            'Cannot add this donor: required donor details are missing.'
                        );
                    }
                }

                $insert = $pdo->prepare(
                    "INSERT INTO donors
                        (name, blood_group, phone, age, area, status,
                         approved_by, approved_at, created_by)
                     VALUES
                        (:name, :blood_group, :phone, :age, :area, 'Active',
                         :approved_by, NOW(), :created_by)"
                );
                $insert->execute([
                    ':name' => $interest['donor_name'],
                    ':blood_group' => $interest['blood_group'],
                    ':phone' => $interest['phone'],
                    ':age' => $interest['age'],
                    ':area' => $interest['area'],
                    ':approved_by' => $adminId,
                    ':created_by' => $adminId,
                ]);

                $donorId = (int) $pdo->lastInsertId();
            }

            $update = $pdo->prepare(
                "UPDATE donation_interests
                 SET donor_id = :donor_id, status = 'Accepted'
                 WHERE id = :id"
            );
            $update->execute([
                ':donor_id' => $donorId,
                ':id' => $recordId,
            ]);

            $pdo->commit();

            $_SESSION['dashboard_flash'] = [
                'type' => 'success',
                'message' => 'Interested donor added to the registry.',
            ];
            dashboardRedirect('contact');
        }

        if ($action === 'delete_interest') {
            $stmt = $pdo->prepare('DELETE FROM donation_interests WHERE id = :id');
            $stmt->execute([':id' => $recordId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Donation interest was not found or was already deleted.');
            }

            $_SESSION['dashboard_flash'] = [
                'type' => 'success',
                'message' => 'Donation interest record deleted.',
            ];
            dashboardRedirect('contact');
        }

        throw new RuntimeException('Unknown dashboard action.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Dashboard action failed: ' . $e->getMessage());

        $_SESSION['dashboard_flash'] = [
            'type' => 'error',
            'message' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The action could not be completed. Check the server error log.',
        ];
        dashboardRedirect($popup);
    }
}

$kpis = [
    'volunteers' => 0,
    'donors' => 0,
    'urgent' => 0,
    'saved' => 0,
    'drives' => 0,
    'talukas' => 0,
];

$pendingDonors = [];
$pendingVolunteers = [];
$donationInterests = [];
$liveRequests = [];

try {
    $kpis['volunteers'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM volunteers WHERE status = 'Active'"
    )->fetchColumn();

    $kpis['donors'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM donors WHERE status = 'Active'"
    )->fetchColumn();

    $kpis['urgent'] = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM blood_requests
         WHERE patient_condition = 'Emergency'
           AND status IN ('Active', 'In-Transit')"
    )->fetchColumn();

    $kpis['saved'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM blood_requests WHERE status = 'Fulfilled'"
    )->fetchColumn();

    $kpis['drives'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM activities WHERE status = 'Published'"
    )->fetchColumn();

    $kpis['talukas'] = (int) $pdo->query(
        "SELECT COUNT(DISTINCT area)
         FROM (
             SELECT area FROM donors WHERE status = 'Active'
             UNION
             SELECT area FROM volunteers WHERE status = 'Active'
         ) AS active_areas"
    )->fetchColumn();

    $pendingDonors = $pdo->query(
        'SELECT id, name, blood_group, phone, age, area, last_donation, submitted_at
         FROM pending_donors
         ORDER BY submitted_at DESC, id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $pendingVolunteers = $pdo->query(
        'SELECT id, name, phone, area, role, availability, submitted_at
         FROM pending_volunteers
         ORDER BY submitted_at DESC, id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $donationInterests = $pdo->query(
        "SELECT
            di.id,
            di.blood_request_id,
            di.donor_id,
            di.donor_name,
            di.blood_group,
            di.phone,
            di.age,
            di.area,
            di.status,
            di.created_at,
            br.patient,
            br.hospital_address
         FROM donation_interests AS di
         LEFT JOIN blood_requests AS br ON br.id = di.blood_request_id
         WHERE di.status IN ('Pending', 'Contacted', 'Accepted')
         ORDER BY di.created_at DESC, di.id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $liveRequests = $pdo->query(
        "SELECT id, patient, blood_group, patient_condition, units,
                hospital_address, ward, contact, runner, status, created_at
         FROM blood_requests
         WHERE status IN ('Active', 'In-Transit')
         ORDER BY
            CASE WHEN patient_condition = 'Emergency' THEN 0 ELSE 1 END,
            created_at DESC,
            id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Dashboard data query failed: ' . $e->getMessage());
}

$csrfToken = (string) $_SESSION['dashboard_csrf_token'];
$openPopup = (string) ($_GET['popup'] ?? '');
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

        <section class="adm-page" id="page-dashboard">
          <div class="adm-page-head">
            <h2>Operations Command Overview <span class="adm-chip"> Live Sync</span></h2>
            <small><i class="fa-solid fa-bolt"></i> High Alert Ready</small>
          </div>

          <div class="adm-grid">
            <div class="adm-stat">
              <div><small>TOTAL VOLUNTEERS</small><strong data-stat="volunteers"><?= (int) $kpis['volunteers'] ?></strong><em>Morbi District Active Cadre</em></div>
              <span class="adm-stat__icon"><i class="fa-solid fa-people-group"></i></span>
            </div>
            <div class="adm-stat">
              <div><small>VERIFIED DONORS</small><strong data-stat="donors"><?= (int) $kpis['donors'] ?></strong><em>Across 8 Talukas Network</em></div>
              <span class="adm-stat__icon"><i class="fa-solid fa-hand-holding-heart"></i></span>
            </div>
            <div class="adm-stat adm-stat--alert">
              <div><small>URGENT BLOOD</small><strong data-stat="urgent"><?= (int) $kpis['urgent'] ?></strong><em>Morbi Civil &amp; Private ICU</em></div>
              <span class="adm-stat__icon"><i class="fa-solid fa-heart-pulse"></i></span>
            </div>
            <div class="adm-stat">
              <div><small>LIVES SAVED</small><strong data-stat="saved"><?= (int) $kpis['saved'] ?></strong><em>Fulfilled requests</em></div>
              <span class="adm-stat__icon"><i class="fa-solid fa-heart"></i></span>
            </div>
            <div class="adm-stat">
              <div><small>CIVIC ACTIVITIES</small><strong data-stat="drives"><?= (int) $kpis['drives'] ?></strong><em>Camps, Relief &amp; Green Morbi</em></div>
              <span class="adm-stat__icon"><i class="fa-solid fa-bullhorn"></i></span>
            </div>
            <div class="adm-stat">
              <div><small>TALUKA CENTERS</small><strong data-stat="talukas"><?= (int) $kpis['talukas'] ?></strong><em>Grassroots Base</em></div>
              <span class="adm-stat__icon"><i class="fa-solid fa-circle-nodes"></i></span>
            </div>
          </div>

          <div class="adm-inq">
            <button type="button" class="adm-inq__btn" data-inq="donors">
              <span class="adm-inq__icon"><i class="fa-solid fa-hand-holding-droplet"></i></span>
              <div><strong>Donors Registration</strong><small>Review new donor registrations</small></div>
              <span class="adm-inq__count" data-count="donors"><?= count($pendingDonors) ?></span>
            </button>
            <button type="button" class="adm-inq__btn" data-inq="contact">
              <span class="adm-inq__icon adm-inq__icon--orange"><i class="fa-solid fa-heart-pulse"></i></span>
              <div><strong>Interested to Donate</strong><small>Review inquiries from potential donors</small></div>
              <span class="adm-inq__count" data-count="contact"><?= count($donationInterests) ?></span>
            </button>
            <button type="button" class="adm-inq__btn" data-inq="volunteers">
              <span class="adm-inq__icon adm-inq__icon--green"><i class="fa-solid fa-people-group"></i></span>
              <div><strong>Volunteers Registration</strong><small>Review new volunteer registrations</small></div>
              <span class="adm-inq__count" data-count="volunteers"><?= count($pendingVolunteers) ?></span>
            </button>
          </div>

          <div class="adm-panel">
            <div class="adm-panel__head">
              <h3>Live Blood Request<small>Morbi Taluka Emergency Desk</small></h3>
              <div class="adm-actions">
                <span class="adm-badge adm-badge--red">Live Feed</span>
                <button class="adm-btn adm-btn--sm" id="feedRefresh" type="button" title="Refresh">
                  <i class="fa-solid fa-rotate"></i>
                </button>
              </div>
            </div>

            <div id="feedArea">
              <?php if (!$liveRequests): ?>
                <p>No active blood requests.</p>
              <?php endif; ?>

              <?php foreach ($liveRequests as $request): ?>
                <div class="adm-feed__row">
                  <div class="adm-feed__main">
                    <span class="adm-group-badge"><?= dashboardEscape($request['blood_group']) ?></span>
                    <div>
                      <strong><?= dashboardEscape($request['patient']) ?></strong>
                      <span class="adm-badge"><?= dashboardEscape($request['patient_condition']) ?></span>
                      <small>
                        <?= dashboardEscape($request['hospital_address']) ?>
                        · <?= (int) $request['units'] ?> unit(s)
                        · <?= dashboardEscape($request['status']) ?>
                      </small>
                    </div>
                  </div>
                  <div class="adm-feed__side">
                    <a
                      class="adm-btn adm-btn--green adm-btn--sm"
                      href="manage-blood-request.php?id=<?= (int) $request['id'] ?>&amp;search=<?= rawurlencode((string) $request['patient']) ?>"
                    >Manage</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

      </div>
    </main>
  </div>

  <!-- POPUPS -->
  <div class="adm-modal" id="modal-confirm" hidden>
    <div class="adm-modal__box" role="dialog" aria-modal="true">
      <div class="adm-modal__head">
        <h3><i class="fa-solid fa-triangle-exclamation" data-slot="icon"></i> <span data-slot="title">Confirm</span></h3>
        <button type="button" class="adm-x" data-close aria-label="Close">&times;</button>
      </div>
      <div class="adm-modal__body"><p data-slot="message"></p></div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Cancel</button>
        <button type="button" class="adm-btn adm-btn--danger" data-slot="confirm">Yes</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-message" hidden>
    <div class="adm-modal__box" role="dialog" aria-modal="true">
      <div class="adm-modal__head">
        <h3><i class="fa-solid fa-circle-check" data-slot="icon"></i> <span data-slot="title">Done</span></h3>
        <button type="button" class="adm-x" data-close aria-label="Close">&times;</button>
      </div>
      <div class="adm-modal__body"><p data-slot="message"></p></div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-slot="secondary" hidden></button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="primary">OK</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-edit" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head">
        <h3><i class="fa-solid fa-pen-to-square" data-slot="icon"></i> <span data-slot="title">Update</span></h3>
        <button type="button" class="adm-x" data-close aria-label="Close">&times;</button>
      </div>
      <div class="adm-modal__body"><div data-slot="form"></div></div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Cancel</button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="save"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-notifications" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head">
        <h3><i class="fa-solid fa-bell" data-slot="icon"></i> <span data-slot="title">Notifications</span></h3>
        <button type="button" class="adm-x" data-close aria-label="Close">&times;</button>
      </div>
      <div class="adm-modal__body"><div class="adm-notes" data-slot="list"></div></div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Close</button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="markall"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-pending" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head">
        <h3><i class="fa-solid fa-hand-holding-droplet" data-slot="icon"></i> <span data-slot="title">Inquiries</span></h3>
        <button type="button" class="adm-x" data-close aria-label="Close">&times;</button>
      </div>

      <div class="adm-modal__body">
        <div class="adm-pending" data-slot="list">

          <section data-popup-list="donors" hidden>
            <?php if (!$pendingDonors): ?><p>No pending donor registrations.</p><?php endif; ?>

            <?php foreach ($pendingDonors as $row): ?>
              <article class="adm-pending__item">
                <div class="adm-pending__head">
                  <div>
                    <strong><?= dashboardEscape($row['name']) ?></strong>
                    <small>
                      <?= dashboardEscape($row['blood_group']) ?>
                      · <?= dashboardEscape($row['phone']) ?>
                      · <?= dashboardEscape($row['area']) ?>
                      · Age <?= (int) $row['age'] ?>
                    </small>
                  </div>
                  <span class="adm-badge adm-badge--orange">Pending</span>
                </div>

                <div class="adm-pending__foot">
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                    <input type="hidden" name="popup" value="donors">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="dashboard_action" value="approve_donor">
                    <button class="adm-btn adm-btn--green" type="submit">
                      <i class="fa-solid fa-check"></i> Approve
                    </button>
                  </form>

                  <form method="post" data-confirm="Remove this pending donor?">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                    <input type="hidden" name="popup" value="donors">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="dashboard_action" value="remove_donor">
                    <button class="adm-btn adm-btn--danger" type="submit">
                      <i class="fa-solid fa-trash"></i> Remove
                    </button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </section>

          <section data-popup-list="volunteers" hidden>
            <?php if (!$pendingVolunteers): ?><p>No pending volunteer registrations.</p><?php endif; ?>

            <?php foreach ($pendingVolunteers as $row): ?>
              <article class="adm-pending__item">
                <div class="adm-pending__head">
                  <div>
                    <strong><?= dashboardEscape($row['name']) ?></strong>
                    <small>
                      <?= dashboardEscape($row['phone']) ?>
                      · <?= dashboardEscape($row['area']) ?>
                      · <?= dashboardEscape($row['role']) ?>
                      · <?= dashboardEscape($row['availability']) ?>
                    </small>
                  </div>
                  <span class="adm-badge adm-badge--orange">Pending</span>
                </div>

                <div class="adm-pending__foot">
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                    <input type="hidden" name="popup" value="volunteers">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="dashboard_action" value="approve_volunteer">
                    <button class="adm-btn adm-btn--green" type="submit">
                      <i class="fa-solid fa-check"></i> Approve
                    </button>
                  </form>

                  <form method="post" data-confirm="Remove this pending volunteer?">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                    <input type="hidden" name="popup" value="volunteers">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="dashboard_action" value="remove_volunteer">
                    <button class="adm-btn adm-btn--danger" type="submit">
                      <i class="fa-solid fa-trash"></i> Remove
                    </button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </section>

          <section data-popup-list="contact" hidden>
            <!-- <h4>Interested to Donate</h4> -->
            <?php if (!$donationInterests): ?><p>No donor interest records.</p><?php endif; ?>

            <?php foreach ($donationInterests as $row): ?>
              <?php
                $canAdd =
                    trim((string) ($row['donor_name'] ?? '')) !== ''
                    && trim((string) ($row['blood_group'] ?? '')) !== ''
                    && trim((string) ($row['phone'] ?? '')) !== ''
                    && $row['age'] !== null
                    && trim((string) ($row['area'] ?? '')) !== '';
              ?>
              <article class="adm-pending__item">
                <div class="adm-pending__head">
                  <div>
                    <strong><?= dashboardEscape($row['donor_name'] ?: 'Interested donor') ?></strong>
                    <small>
                      <?= dashboardEscape($row['blood_group'] ?? '') ?>
                      · <?= dashboardEscape($row['phone'] ?? '') ?>
                      · <?= dashboardEscape($row['area'] ?? '') ?>
                      <?php if (!empty($row['patient'])): ?>
                        · Request for <?= dashboardEscape($row['patient']) ?>
                      <?php endif; ?>
                    </small>
                  </div>
                  <span class="adm-badge"><?= dashboardEscape($row['status']) ?></span>
                </div>

                <div class="adm-pending__foot">
                  <?php if ($canAdd): ?>
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                      <input type="hidden" name="popup" value="contact">
                      <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="dashboard_action" value="add_interest_to_registry">
                      <button class="adm-btn adm-btn--green" type="submit">
                        <i class="fa-solid fa-user-plus"></i> Add to Registry
                      </button>
                    </form>
                  <?php else: ?>
                    <span>Cannot add: donor details are incomplete.</span>
                  <?php endif; ?>

                  <?php if (!empty($row['phone'])): ?>
                    <?php $phoneLink = preg_replace('/[^0-9+]/', '', (string) $row['phone']); ?>
                    <a class="adm-btn" href="tel:<?= dashboardEscape($phoneLink) ?>">
                      <i class="fa-solid fa-phone"></i> Call Now
                    </a>
                  <?php endif; ?>

                  <form method="post" data-confirm="Delete this donation interest record?">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                    <input type="hidden" name="popup" value="contact">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="dashboard_action" value="delete_interest">
                    <button class="adm-btn adm-btn--danger" type="submit">
                      <i class="fa-solid fa-trash"></i> Delete
                    </button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </section>

        </div>
      </div>

      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Close</button>
      </div>
    </div>
  </div>

  <!-- Existing JavaScript templates retained for admin.js compatibility -->
  <template id="tpl-actions">
    <button class="adm-btn adm-btn--sm" data-act="edit"><i class="fa-solid fa-pen"></i> Update</button>
    <button class="adm-btn adm-btn--danger adm-btn--sm" data-act="delete"><i class="fa-solid fa-trash"></i> Delete</button>
  </template>

  <template id="tpl-feed-row">
    <div class="adm-feed__row">
      <div class="adm-feed__main">
        <span class="adm-group-badge" data-f="group"></span>
        <div>
          <strong data-f="patient"></strong>
          <span class="adm-badge" data-f="badge"></span>
          <small data-f="where"></small>
        </div>
      </div>
      <div class="adm-feed__side">
        <button class="adm-btn adm-btn--green adm-btn--sm" data-for="In-Transit" data-act="done">Manage</button>
      </div>
    </div>
  </template>

  <template id="tpl-notice">
    <a class="adm-notice">
      <span class="adm-notice__icon"><i class="fa-solid"></i></span>
      <div><strong data-f="title"></strong><small data-f="text"></small></div>
      <time data-f="time"></time>
    </a>
  </template>

  <template id="tpl-pending">
    <div class="adm-pending__item">
      <div class="adm-pending__head">
        <div><strong data-f="name"></strong><small data-f="meta"></small></div>
        <span class="adm-badge adm-badge--orange">Pending</span>
      </div>
      <dl class="adm-pending__grid" data-f="grid"></dl>
      <div class="adm-pending__foot">
        <span><i class="fa-solid fa-hourglass-half"></i> Held in the temporary table until approved</span>
        <button class="adm-btn adm-btn--green" data-approve><i class="fa-solid fa-check"></i> Approve</button>
      </div>
    </div>
  </template>

  <template id="tpl-backup-row">
    <tr>
      <td><b data-f="name"></b><br><small data-f="note"></small></td>
      <td data-f="created"></td>
      <td><span class="adm-badge" data-f="type"></span></td>
      <td data-f="records"></td>
      <td data-f="size"></td>
      <td class="is-actions">
        <button class="adm-btn adm-btn--sm" data-bk="download" title="Download"><i class="fa-solid fa-download"></i></button>
        <button class="adm-btn adm-btn--green adm-btn--sm is-restore-only" data-bk="restore"><i class="fa-solid fa-clock-rotate-left"></i> Restore</button>
        <button class="adm-btn adm-btn--danger adm-btn--sm" data-bk="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
      </td>
    </tr>
  </template>

  <div
    class="adm-toast"
    id="toast"
    role="status"
    aria-live="polite"
    data-message="<?= dashboardEscape($dashboardFlash['message'] ?? '') ?>"
    data-type="<?= dashboardEscape($dashboardFlash['type'] ?? 'success') ?>"
  ></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
  <script src="admin.js"></script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("modal-pending");
    const list = modal?.querySelector('[data-slot="list"]');

    document.addEventListener("click", event => {
      const trigger = event.target.closest("[data-inq]");
      if (!trigger || !modal || !list) return;

      const selected = trigger.dataset.inq;
      list.querySelectorAll("[data-popup-list]").forEach(section => {
        section.hidden = section.dataset.popupList !== selected;
      });

      const titles = {
        donors: "Donor Registrations",
        volunteers: "Volunteer Registrations",
        contact: "Interested to Donate"
      };

      const titleSlot = modal.querySelector('[data-slot="title"]');
      if (titleSlot) titleSlot.textContent = titles[selected] || "Inquiries";

      document.querySelectorAll(".adm-modal").forEach(item => {
        item.hidden = true;
      });
      modal.hidden = false;
    });

    document.getElementById("feedRefresh")?.addEventListener("click", () => {
      window.location.reload();
    });

    const requestedPopup = <?= json_encode(
        $openPopup,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    if (requestedPopup && modal && list) {
      const section = [...list.querySelectorAll("[data-popup-list]")]
        .find(item => item.dataset.popupList === requestedPopup);

      if (section) {
        list.querySelectorAll("[data-popup-list]").forEach(item => {
          item.hidden = item !== section;
        });

        const titles = {
          donors: "Donor Registrations",
          volunteers: "Volunteer Registrations",
          contact: "Interested to Donate"
        };

        const titleSlot = modal.querySelector('[data-slot="title"]');
        if (titleSlot) titleSlot.textContent = titles[requestedPopup] || "Inquiries";
        modal.hidden = false;
      }
    }
  });
  </script>
</body>
</html>