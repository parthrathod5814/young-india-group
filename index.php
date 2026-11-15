<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
$pdo = db();

function homeEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function homeJsonResponse(array $data, int $statusCode = 200): void
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

function homeTextLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function homeActivityCategoryGujarati(string $category): string
{
    return match ($category) {
        'Event' => 'કાર્યક્રમ',
        'Donation & Charity' => 'દાન અને સેવાકાર્ય',
        'Blood Drive' => 'રક્તદાન શિબિર',
        'Medical Camp' => 'આરોગ્ય શિબિર',
        'Education' => 'શિક્ષણ',
        'Environment' => 'પર્યાવરણ',
        'Awareness' => 'જાગૃતિ',
        default => 'સમુદાય સેવા',
    };
}

if (empty($_SESSION['home_inquiry_csrf_token'])) {
    $_SESSION['home_inquiry_csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * AJAX handler for the home-page inquiry form.
 * The form must send form_action=submit_inquiry.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form_action'] ?? '') === 'submit_inquiry'
) {
    $sessionToken = (string) ($_SESSION['home_inquiry_csrf_token'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        homeJsonResponse([
            'ok' => false,
            'message' => 'Your session expired. Reload the page and try again.',
        ], 419);
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $type = trim((string) ($_POST['type'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $allowedTypes = [
        'Blood Donor Verification',
        'Urgent Blood Request',
        'Volunteer Registration',
        'Welfare / Scholarship Help',
        'Other',
    ];

    $digits = preg_replace('/\D/', '', $phone);

    if (homeTextLength($name) < 2 || homeTextLength($name) > 100) {
        homeJsonResponse([
            'ok' => false,
            'message' => 'Please enter your name (2–100 characters).',
        ], 422);
    }

    if (
        strlen($phone) > 20
        || preg_match('/^\+?[0-9][0-9\s().-]{5,18}[0-9]$/', $phone) !== 1
        || strlen($digits) < 7
        || strlen($digits) > 15
    ) {
        homeJsonResponse([
            'ok' => false,
            'message' => 'Please enter a valid phone number.',
        ], 422);
    }

    if (!in_array($type, $allowedTypes, true)) {
        homeJsonResponse([
            'ok' => false,
            'message' => 'Please select a valid inquiry type.',
        ], 422);
    }

    if (homeTextLength($message) > 5000) {
        homeJsonResponse([
            'ok' => false,
            'message' => 'The message must be 5000 characters or fewer.',
        ], 422);
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO inquiries
                (name, phone, type, status, message, ip_address)
             VALUES
                (:name, :phone, :type, 'New', :message, :ip_address)"
        );

        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':type' => $type,
            ':message' => $message !== '' ? $message : null,
            ':ip_address' => substr(
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                0,
                45
            ),
        ]);

        homeJsonResponse([
            'ok' => true,
            'message' => 'Your inquiry was submitted to the Morbi field coordinator.',
        ]);
    } catch (Throwable $e) {
        error_log('Home inquiry submission failed: ' . $e->getMessage());

        homeJsonResponse([
            'ok' => false,
            'message' => 'Could not submit your inquiry. Please try again.',
        ], 500);
    }
}

/*
 * Always initialize this before the page HTML.
 * If the activity query fails, the template safely displays its empty state.
 */
$featuredActivities = [];

try {
    $stmt = $pdo->query(
        "SELECT
            a.id,
            a.title AS original_title,
            a.category,
            a.area,
            a.activity_date,
            a.featured_image,
            a.description AS original_description,
            t.title AS gujarati_title,
            t.short_description AS gujarati_short_description,
            t.description AS gujarati_description
         FROM activities AS a
         LEFT JOIN activity_translations AS t
            ON t.activity_id = a.id
           AND t.language = 'gu'
         WHERE a.status = 'Published'
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 3"
    );

    $featuredActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Home featured activities query failed: ' . $e->getMessage());
}

$homeCsrfToken = (string) $_SESSION['home_inquiry_csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Young India Group – The Deven Rabari Foundation | Morbi</title>
  <meta name="description" content="Empowering youth, serving Morbi. 24/7 emergency blood support, healthcare aid and rural education.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script>document.documentElement.classList.add("js");</script>
  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    .home-toast {
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

    .home-toast.is-show {
      opacity: 1;
      transform: translateY(0);
    }

    .home-toast.is-error {
      background: #a51d2d;
    }

    .home-toast.is-success {
      background: #23734a;
    }

    #inquiryForm button:disabled {
      cursor: wait;
      opacity: .7;
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<main>

  <!-- ===== HERO ===== -->
  <section class="hero" id="about">
    <div class="container hero__grid">

      <div class="hero__content">
        <div class="chip">
          <span class="chip__est">● EST. GUJARAT</span>
          <span class="chip__tag">SPEAK • ACT • CHANGE</span>
        </div>

        <h1 class="hero__title">Empowering Youth, Serving Morbi</h1>
        <p lang="gu" class="hero__gujarati">યુવા શક્તિ, સેવા ભક્તિ</p>

        <p class="hero__text">
          Carrying forward the humanitarian mission of Shri Deven Rabari — uniting
          youth power for 24/7 emergency blood support, healthcare aid, and rural
          education across Morbi district.
        </p>

        <div class="hero__actions">
          <a href="bloodhub.php" class="btn btn--orange">
            <i class="fa-solid fa-droplet"></i>
            <span lang="gu">તાત્કાલિક રક્ત સહાય (Urgent Blood)</span>
          </a>
          <a href="volunteers.php" class="btn btn--purple">
            <i class="fa-solid fa-people-group"></i>
            <span lang="gu">સ્વયંસેવક જોડાઓ (Join Volunteer)</span>
          </a>
        </div>

        <ul class="hero__trust">
          <li><i class="fa-solid fa-circle-check"></i>100% Free Public Relief</li>
          <li><i class="fa-solid fa-stopwatch"></i>&lt;15 Mins Match Response</li>
          <li><i class="fa-solid fa-shield-halved"></i>Govt. Regd No. E/1428/Morbi</li>
        </ul>
      </div>

      <figure class="portrait">
        <div class="portrait__photo">
          <img src="assets/DR.png" alt="Shree Deven Rabari">
        </div>
        <figcaption>
          <span class="portrait__label">IN REVERENT REMEMBRANCE</span>
          <strong>Shree Deven Rabari</strong>
          <em>Founder &amp; Visionary Leader</em>
          <q>Service to humanity is devotion to the divine.</q>
        </figcaption>
      </figure>

    </div>
  </section>

  <!-- ===== CIVIC SERVICES ===== -->
  <section class="section section--bordered" id="services">
    <div class="container">

      <div class="section__head">
        <div>
          <span class="eyebrow">મોરબી સેવા સંકલ્પ • CIVIC DESKS</span>
          <h2>તાત્કાલિક નાગરિક સેવાઓ (Direct Community Assistance)</h2>
        </div>
        <p class="section__note">મોરબી જિલ્લામાં ૨૪ કલાક અવિરત કાર્યરત ઇમરજન્સી સેવાઓ અને સહાયતા કેન્દ્ર.</p>
      </div>

      <div class="grid grid--4">

        <article class="service service--red">
          <span class="service__icon"><i class="fa-solid fa-droplet"></i></span>
          <small>URGENT SOS</small>
          <h3>Blood Support</h3>
          <p>Rapid on-call donor dispatch across Morbi Civil &amp; Saurashtra.</p>
          <a href="bloodhub.php">Request Donor <i class="fa-solid fa-arrow-right"></i></a>
        </article>

        <article class="service service--purple">
          <span class="service__icon"><i class="fa-solid fa-hand-holding-heart"></i></span>
          <small>CIVIC CADRE</small>
          <h3>Register Donor</h3>
          <p>Join 6,200+ local youth donors ready for emergency response.</p>
          <a href="bloodhub.php">Sign Up Today <i class="fa-solid fa-arrow-right"></i></a>
        </article>

        <article class="service service--green">
          <span class="service__icon"><i class="fa-solid fa-people-group"></i></span>
          <small>VOLUNTEERS</small>
          <h3>Youth Force</h3>
          <p>Active grassroots volunteering in healthcare and disaster relief.</p>
          <a href="volunteers.php">Join the Team <i class="fa-solid fa-arrow-right"></i></a>
        </article>

        <article class="service service--orange">
          <span class="service__icon"><i class="fa-solid fa-graduation-cap"></i></span>
          <small>WELFARE</small>
          <h3>Civic Aid Desk</h3>
          <p>Educational scholarships, orphan care, and rural health aid.</p>
          <a href="#contact">Contact Welfare <i class="fa-solid fa-arrow-right"></i></a>
        </article>

      </div>
    </div>
  </section>

  <!-- ===== ABOUT TEASER ===== -->
  <section class="section section--bordered">
    <div class="container home-about">

      <div>
        <span class="eyebrow">અમારા વિશે • ABOUT US</span>
        <h2>નાનેરા છોડથી વટવૃક્ષ સુધીની સેવાયાત્રા</h2>

        <p>
          SPEAK • ACT • CHANGE ના જીવનમંત્ર સાથે ૨૦૦૭ માં મોરબીમાં મેન્ટોર શ્રી દેવેનભાઈ એન. ગીડ (રબારી)
          દ્વારા યંગ ઈન્ડીયા ગ્રુપની સ્થાપના કરવામાં આવી. શિક્ષણ, ખેલકુદ, સંસ્કૃતિ અને જનહિત સેવાના
          ૭૯ જેટલા પ્રકલ્પોનું અમલીકરણ કરી ગ્રુપે મોરબી–સૌરાષ્ટ્ર–કચ્છ સહિત સમગ્ર રાષ્ટ્રમાં ગુજરાતને ગૌરવ અપાવ્યું છે.
        </p>

        <p>
          યંગ ઈન્ડીયા ગ્રુપ પબ્લિસિટી નહીં, જરૂરિયાતમંદોની પ્રાયોરિટીનો પર્યાય છે. અમારી ટીમ – અમારી થીમ –
          અમારા મિશનમાં જોડાય તે દરેક યંગ ઈન્ડીયા ગ્રુપનો સદસ્ય છે.
        </p>

        <a href="about.php" class="btn btn--purple">
          વધુ જાણો (Read Full Story) <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>

      <div class="home-facts">
        <div class="home-fact"><strong>2007</strong><span>સ્થાપના વર્ષ</span></div>
        <div class="home-fact"><strong>79+</strong><span>જનહિત સેવા પ્રકલ્પો</span></div>
        <div class="home-fact"><strong>80G</strong><span>કરમુક્તિ પાત્ર યોગદાન</span></div>
        <div class="home-fact"><strong>Dr.</strong><span>માનદ ડોક્ટરેટ ડિગ્રી</span></div>
      </div>

    </div>
  </section>

  <!-- ===== IMPACT STATS ===== -->
  <section class="impact">
    <div class="container">

      <div class="impact__head">
        <span class="eyebrow eyebrow--light">DECADE OF GROUND REALITIES &amp; DEVOTION</span>
        <h2>Our Collective Civic Footprint in Morbi</h2>
      </div>

      <div class="grid grid--4">

        <div class="stat">
          <span class="stat__icon"><i class="fa-solid fa-droplet"></i></span>
          <strong class="stat__number"><span data-count="15400">15,400</span>+</strong>
          <h3>Blood Units Donated</h3>
          <p>Lives supported in Morbi Civil &amp; Saurashtra</p>
        </div>

        <div class="stat">
          <span class="stat__icon"><i class="fa-solid fa-people-group"></i></span>
          <strong class="stat__number"><span data-count="6200">6,200</span>+</strong>
          <h3>Active Youth Volunteers</h3>
          <p>Grassroots cadre across 85 villages</p>
        </div>

        <div class="stat">
          <span class="stat__icon"><i class="fa-solid fa-briefcase-medical"></i></span>
          <strong class="stat__number"><span data-count="45">45</span>+</strong>
          <h3>Rural Health &amp; Eye Camps</h3>
          <p>Free checkups, spectacles, and medicines</p>
        </div>

        <div class="stat">
          <span class="stat__icon"><i class="fa-solid fa-gauge-high"></i></span>
          <strong class="stat__number">&lt;15 Mins</strong>
          <h3>Emergency Match Time</h3>
          <p>Average rapid SOS response in Morbi urban limits</p>
        </div>

      </div>
    </div>
  </section>

  <!-- ===== FEATURED DRIVES ===== -->
  <section class="section" id="drives">
    <div class="container">

      <div class="section__head section__head--line">
        <div>
          <span class="eyebrow">GROUND INITIATIVES</span>
          <h2>Featured Community Drives</h2>
        </div>
        <a href="activities.php" class="link-arrow">
          View Archive <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>

      <div class="grid grid--3">
     
<?php if (!empty($featuredActivities)): ?>
          <?php foreach ($featuredActivities as $activity): ?>
            <?php
              $translatedTitle = trim((string) ($activity['gujarati_title'] ?? ''));
              $title = $translatedTitle !== ''
                  ? $translatedTitle
                  : (string) $activity['original_title'];

              $translatedDescription = trim(
                  (string) ($activity['gujarati_short_description'] ?? '')
              );

              if ($translatedDescription === '') {
                  $translatedDescription = trim(
                      strip_tags((string) (
                          $activity['gujarati_description']
                          ?: $activity['original_description']
                      ))
                  );
              }

              $description = function_exists('mb_substr')
                  ? mb_substr($translatedDescription, 0, 180, 'UTF-8')
                  : substr($translatedDescription, 0, 180);

              $image = trim((string) ($activity['featured_image'] ?? ''));
              if ($image === '') {
                  $image = 'assets/photos/02.jpg';
              }

              $activityDate = '';
              try {
                  $activityDate = (new DateTime(
                      (string) $activity['activity_date']
                  ))->format('d M Y');
              } catch (Throwable $e) {
                  $activityDate = '';
              }
            ?>
            <article class="drive">
              <div class="drive__image">
                <img
                  src="<?= homeEscape($image) ?>"
                  alt="<?= homeEscape($title) ?>"
                  loading="lazy"
                >
                <span class="drive__badge">
                  <?= homeEscape(homeActivityCategoryGujarati((string) $activity['category'])) ?>
                </span>
              </div>

              <div class="drive__body">
                <small>
                  <?= homeEscape((string) $activity['area']) ?>
                  <?php if ($activityDate !== ''): ?>
                    · <?= homeEscape($activityDate) ?>
                  <?php endif; ?>
                </small>

                <h3 lang="gu"><?= homeEscape($title) ?></h3>
                <p lang="gu"><?= homeEscape($description) ?></p>

                <div class="drive__footer">
                  <span class="tag tag--medical">
                    <?= homeEscape(homeActivityCategoryGujarati((string) $activity['category'])) ?>
                  </span>
                  <a href="activity/template.php?id=<?= (int) $activity['id'] ?>">
                    વધુ વાંચો <i class="fa-solid fa-chevron-right"></i>
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p lang="gu">હાલમાં કોઈ પ્રકાશિત પ્રવૃત્તિ ઉપલબ્ધ નથી.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===== VISION + TESTIMONIAL ===== -->
  <section class="section section--bordered">
    <div class="container vision">

      <div class="vision__card">
        <div class="vision__head">
          <img src="assets/logo.png" alt="" class="vision__logo">
          <div>
            <h3>Vision of Shri Deven Rabari</h3>
            <small>Founder • Young India Group</small>
          </div>
        </div>

        <blockquote>
          “We do not build institutions for name or fame; we gather the relentless
          energy of youth so no family in Morbi cries for a single unit of blood in
          the dead of the night. Leadership begins the second you choose to serve.”
        </blockquote>

        <div class="vision__foot">
          <span>સમાજ સેવા એ જ સાચી પ્રભુ ભક્તિ છે</span>
          <span>THE DEVEN RABARI FOUNDATION</span>
        </div>
      </div>

      <div class="vision__side">
        <div class="testimonial">
          <small><i class="fa-solid fa-quote-left"></i> CIVIC BENEFICIARY ACCOUNT</small>
          <p>
            “When my brother urgently needed rare AB-negative blood at 2 AM at Morbi
            Civil Hospital, the foundation helpline dispatched verified donors in under
            20 minutes. Their promptness saved his life.”
          </p>
          <div class="testimonial__foot">
            <div>
              <strong>Pravinbhai Patel</strong>
              <span>Shanala Road, Morbi</span>
            </div>
            <span class="verified"><i class="fa-solid fa-circle-check"></i> Verified Beneficiary</span>
          </div>
        </div>

        <div class="transparency">
          <span class="transparency__icon"><i class="fa-solid fa-building-columns"></i></span>
          <div>
            <strong>Uncompromising Civic Transparency</strong>
            <small>Audited non-profit governance compliant with Gujarat Public Charitable Trust norms.</small>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ===== CONTACT / HQ ===== -->
  <section class="section" id="contact">
    <div class="container">
      <div class="hq">

        <div class="hq__info">
          <div class="hq__tags">
            <span class="tag tag--purple">STATUTORY CREDENTIALS</span>
            <span class="hq__govt">GOVT. OF GUJARAT REGISTRATION</span>
          </div>

          <h2>Young India Group Central Headquarters &amp; Operations Desk</h2>

          <p>
            Registered Public Charitable Trust No: <strong>E/1428/Morbi</strong>. Fully
            compliant with Gujarat Public Trusts Act, Section 12A, and 80G tax
            exemptions for all donor contributions.
          </p>

          <div class="hq__boxes">
            <div class="hq__box">
              <span class="hq__icon"><i class="fa-solid fa-location-dot"></i></span>
              <p><strong>Morbi Central Secretariat</strong><br>Opp. Civil Hospital Road, Near Shanala Gate, Morbi – 363641, Gujarat</p>
            </div>
            <div class="hq__box">
              <span class="hq__icon"><i class="fa-solid fa-phone-volume"></i></span>
              <p><strong>WhatsApp Helpline &amp; Dispatch</strong><br>+91 98250 00000 / +91 2822 220000<br>24 Hours Active Dispatch Desk</p>
            </div>
          </div>
        </div>

       <form class="form" id="inquiryForm" novalidate>
        <input type="hidden" name="form_action" value="submit_inquiry">
        <input type="hidden" name="csrf_token" value="<?= homeEscape($_SESSION['home_inquiry_csrf_token']) ?>">

        <label for="name">Your Full Name (નામ)</label>
        <input type="text" id="name" name="name" placeholder="e.g. Ramesh Patel" required>

        <label for="phone">Mobile / WhatsApp Number</label>
        <input type="tel" id="phone" name="phone" minlength="10" maxlength="10" placeholder="+91 98XXX XXXXX" required>

        <label for="type">Nature of Inquiry</label>
        <select id="type" name="type" required>
          <option value="Blood Donor Verification">Blood Donor Verification</option>
          <option value="Urgent Blood Request">Urgent Blood Request</option>
          <option value="Volunteer Registration">Volunteer Registration</option>
          <option value="Welfare / Scholarship Help">Welfare / Scholarship Help</option>
          <option value="Other">Other / અન્ય</option>
        </select>

        <label for="message">Message (optional)</label>
        <textarea id="message" name="message" rows="4" maxlength="15000" placeholder="Tell us how we can help."></textarea>

        <button type="submit" class="btn btn--purple btn--block">
          <i class="fa-solid fa-paper-plane"></i> Submit to Morbi Field Coordinator
        </button>

        <p class="form__status" id="formStatus" role="status"></p>
      </form>

      </div>
    </div>
  </section>

</main>

<div class="home-toast" id="homeToast" role="status" aria-live="polite"></div>

<?php include 'footer.php'; ?>

<script src="assets/js/script.js"></script>
<script>
(() => {
  const form = document.getElementById("inquiryForm");
  if (!form) return;

  const status = document.getElementById("formStatus");
  const submitButton = form.querySelector('button[type="submit"]');

  form.addEventListener("submit", async function (event) {
    event.preventDefault();

    if (!form.reportValidity()) return;

    const phone = form.elements.namedItem("phone");
    const digits = phone.value.replace(/\D/g, "");
    if (digits.length < 7 || digits.length > 15) {
      phone.setCustomValidity("Enter a valid phone number.");
      phone.reportValidity();
      phone.setCustomValidity("");
      return;
    }

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

    try {
      const response = await fetch(window.location.href, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      const text = await response.text();

      let result;
      try {
        result = JSON.parse(text);
      } catch {
        console.error("Invalid JSON response:", text);
        throw new Error("The server returned an invalid response.");
      }

      if (!response.ok || !result.ok) {
        throw new Error(result.message || "Could not submit your inquiry.");
      }

      form.reset();
      status.textContent = result.message;
      status.className = "form__status is-success";

      if (typeof showToast === "function") {
        showToast(result.message, "is-success");
      }
    } catch (error) {
      status.textContent = error.message;
      status.className = "form__status is-error";

      if (typeof showToast === "function") {
        showToast(error.message, "is-error");
      }
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit to Morbi Field Coordinator';
    }
  });
})();
</script>
</body>
</html>