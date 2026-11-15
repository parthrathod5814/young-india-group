<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
$pdo = db();

function bloodHubJson(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function bloodHubEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function bloodHubClean(string $value, int $length): string
{
    $value = trim($value);

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $length, 'UTF-8')
        : substr($value, 0, $length);
}

function bloodHubLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function bloodHubValidPhone(string $phone): bool
{
    $digits = preg_replace('/\D/', '', $phone);

    return preg_match('/^\+?[0-9][0-9\s().-]{5,18}[0-9]$/', $phone) === 1
        && strlen($digits) >= 7
        && strlen($digits) <= 15;
}

function bloodHubCompatible(string $donor, string $recipient): bool
{
    $donorAbo = preg_replace('/[+-]/', '', $donor);
    $recipientAbo = preg_replace('/[+-]/', '', $recipient);

    $aboOk = $donorAbo === 'O'
        || $donorAbo === $recipientAbo
        || $recipientAbo === 'AB';

    $rhOk = str_ends_with($donor, '-') || str_ends_with($recipient, '+');

    return $aboOk && $rhOk;
}

function bloodHubCardStatus(string $status): array
{
    return match ($status) {
        'In-Transit' => ['enroute', 'DONOR EN ROUTE', 'fa-motorcycle'],
        'Fulfilled' => ['fulfilled', 'FULFILLED', 'fa-circle-check'],
        default => ['active', 'ACTIVE SOS', 'fa-heart-pulse'],
    };
}

/**
 * Add a notification using the supplied notifications table columns.
 * Called inside the same transaction as the donor/interest insert.
 */
function bloodHubInsertNotification(
    PDO $pdo,
    string $itemKey,
    string $type,
    string $title,
    string $message,
    string $entity,
    int $entityId
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO notifications
            (item_key, type, title, message, entity, entity_id)
         VALUES
            (:item_key, :type, :title, :message, :entity, :entity_id)'
    );

    $stmt->execute([
        ':item_key' => $itemKey,
        ':type' => $type,
        ':title' => $title,
        ':message' => $message,
        ':entity' => $entity,
        ':entity_id' => $entityId,
    ]);
}

$bloodGroups = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
$bloodAreas = [
    'Morbi City',
    'Shanala Road',
    'Wankaner',
    'Maliya',
    'Tankara',
    'Halvad',
    'Ravapar',
    'Other',
];

if (empty($_SESSION['bloodhub_csrf_token'])) {
    $_SESSION['bloodhub_csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * Return emergency requests in pages of nine.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'stream') {
    try {
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
        $limit = filter_var($_GET['limit'] ?? 9, FILTER_VALIDATE_INT);

        $offset = $offset !== false && $offset >= 0 ? $offset : 0;
        $limit = $limit !== false ? max(1, min($limit, 27)) : 9;

        $total = (int) $pdo->query(
            "SELECT COUNT(*)
             FROM blood_requests
             WHERE status IN ('Active','In-Transit','Fulfilled')"
        )->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT id, patient, blood_group, patient_condition, units,
                    hospital_address, status, created_at
             FROM blood_requests
             WHERE status IN ('Active','In-Transit','Fulfilled')
             ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        bloodHubJson([
            'ok' => true,
            'requests' => $requests,
            'total' => $total,
            'has_more' => $offset + count($requests) < $total,
        ]);
    } catch (Throwable $e) {
        error_log('Blood hub stream error: ' . $e->getMessage());
        bloodHubJson([
            'ok' => false,
            'message' => 'Could not load requests.',
        ], 500);
    }
}

/*
 * Process AJAX forms.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bloodhub_action'])) {
    $sessionToken = (string) ($_SESSION['bloodhub_csrf_token'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        bloodHubJson([
            'ok' => false,
            'message' => 'Your session expired. Reload the page and try again.',
        ], 419);
    }

    $action = (string) $_POST['bloodhub_action'];
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    try {
        /*
         * Existing SOS request behavior is retained.
         */
        if ($action === 'submit_sos') {
            $patient = bloodHubClean((string) ($_POST['patient'] ?? ''), 120);
            $group = (string) ($_POST['blood_group'] ?? '');
            $condition = (string) ($_POST['patient_condition'] ?? '');
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            $hospital = bloodHubClean((string) ($_POST['hospital_address'] ?? ''), 255);
            $contact = bloodHubClean((string) ($_POST['contact'] ?? ''), 20);

            if (bloodHubLength($patient) < 2) {
                bloodHubJson(['ok' => false, 'message' => 'Enter the patient name.'], 422);
            }

            if (!in_array($group, $bloodGroups, true)) {
                bloodHubJson(['ok' => false, 'message' => 'Select a valid blood group.'], 422);
            }

            if (!in_array($condition, ['Emergency', 'Normal'], true)) {
                bloodHubJson(['ok' => false, 'message' => 'Select a patient condition.'], 422);
            }

            if ($units === false || $units < 1 || $units > 4) {
                bloodHubJson(['ok' => false, 'message' => 'Select 1–4 units.'], 422);
            }

            if ($hospital === '') {
                bloodHubJson(['ok' => false, 'message' => 'Enter a hospital name and address.'], 422);
            }

            if (!bloodHubValidPhone($contact)) {
                bloodHubJson(['ok' => false, 'message' => 'Enter a valid contact number.'], 422);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO blood_requests
                    (patient, blood_group, patient_condition, units,
                     hospital_address, contact, status)
                 VALUES
                    (:patient, :group_name, :patient_condition, :units,
                     :hospital, :contact, 'Active')"
            );
            $stmt->execute([
                ':patient' => $patient,
                ':group_name' => $group,
                ':patient_condition' => $condition,
                ':units' => $units,
                ':hospital' => $hospital,
                ':contact' => $contact,
            ]);

            $requestId = (int) $pdo->lastInsertId();

            /*
             * Keep the existing SOS inquiry record.
             * Donor-registration and donation-interest submissions below
             * do not insert into inquiries.
             */
            $inquiry = $pdo->prepare(
                "INSERT INTO inquiries
                    (name, phone, type, status, message, ip_address)
                 VALUES
                    (:name, :phone, 'Blood Request', 'New', :message, :ip)"
            );
            $inquiry->execute([
                ':name' => $patient,
                ':phone' => $contact,
                ':message' => sprintf(
                    'Blood request #%d: %s, %s, %d unit(s), %s.',
                    $requestId,
                    $group,
                    $condition,
                    $units,
                    $hospital
                ),
                ':ip' => $ip,
            ]);

            $pdo->commit();

            bloodHubJson([
                'ok' => true,
                'message' => 'Your blood request was sent to the coordination team.',
            ]);
        }

        /*
         * Regular donor registration:
         * Save only to pending_donors, then create its admin notification.
         */
        if ($action === 'register_donor') {
            $name = bloodHubClean((string) ($_POST['fullname'] ?? ''), 100);
            $group = (string) ($_POST['group'] ?? '');
            $phone = bloodHubClean((string) ($_POST['whatsapp'] ?? ''), 20);
            $age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT);
            $area = (string) ($_POST['area'] ?? '');
            $consent = (string) ($_POST['consent'] ?? '') === '1';

            if (bloodHubLength($name) < 2) {
                bloodHubJson(['ok' => false, 'message' => 'Enter your full name.'], 422);
            }

            if (!in_array($group, $bloodGroups, true)) {
                bloodHubJson(['ok' => false, 'message' => 'Select a valid blood group.'], 422);
            }

            if (!bloodHubValidPhone($phone)) {
                bloodHubJson(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
            }

            if ($age === false || $age < 18 || $age > 60) {
                bloodHubJson(['ok' => false, 'message' => 'Age must be from 18 to 60.'], 422);
            }

            if (!in_array($area, $bloodAreas, true)) {
                bloodHubJson(['ok' => false, 'message' => 'Select a valid area or taluka.'], 422);
            }

            if (!$consent) {
                bloodHubJson(['ok' => false, 'message' => 'Please confirm the donor consent.'], 422);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO pending_donors
                    (name, blood_group, phone, age, area, ip_address)
                 VALUES
                    (:name, :group_name, :phone, :age, :area, :ip_address)'
            );
            $stmt->execute([
                ':name' => $name,
                ':group_name' => $group,
                ':phone' => $phone,
                ':age' => $age,
                ':area' => $area,
                ':ip_address' => $ip,
            ]);

            $pendingDonorId = (int) $pdo->lastInsertId();

            bloodHubInsertNotification(
                $pdo,
                'pending_donor_' . $pendingDonorId,
                'donor_registration',
                'New donor registration',
                sprintf(
                    '%s registered as a donor. Blood group: %s; area: %s.',
                    $name,
                    $group,
                    $area
                ),
                'pending_donor',
                $pendingDonorId
            );

            $pdo->commit();

            bloodHubJson([
                'ok' => true,
                'message' => 'Registration received and sent to the admin team for approval.',
            ]);
        }

        /*
         * Request-card popup:
         * Save donor details and selected request to donation_interests.
         * Link to an existing donor or pending donor when one matches.
         * Do not create another pending_donors row and do not insert an inquiry.
         */
        if ($action === 'register_interest') {
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $name = bloodHubClean((string) ($_POST['fullname'] ?? ''), 100);
            $group = (string) ($_POST['group'] ?? '');
            $phone = bloodHubClean((string) ($_POST['whatsapp'] ?? ''), 20);
            $age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT);
            $area = (string) ($_POST['area'] ?? '');
            $consent = (string) ($_POST['consent'] ?? '') === '1';

            if ($requestId === false || $requestId === null || $requestId < 1) {
                bloodHubJson(['ok' => false, 'message' => 'Invalid emergency request.'], 422);
            }

            if (bloodHubLength($name) < 2) {
                bloodHubJson(['ok' => false, 'message' => 'Enter your full name.'], 422);
            }

            if (!in_array($group, $bloodGroups, true)) {
                bloodHubJson(['ok' => false, 'message' => 'Select a valid blood group.'], 422);
            }

            if (!bloodHubValidPhone($phone)) {
                bloodHubJson(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
            }

            if ($age === false || $age < 18 || $age > 60) {
                bloodHubJson(['ok' => false, 'message' => 'Age must be from 18 to 60.'], 422);
            }

            if (!in_array($area, $bloodAreas, true)) {
                bloodHubJson(['ok' => false, 'message' => 'Select a valid area or taluka.'], 422);
            }

            if (!$consent) {
                bloodHubJson(['ok' => false, 'message' => 'Please confirm the consent.'], 422);
            }

            $pdo->beginTransaction();

            $requestStmt = $pdo->prepare(
                "SELECT id, blood_group, status
                 FROM blood_requests
                 WHERE id = :id AND status IN ('Active','In-Transit')
                 FOR UPDATE"
            );
            $requestStmt->execute([':id' => $requestId]);
            $bloodRequest = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$bloodRequest) {
                $pdo->rollBack();
                bloodHubJson([
                    'ok' => false,
                    'message' => 'This request is no longer accepting donor interest.',
                ], 404);
            }

            if (!bloodHubCompatible($group, (string) $bloodRequest['blood_group'])) {
                $pdo->rollBack();
                bloodHubJson([
                    'ok' => false,
                    'message' => 'This blood group is not compatible with the request.',
                ], 422);
            }

            $donorId = null;
            $pendingDonorId = null;

            $donorStmt = $pdo->prepare(
                "SELECT id
                 FROM donors
                 WHERE phone = :phone
                   AND blood_group = :group_name
                   AND status = 'Active'
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $donorStmt->execute([
                ':phone' => $phone,
                ':group_name' => $group,
            ]);
            $existingDonorId = $donorStmt->fetchColumn();

            if ($existingDonorId !== false) {
                $donorId = (int) $existingDonorId;
            } else {
                $pendingStmt = $pdo->prepare(
                    "SELECT id
                     FROM pending_donors
                     WHERE phone = :phone
                       AND blood_group = :group_name
                     ORDER BY id DESC
                     LIMIT 1"
                );
                $pendingStmt->execute([
                    ':phone' => $phone,
                    ':group_name' => $group,
                ]);
                $existingPendingDonorId = $pendingStmt->fetchColumn();

                if ($existingPendingDonorId !== false) {
                    $pendingDonorId = (int) $existingPendingDonorId;
                }
            }

            $interestStmt = $pdo->prepare(
                "INSERT INTO donation_interests
                    (blood_request_id, donor_id, pending_donor_id,
                     status, donor_name, blood_group, phone, age, area)
                 VALUES
                    (:request_id, :donor_id, :pending_donor_id,
                     'Pending', :donor_name, :group_name, :phone, :age, :area)"
            );
            $interestStmt->execute([
                ':request_id' => $requestId,
                ':donor_id' => $donorId,
                ':pending_donor_id' => $pendingDonorId,
                ':donor_name' => $name,
                ':group_name' => $group,
                ':phone' => $phone,
                ':age' => $age,
                ':area' => $area,
            ]);

            $interestId = (int) $pdo->lastInsertId();

            bloodHubInsertNotification(
                $pdo,
                'donation_interest_' . $interestId,
                'donation_interest',
                'New donation interest',
                sprintf(
                    '%s is interested in blood request #%d. Blood group: %s; area: %s.',
                    $name,
                    $requestId,
                    $group,
                    $area
                ),
                'donation_interest',
                $interestId
            );

            $pdo->commit();

            bloodHubJson([
                'ok' => true,
                'message' => 'Your donation interest was sent to the admin team.',
            ]);
        }

        bloodHubJson([
            'ok' => false,
            'message' => 'Unknown form submission.',
        ], 400);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Blood hub submission failed: ' . $e->getMessage());

        bloodHubJson([
            'ok' => false,
            'message' => 'Could not save your submission. Please try again.',
        ], 500);
    }
}

/*
 * Initial page data: stats and first nine requests.
 */
$stats = [
    'donors' => 0,
    'fulfilled' => 0,
    'active' => 0,
    'pending' => 0,
];

$requests = [];
$totalRequests = 0;

try {
    $stats['donors'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM donors WHERE status = 'Active'"
    )->fetchColumn();

    $stats['fulfilled'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM blood_requests WHERE status = 'Fulfilled'"
    )->fetchColumn();

    $stats['active'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM blood_requests WHERE status IN ('Active','In-Transit')"
    )->fetchColumn();

    $stats['pending'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM pending_donors'
    )->fetchColumn();

    $totalRequests = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM blood_requests
         WHERE status IN ('Active','In-Transit','Fulfilled')"
    )->fetchColumn();

    $stmt = $pdo->query(
        "SELECT id, patient, blood_group, patient_condition, units,
                hospital_address, status, created_at
         FROM blood_requests
         WHERE status IN ('Active','In-Transit','Fulfilled')
         ORDER BY created_at DESC, id DESC
         LIMIT 9"
    );

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Blood hub page load failed: ' . $e->getMessage());
}

$csrf = (string) $_SESSION['bloodhub_csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blood Emergency Hub | Young India Group – Morbi</title>
  <meta name="description" content="24/7 emergency blood support and donor assistance in Morbi.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script>document.documentElement.classList.add("js");</script>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .blood-more-wrap { display:flex; justify-content:center; margin-top:24px; }
    .blood-more[hidden], .blood-donor-modal[hidden] { display:none !important; }

    .blood-donor-modal {
      position:fixed;
      inset:0;
      z-index:9999;
      display:flex;
      justify-content:center;
      align-items:center;
      padding:20px;
      background:rgba(31, 16, 48, .68);
      backdrop-filter:blur(4px);
    }

    .blood-donor-modal__panel {
      position:relative;
      width:min(680px, 100%);
      max-height:min(88vh, 850px);
      overflow:auto;
      overscroll-behavior:contain;
      scrollbar-width:thin;
      scrollbar-color:#8a6aa8 #f1eaf7;
      padding:30px;
      border:1px solid #e8d9f3;
      border-radius:20px;
      background:#fff;
      box-shadow:0 24px 70px rgba(31, 16, 48, .28);
    }

    .blood-donor-modal__panel::-webkit-scrollbar { width:10px; }
    .blood-donor-modal__panel::-webkit-scrollbar-track {
      margin:14px 0;
      border-radius:10px;
      background:#f1eaf7;
    }
    .blood-donor-modal__panel::-webkit-scrollbar-thumb {
      border:2px solid #f1eaf7;
      border-radius:10px;
      background:#8a6aa8;
    }

    .blood-donor-modal__close {
      position:absolute;
      top:16px;
      right:16px;
      display:grid;
      width:40px;
      height:40px;
      place-items:center;
      border:1px solid #eadcf3;
      border-radius:50%;
      color:#35105e;
      background:#f8f3fc;
      font-size:20px;
      line-height:1;
      cursor:pointer;
      transition:background .2s, color .2s, transform .2s;
    }
    .blood-donor-modal__close:hover {
      color:#fff;
      background:#35105e;
      transform:rotate(90deg);
    }

    .blood-donor-modal__heading { padding-right:42px; margin-bottom:18px; }
    .blood-donor-modal__heading h2 { margin:5px 0; color:#35105e; }
    .blood-donor-modal__request {
      margin:12px 0 18px;
      padding:14px 16px;
      border:1px solid #eadcf3;
      border-radius:12px;
      color:#35105e;
      background:#f7f2fb;
      overflow-wrap:anywhere;
    }

    .blood-donor-modal .blood-row {
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:14px;
    }

    .blood-donor-modal__panel .blood-form {
      padding:0;
      border:0;
      box-shadow:none;
    }

    .blood-donor-modal__panel .blood-check { margin:14px 0; }

    @media (max-width:600px) {
      .blood-donor-modal { padding:10px; align-items:flex-start; }
      .blood-donor-modal__panel {
        max-height:calc(100vh - 20px);
        padding:22px 18px;
        border-radius:16px;
      }
      .blood-donor-modal .blood-row { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <div class="blood-ticker">
    <span class="blood-ticker__label">
      <i class="fa-solid fa-triangle-exclamation"></i><span>CRITICAL DEMAND NOW</span>
    </span>
    <div class="blood-ticker__window"><div class="blood-ticker__track" id="tickerTrack">Loading...</div></div>
    <span class="blood-ticker__resp">DISPATCH RESPONSE: <strong>Morbi coordination desk</strong></span>
  </div>

  <section class="blood-hero">
    <div class="container">
      <span class="blood-chip"><i class="fa-solid fa-droplet"></i> ૨૪×૭ તાત્કાલિક રક્ત સહાય કેન્દ્ર • Morbi</span>
      <h1>મોરબી ઇમરજન્સી રક્ત કેન્દ્ર &amp; <span>જીવનદાતા નેટવર્ક (Blood Emergency Hub)</span></h1>
      <p>યંગ ઇન્ડિયા ગ્રુપ દ્વારા મોરબીમાં ઇમરજન્સી રક્ત સહાય અને દાતા સંકલન.</p>

      <div class="grid grid--4 blood-stats">
        <div class="blood-stat"><div><small>Active Donors</small><strong><span data-count="<?= $stats['donors'] ?>"><?= number_format($stats['donors']) ?></span></strong><span>Approved donors</span></div><span class="blood-stat__icon"><i class="fa-solid fa-users"></i></span></div>
        <div class="blood-stat"><div><small>Fulfilled Requests</small><strong><span data-count="<?= $stats['fulfilled'] ?>"><?= number_format($stats['fulfilled']) ?></span></strong><span>Completed requests</span></div><span class="blood-stat__icon blood-stat__icon--orange"><i class="fa-solid fa-heart"></i></span></div>
        <div class="blood-stat"><div><small>Active Requests</small><strong><span data-count="<?= $stats['active'] ?>"><?= number_format($stats['active']) ?></span></strong><span>Awaiting assistance</span></div><span class="blood-stat__icon blood-stat__icon--green"><i class="fa-solid fa-motorcycle"></i></span></div>
        <div class="blood-stat"><div><small>Pending Donors</small><strong><span data-count="<?= $stats['pending'] ?>"><?= number_format($stats['pending']) ?></span></strong><span>Awaiting approval</span></div><span class="blood-stat__icon blood-stat__icon--red"><i class="fa-solid fa-user-clock"></i></span></div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container blood-forms">
      <form class="blood-form" id="sosForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= bloodHubEscape($csrf) ?>">
        <div class="blood-form__head">
          <div><span class="eyebrow">ઇમરજન્સી જરૂરિયાત • IMMEDIATE SOS</span><h2>તાત્કાલિક રક્ત વિનંતી (Request Blood SOS)</h2></div>
          <i class="fa-solid fa-truck-medical"></i>
        </div>

        <div class="blood-field">
          <label for="patient">દર્દીનું નામ (Patient Name) *</label>
          <input
            type="text"
            id="patient"
            name="patient"
            minlength="2"
            maxlength="120"
            autocomplete="name"
            required
          >
        </div>

        <div class="blood-field">
          <label>જરૂરી બ્લડ ગ્રુપ (Required Blood Group) *</label>
          <div class="blood-groups" id="groupPicker"></div>
        </div>

        <div class="blood-row">
          <div class="blood-field">
            <label for="patient_condition">દર્દીની સ્થિતિ (Patient Condition) *</label>
              <select id="patient_condition" name="patient_condition" required>
                <option value="">Select patient condition...</option>
                <option value="Emergency">Emergency</option>
                <option value="Normal">Normal</option>
              </select>
          </div>
          <div class="blood-field">
            <label for="units">જરૂરી યુનિટ (Units Needed) *</label>
              <select id="units" name="units" required>
                <option value="">Select units...</option>
                <option value="1">1 Unit</option>
                <option value="2">2 Units</option>
                <option value="3">3 Units</option>
                <option value="4">4 Units</option>
              </select>
          </div>
        </div>

        <div class="blood-field">
          <label for="hospital">હોસ્પિટલનું નામ (Hospital Name / Address) *</label>
          <select id="hospital" name="hospital_choice" required>
            <option value="">Select hospital...</option>
            <option value="Morbi Civil Hospital">Morbi Civil Hospital</option>
            <option value="Krishna Hospital">Krishna Hospital</option>
            <option value="Ayush Hospital">Ayush Hospital</option>
            <option value="Other">Other hospital</option>
          </select>

          <input
            type="text"
            id="hospitalOther"
            minlength="3"
            maxlength="255"
            placeholder="Enter hospital name and address"
            required
            hidden
          >
        </div>

        <div class="blood-field">
          <label for="attendant">સગા/સહાયક મોબાઇલ નંબર (Attendant Contact) *</label>
          <input
            type="tel"
            id="attendant"
            name="contact"
            minlength="10"
            maxlength="12"
            pattern="(\+91)?[\s-]?[6-9][0-9]{4}[\s-]?[0-9]{5}"
            title="Enter a valid Indian mobile number, with or without +91."
            autocomplete="tel"
            required
          >        </div>

        <button type="submit" class="btn btn--orange btn--block blood-btn-sos">
          <i class="fa-solid fa-tower-broadcast"></i> રક્ત વિનંતી મોકલો (Broadcast Blood SOS)
        </button>
        <p class="blood-form__status" id="sosStatus" role="status"></p>
      </form>

      <!-- Keep this donor registration form: it submits to pending_donors. -->
      <form class="blood-form" id="donorForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= bloodHubEscape($csrf) ?>">
        <div class="blood-form__head">
          <div><span class="eyebrow">યુવા શક્તિ • YOUTH VANGUARD</span><h2>રક્તદાતા તરીકે નોંધણી (Register as Donor)</h2></div>
          <i class="fa-solid fa-hand-holding-heart"></i>
        </div>

        <div class="blood-field">
          <label for="fullname">પૂરું નામ (Full Name) *</label>
          <input
            type="text"
            id="fullname"
            name="fullname"
            minlength="2"
            maxlength="100"
            autocomplete="name"
            required
          >
        </div>

        <div class="blood-row">
          <div class="blood-field">
            <label for="group">બ્લડ ગ્રુપ (Blood Group) *</label>
            <select id="group" name="group" data-blood required>
  <option value="">Select blood group...</option>
</select>
          </div>
          <div class="blood-field">
            <label for="age">ઉંમર / Age (18-60) *</label>
          <input
            type="number"
            id="age"
            name="age"
            min="18"
            max="60"
            step="1"
            required
          >
          </div>
        </div>

        <div class="blood-row">
          <div class="blood-field">
            <label for="whatsapp">વોટ્સએપ નંબર (WhatsApp No) *</label>
            <input
              type="tel"
              id="whatsapp"
              name="whatsapp"
              minlength="10"
              maxlength="12"
              pattern="(\+91)?[\s-]?[6-9][0-9]{4}[\s-]?[0-9]{5}"
              title="Enter a valid Indian mobile number, with or without +91."
              autocomplete="tel"
              required
            >
          </div>
          <div class="blood-field">
            <label for="area">વિસ્તાર / તાલુકો (Area / Taluka) *</label>
            <select id="area" name="area" required>
              <option value="">Select area / taluka...</option>
              <?php foreach ($bloodAreas as $area): ?>
                <option value="<?= bloodHubEscape($area) ?>"><?= bloodHubEscape($area) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <label class="blood-check">
          <input type="checkbox" id="consent" name="consent" value="1" required>
          <span>હું પુષ્ટિ કરું છું કે મારું વજન &gt;50kg છે અને ઇમરજન્સી સમયે સૂચના મેળવવા સંમત છું.</span>
        </label>

        <button type="submit" class="btn btn--purple btn--block">
          <i class="fa-solid fa-user-plus"></i> રક્તદાતા બનો (Join Donor Roster)
        </button>
        <p class="blood-form__status" id="donorStatus" role="status"></p>
      </form>
    </div>
  </section>

  <section class="section section--bordered">
    <div class="container">
      <div class="blood-stream__head">
        <div><span class="eyebrow live-dot">LIVE CITIZEN DISPATCH STREAM</span><h2>Ongoing Morbi Emergency Requests</h2></div>
        <div class="blood-controls">
          <button type="button" class="blood-toggle" id="autoToggle" aria-pressed="true"><i class="fa-solid fa-rotate"></i> <span id="toggleText">Auto-Refresh (Every 30s)</span></button>
          <button type="button" class="blood-refresh" id="refreshNow"><i class="fa-solid fa-arrows-rotate"></i> Refresh now</button>
          <span id="countdown">Next refresh in 30s</span><span id="lastUpdated"></span><strong id="activeCount"><?= $stats['active'] ?> Active In Morbi</strong>
        </div>
      </div>

      <div class="grid grid--3" id="streamList"
           data-initial-count="<?= count($requests) ?>"
           data-total="<?= $totalRequests ?>">
        <?php if (!$requests): ?>
          <p class="blood-empty">No emergency requests right now.</p>
        <?php else: ?>
          <?php foreach ($requests as $request): ?>
            <?php [$cssStatus, $label, $icon] = bloodHubCardStatus((string) $request['status']); ?>
            <article class="blood-card blood-card--<?= bloodHubEscape($cssStatus) ?>" data-request-id="<?= (int) $request['id'] ?>">
              <div class="blood-card__top">
                <span class="blood-status"><i class="fa-solid <?= bloodHubEscape($icon) ?>"></i> <?= bloodHubEscape($label) ?></span>
                <span><?= bloodHubEscape($request['created_at']) ?></span>
              </div>
              <div class="blood-card__main">
                <div>
                  <h3><?= bloodHubEscape($request['patient']) ?></h3>
                  <p class="blood-card__where"><?= bloodHubEscape($request['hospital_address']) ?></p>
                </div>
                <span class="blood-type"><?= bloodHubEscape($request['blood_group']) ?></span>
              </div>
              <div class="blood-card__details">
                <p><span>Patient Condition:</span><span><?= bloodHubEscape($request['patient_condition']) ?></span></p>
                <p><span>Units Needed:</span><span><?= (int) $request['units'] ?></span></p>
                <p><span>Request Status:</span><span><?= bloodHubEscape($request['status']) ?></span></p>
              </div>
              <?php if (in_array($request['status'], ['Active', 'In-Transit'], true)): ?>
                <div class="blood-card__foot">
                  <button type="button" class="blood-dispatch" data-become-donor
                          data-request-id="<?= (int) $request['id'] ?>"
                          data-request-label="<?= bloodHubEscape($request['patient'] . ' — ' . $request['blood_group'] . ' — ' . $request['hospital_address']) ?>">
                    <i class="fa-solid fa-hand-holding-heart"></i> Become a Donor
                  </button>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="blood-more-wrap">
        <button type="button" class="btn btn--purple blood-more" id="seeMoreRequests"
          <?= $totalRequests <= count($requests) ? 'hidden' : '' ?>>See More Requests</button>
      </div>
      <p class="blood-form__status" id="streamStatus" role="status"></p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="blood-matrix">
        <div class="blood-matrix__head">
          <div><span class="eyebrow">CLINICAL GUIDANCE REFERENCE</span><h2>Blood Group Compatibility Matrix</h2><p>Check blood group compatibility.</p></div>
        </div>
        <div class="blood-checker">
          <div class="blood-field"><label for="checkPatient">Patient blood group</label><select id="checkPatient" data-blood></select></div>
          <div class="blood-field"><label for="checkDonor">Donor blood group</label><select id="checkDonor" data-blood></select></div>
          <button type="button" class="btn btn--purple" id="checkBtn">Check</button>
        </div>
        <div class="blood-result" id="checkResult" role="status"></div>
        <div class="blood-table-wrap"><table class="blood-table" id="matrixTable"></table></div>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="container blood-notes">
      <div class="blood-note"><i class="fa-solid fa-circle-check"></i><div><strong>100% Voluntary &amp; Free</strong><p>Purely voluntary relief service.</p></div></div>
      <div class="blood-note"><i class="fa-solid fa-bolt"></i><div><strong>Rapid Dispatch Process</strong><p>Requests are sent to the admin coordination team.</p></div></div>
    </div>
  </section>
</main>

<!-- Request-specific donor interest modal -->
<div class="blood-donor-modal" id="donorModal" hidden>
  <div class="blood-donor-modal__panel" role="dialog" aria-modal="true" aria-labelledby="donorModalTitle">
    <button type="button" class="blood-donor-modal__close" id="closeDonorModal" aria-label="Close dialog">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>

    <div class="blood-donor-modal__heading">
      <span class="eyebrow">DONOR INTEREST</span>
      <h2 id="donorModalTitle">Become a Donor</h2>
      <p>Submit your interest for this specific request.</p>
    </div>
    <p class="blood-donor-modal__request" id="selectedRequestLabel"></p>

    <form class="blood-form" id="donorInterestForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= bloodHubEscape($csrf) ?>">
      <input type="hidden" name="request_id" id="interestRequestId">

      <div class="blood-field"><label for="interestName">Full Name *</label><input id="interestName" name="fullname" maxlength="100" required></div>
      <div class="blood-row">
        <div class="blood-field">
          <label for="interestGroup">Blood Group *</label>
          <select id="interestGroup" name="group" required>
            <option value="">Select...</option>
            <?php foreach ($bloodGroups as $group): ?><option value="<?= bloodHubEscape($group) ?>"><?= bloodHubEscape($group) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="blood-field"><label for="interestAge">Age (18–60) *</label><input type="number" id="interestAge" name="age" min="18" max="60" required></div>
      </div>
      <div class="blood-field"><label for="interestPhone">Phone / WhatsApp *</label><input type="tel" id="interestPhone" name="whatsapp" minlength="10" maxlength="12" required></div>
      <div class="blood-field">
        <label for="interestArea">Area / Taluka *</label>
        <select id="interestArea" name="area" required>
          <option value="">Select area / taluka...</option>
          <?php foreach ($bloodAreas as $area): ?><option value="<?= bloodHubEscape($area) ?>"><?= bloodHubEscape($area) ?></option><?php endforeach; ?>
        </select>
      </div>
      <label class="blood-check">
        <input type="checkbox" name="consent" value="1" required>
        <span>I agree to be contacted about this request and confirm the donor eligibility details.</span>
      </label>
      <button type="submit" class="btn btn--purple btn--block">
        <i class="fa-solid fa-user-plus"></i> Submit Donor Interest
      </button>
      <p class="blood-form__status" id="donorInterestStatus" role="status"></p>
    </form>
  </div>
</div>

<div class="blood-toast" id="toast" role="status" aria-live="polite"></div>

<?php include 'footer.php'; ?>
<script src="assets/js/script.js"></script>
<script src="assets/js/bloodhub.js"></script>
</body>
</html>