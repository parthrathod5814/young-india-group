<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';

$pdo = db();

function volunteerJsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function volunteerClean(string $value, int $maxLength): string
{
    $value = trim($value);

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function volunteerLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function volunteerValidPhone(string $phone): bool
{
    $digits = preg_replace('/\D/', '', $phone);

    return preg_match('/^\+?[0-9][0-9\s().-]{5,18}[0-9]$/', $phone) === 1
        && strlen($digits) >= 7
        && strlen($digits) <= 15;
}

function volunteerEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$allowedAreas = [
    'Morbi City',
    'Shanala Road',
    'Wankaner',
    'Maliya',
    'Tankara',
    'Halvad',
    'Other',
];

$allowedRoles = [
    'Blood Donor & Mobilizer',
    'Emergency Responder',
    'Education & Mentorship',
    'Medical Camp Support',
    'Tree Plantation & Cleanliness',
    'Disaster Relief Team',
    'Digital & Media Volunteer',
];

/*
 * These values exactly match the pending_volunteers.availability enum.
 */
$allowedAvailability = [
    'Anytime',
    'Weekend',
    'Emergency SOS',
    '4-8 hrs / week',
];

if (empty($_SESSION['volunteer_form_csrf_token'])) {
    $_SESSION['volunteer_form_csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * AJAX form handler.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'register_volunteer') {
    $sessionToken = (string) ($_SESSION['volunteer_form_csrf_token'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Your session expired. Reload the page and try again.',
        ], 419);
    }

    $name = volunteerClean((string) ($_POST['vname'] ?? ''), 100);
    $phone = volunteerClean((string) ($_POST['vphone'] ?? ''), 20);
    $area = (string) ($_POST['varea'] ?? '');
    $role = (string) ($_POST['vrole'] ?? '');
    $availability = (string) ($_POST['availability'] ?? '');
    $consent = (string) ($_POST['vconsent'] ?? '') === '1';

    if (volunteerLength($name) < 2) {
        volunteerJsonResponse(['ok' => false, 'message' => 'Enter your full name.'], 422);
    }

    if (!volunteerValidPhone($phone)) {
        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Enter a valid phone number.',
        ], 422);
    }

    if (!in_array($area, $allowedAreas, true)) {
        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Select a valid area or ward.',
        ], 422);
    }

    if (!in_array($role, $allowedRoles, true)) {
        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Select a valid volunteer role.',
        ], 422);
    }

    if (!in_array($availability, $allowedAvailability, true)) {
        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Select a valid availability option.',
        ], 422);
    }

    if (!$consent) {
        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Please accept the code of conduct.',
        ], 422);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pending_volunteers
                (name, phone, area, role, availability, ip_address)
             VALUES
                (:name, :phone, :area, :role, :availability, :ip_address)'
        );

        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':area' => $area,
            ':role' => $role,
            ':availability' => $availability,
            ':ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);

        $pendingId = (int) $pdo->lastInsertId();

        volunteerJsonResponse([
            'ok' => true,
            'name' => $name,
            'reference' => 'YIG-V-' . date('Y') . '-' . str_pad((string) $pendingId, 4, '0', STR_PAD_LEFT),
            'message' => 'Your volunteer application has been received.',
        ]);
    } catch (Throwable $e) {
        error_log('Volunteer registration failed: ' . $e->getMessage());

        volunteerJsonResponse([
            'ok' => false,
            'message' => 'Could not save your application. Please try again.',
        ], 500);
    }
}

$csrfToken = (string) $_SESSION['volunteer_form_csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Volunteer Network (સ્વયંસેવક નેટવર્ક) | Young India Group – Morbi</title>
  <meta name="description" content="Empowering youth, serving Morbi. 24/7 emergency blood support, healthcare aid and rural education.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script>document.documentElement.classList.add("js");</script>
  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    .volunteer-toast {
      position: fixed;
      right: 24px;
      bottom: 24px;
      z-index: 10000;
      max-width: min(440px, calc(100vw - 32px));
      padding: 14px 18px;
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 12px;
      color: #fff;
      background: #35105e;
      box-shadow: 0 12px 36px rgba(30, 12, 49, .24);
      font: 600 14px/1.5 Inter, sans-serif;
      opacity: 0;
      pointer-events: none;
      transform: translateY(12px);
      transition: opacity .2s ease, transform .2s ease;
    }

    .volunteer-toast.is-show {
      opacity: 1;
      transform: translateY(0);
    }

    .volunteer-toast.is-error {
      background: #a51d2d;
    }

    .volunteer-toast.is-success {
      background: #23734a;
    }

    .volunteer-submit:disabled {
      cursor: wait;
      opacity: .7;
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<main>
  <!-- ===== HERO + STATS ===== -->
  <section class="volunteer-hero">
    <div class="container">
      <span class="volunteer-chip">
        <i class="fa-solid fa-people-group"></i>
        મોરબી સિવિક વાનગાર્ડ &amp; યુવા સ્વયંસેવક નેટવર્ક
      </span>

      <h1>Join Morbi’s Largest Youth Civic Network</h1>
      <p class="volunteer-hero__sub">"યુવા શક્તિ, સેવા ભક્તિ" — Speak. Act. Change.</p>

      <p>
        Empowering Morbi and Saurashtra through organized civic action, immediate life-saving blood mobilization,
        municipal relief logistics, and dedicated youth mentorship under theDeven Rabari Foundation.
      </p>

      <div class="volunteer-stats">
        <div class="volunteer-stat">
          <strong><span data-count="1450" style="color: orange;">1,450</span>+</strong>
          <span>Active Volunteers</span>
        </div>
        <div class="volunteer-stat volunteer-stat--white">
          <strong><span data-count="12800">12,800</span>+</strong>
          <span>Blood Units Mobilized</span>
        </div>
        <div class="volunteer-stat volunteer-stat--green">
          <strong>14 Wards</strong>
          <span>Morbi Coverage</span>
        </div>
        <div class="volunteer-stat volunteer-stat--white">
          <strong>24/7</strong>
          <span>Emergency Response</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== FORMS + OFFICE ===== -->
  <section class="section">
    <div class="container volunteer-layout">

      <!-- Volunteer registration -->
      <div class="volunteer-card volunteer-card--top">
        <div class="volunteer-card__head">
          <h2>
            <i class="fa-solid fa-user-check"></i>
            સેવા જોડાણ ફોર્મ <small>(Volunteer Registration)</small>
          </h2>
          <span class="volunteer-badge">સત્ર ૨૦૨૪-૨૫</span>
        </div>

        <p class="volunteer-card__intro">
          મોરબી નાગરિક સેવા અને કટોકટી રાહત ટુકડીમાં જોડાઓ.
          Join Morbi's active civic response &amp; emergency relief teams.
        </p>

        <form id="volunteerForm">
          <input type="hidden" name="csrf_token" value="<?= volunteerEscape($csrfToken) ?>">
          <input type="hidden" name="form_action" value="register_volunteer">

          <div class="volunteer-row">
            <div class="volunteer-field">
              <label for="vname">Full Name / પૂરું નામ *</label>
              <input
                type="text"
                id="vname"
                name="vname"
                placeholder="e.g. Ramesh Patel / રમેશભાઈ પટેલ"
                minlength="2"
                maxlength="100"
                autocomplete="name"
                required
              >
            </div>

            <div class="volunteer-field">
              <label for="vphone">Phone / WhatsApp / ફોન નંબર *</label>
             <input
                type="tel"
                id="vphone"
                name="vphone"
                placeholder="+91 98250 12345"
                minlength="10"
                maxlength="20"
                inputmode="tel"
                autocomplete="tel"
                required
              >
            </div>
          </div>

          <div class="volunteer-row">
            <div class="volunteer-field">
              <label for="varea">Area / Ward / વિસ્તાર અથવા વોર્ડ *</label>
              <select id="varea" name="varea" required>
                <option value="">Select your area in Morbi / વિસ્તાર પસંદ કરો...</option>
                <option value="Morbi City">Morbi City (મોરબી શહેર)</option>
                <option value="Shanala Road">Shanala Road (શનાળા રોડ)</option>
                <option value="Wankaner">Wankaner (વાંકાનેર)</option>
                <option value="Maliya">Maliya (માળિયા)</option>
                <option value="Tankara">Tankara (ટંકારા)</option>
                <option value="Halvad">Halvad (હળવદ)</option>
                <option value="Other">Other / અન્ય</option>
              </select>
            </div>

            <div class="volunteer-field">
              <label for="vrole">Primary Role / સેવા ક્ષેત્ર *</label>
              <select id="vrole" name="vrole" required>
                <option value="">Select role / ક્ષેત્ર પસંદ કરો...</option>
                <option value="Blood Donor &amp; Mobilizer">Blood Donor &amp; Mobilizer (રક્તદાતા)</option>
                <option value="Emergency Responder">Emergency Responder (ઇમરજન્સી ટીમ)</option>
                <option value="Education &amp; Mentorship">Education &amp; Mentorship (શિક્ષણ સહાય)</option>
                <option value="Medical Camp Support">Medical Camp Support (આરોગ્ય કેમ્પ)</option>
                <option value="Tree Plantation &amp; Cleanliness">Tree Plantation &amp; Cleanliness (વૃક્ષારોપણ)</option>
                <option value="Disaster Relief Team">Disaster Relief Team (આપત્તિ રાહત)</option>
                <option value="Digital &amp; Media Volunteer">Digital &amp; Media Volunteer</option>
              </select>
            </div>
          </div>

          <fieldset class="volunteer-field" style="border: 0;">
            <legend class="volunteer-legend">Availability / સમય અનુકૂળતા *</legend>
            <div class="volunteer-radios">
            <label class="volunteer-radio">
                <input type="radio" name="availability" value="Anytime" required>
                કોય પણ સમયે
              </label>  
            
            <label class="volunteer-radio">
                <input type="radio" name="availability" value="Weekend" required>
                શનિ-રવિ
              </label>
              <label class="volunteer-radio is-checked">
                <input type="radio" name="availability" value="Emergency SOS" checked>
                ઇમરજન્સી SOS
              </label>
              <label class="volunteer-radio">
                <input type="radio" name="availability" value="4-8 hrs / week">
                ૪-૮ કલાક/સપ્તાહ
              </label>
            </div>
          </fieldset>

          <label class="volunteer-check">
            <input type="checkbox" id="vconsent" name="vconsent" value="1" required>
            <span>
              હું યંગ ઇન્ડિયા ગ્રુપની આચારસંહિતા સ્વીકારું છું.
              (I agree to the Foundation's Code of Conduct).
            </span>
          </label>

          <button type="submit" class="btn btn--orange btn--block volunteer-submit" id="volunteerSubmit">
            <i class="fa-regular fa-paper-plane"></i>
            અરજી સબમિટ કરો (Submit Application)
          </button>

          <p class="volunteer-status" id="volunteerStatus" role="status" aria-live="polite"></p>
        </form>

        <div class="volunteer-success" id="volunteerSuccess" hidden>
          <i class="fa-solid fa-circle-check"></i>
          <h3>આભાર, <span id="volunteerName"></span>!</h3>
          <p>Your application has been received. Reference ID: <strong id="volunteerRef"></strong></p>
          <p>A Morbi field coordinator will contact you on WhatsApp within 48 hours.</p>
          <button type="button" class="btn btn--purple" id="volunteerAgain">
            <i class="fa-solid fa-rotate-left"></i> Submit another response
          </button>
        </div>
      </div>

      <!-- Office details -->
      <div class="volunteer-side">
        <div class="volunteer-card">
          <div class="volunteer-office__head">
            <div>
              <i class="fa-solid fa-building"></i>
              <div>
                <h2>મોરબી મુખ્ય કાર્યાલય</h2>
                <small>સિવિલ હોસ્પિટલ રોડ, શનાળા રોડ, મોરબી</small>
              </div>
            </div>
            <span class="volunteer-badge volunteer-badge--green">૨૪/૭ કાર્યરત</span>
          </div>

          <a href="tel:+919825000000" class="volunteer-contact volunteer-contact--sos">
            <i class="fa-solid fa-asterisk"></i>
            <div><small>Blood SOS Dispatch</small><strong>+91 98250 00000</strong></div>
            <i class="fa-solid fa-phone"></i>
          </a>

          <a href="tel:+912822220000" class="volunteer-contact">
            <i class="fa-solid fa-building-columns"></i>
            <div><small>Landline Desk</small><strong>+91 2822 220000</strong></div>
            <i class="fa-solid fa-phone"></i>
          </a>

          <a href="mailto:info@youngindiagroup.org" class="volunteer-contact">
            <i class="fa-regular fa-envelope"></i>
            <div><small>Official Email</small><strong>info@youngindiagroup.org</strong></div>
            <i class="fa-solid fa-arrow-right"></i>
          </a>

          <a href="https://wa.me/919825000000" target="_blank" rel="noopener" class="btn btn--block volunteer-whatsapp">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp Quick Connect
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== RECOGNITION ===== -->
  <section class="section section--bordered">
    <div class="container">
      <div class="volunteer-recog__head">
        <h2>સ્વયંસેવક માન્યતા અને સન્માન પ્રમાણપત્ર</h2>
        <p>માનકીકૃત નાગરિક પ્રમાણપત્રો અને અધિકૃત સેવા બહુમાન (Volunteer Recognition &amp; Accreditations)</p>
      </div>

      <div class="grid grid--3">
        <div class="volunteer-recog">
          <span class="volunteer-recog__icon"><i class="fa-solid fa-certificate"></i></span>
          <div><h3>નેતૃત્વ પ્રમાણપત્ર</h3><p>Official Trust accreditation &amp; CV credits</p></div>
        </div>
        <div class="volunteer-recog">
          <span class="volunteer-recog__icon volunteer-recog__icon--orange"><i class="fa-solid fa-briefcase-medical"></i></span>
          <div><h3>રિસ્પોન્ડર બેજ &amp; ID</h3><p>Frontline blood mobilizer ID pin &amp; card</p></div>
        </div>
        <div class="volunteer-recog">
          <span class="volunteer-recog__icon volunteer-recog__icon--green"><i class="fa-solid fa-award"></i></span>
          <div><h3>વાર્ષિક સેવા ગૌરવ</h3><p>Morbi District annual youth banquet award</p></div>
        </div>
      </div>
    </div>
  </section>
</main>

<div class="volunteer-toast" id="volunteerToast" role="status" aria-live="polite"></div>

<?php include 'footer.php'; ?>

<script src="assets/js/script.js"></script>

<script>
(() => {
  const form = document.getElementById("volunteerForm");
  if (!form) return;

  const status = document.getElementById("volunteerStatus");
  const toast = document.getElementById("volunteerToast");
  const successPanel = document.getElementById("volunteerSuccess");
  const submitButton = document.getElementById("volunteerSubmit");
  let toastTimer;

  function showToast(message, type) {
    if (!toast) return;

    toast.textContent = message;
    toast.className = "volunteer-toast is-show " + (type || "");

    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
      toast.classList.remove("is-show", "is-error", "is-success");
    }, 4000);
  }

  function showStatus(message, type) {
    if (!status) return;
    status.textContent = message;
    status.className = "volunteer-status" + (type ? " " + type : "");
  }

  /*
   * Capture the submit event before the existing script.js listener.
   * This prevents the demo-only fakeSend handler from intercepting the form.
   */
  document.addEventListener("submit", async event => {
    if (event.target !== form) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    if (!form.reportValidity()) return;

    const phone = form.elements.namedItem("vphone");
    const digits = phone.value.replace(/\D/g, "");
    if (digits.length < 7 || digits.length > 15) {
      phone.setCustomValidity("Enter a valid phone number.");
      phone.reportValidity();
      phone.setCustomValidity("");
      return;
    }

    const data = new FormData(form);
    const originalButton = submitButton.innerHTML;

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    showStatus("", "");

    try {
      const response = await fetch(window.location.href, {
        method: "POST",
        body: data,
        credentials: "same-origin",
        cache: "no-store",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      let result;
      try {
        result = await response.json();
      } catch {
        throw new Error("The server returned an invalid response. Please try again.");
      }

      if (!response.ok || !result.ok) {
        throw new Error(result.message || "Could not save your application.");
      }

      document.getElementById("volunteerName").textContent = result.name;
      document.getElementById("volunteerRef").textContent = result.reference;
      form.hidden = true;
      successPanel.hidden = false;

      showStatus(result.message, "is-success");
      showToast(result.message, "is-success");
    } catch (error) {
      showStatus(error.message, "is-error");
      showToast(error.message, "is-error");
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = originalButton;
    }
  }, true);

  /*
   * Keep the existing “Submit another response” experience.
   * Capture phase prevents the demo handler from also processing this click.
   */
  document.addEventListener("click", event => {
    const again = event.target.closest("#volunteerAgain");
    if (!again) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    form.reset();
    form.hidden = false;
    successPanel.hidden = true;
    showStatus("", "");

    document.querySelectorAll(".volunteer-radio").forEach(card => {
      const radio = card.querySelector('input[type="radio"]');
      card.classList.toggle("is-checked", Boolean(radio?.checked));
    });
  }, true);

  document.querySelectorAll(".volunteer-radio input").forEach(radio => {
    radio.addEventListener("change", () => {
      document.querySelectorAll(".volunteer-radio").forEach(card => {
        card.classList.toggle(
          "is-checked",
          Boolean(card.querySelector('input[type="radio"]')?.checked)
        );
      });
    });
  });
})();
</script>
</body>
</html>