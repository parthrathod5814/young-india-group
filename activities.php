<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
$pdo = db();

function activityEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function activityCategoryGujarati(string $category): string
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

function activityCategoryKey(string $category): string
{
    return match ($category) {
        'Blood Drive' => 'blood',
        'Medical Camp' => 'medical',
        'Education' => 'education',
        'Environment' => 'environment',
        'Donation & Charity' => 'donation',
        'Awareness' => 'awareness',
        'Event' => 'event',
        default => 'other',
    };
}

function activityTalukaKey(string $area): string
{
    $normalized = function_exists('mb_strtolower')
        ? mb_strtolower(trim($area), 'UTF-8')
        : strtolower(trim($area));

    if (str_contains($normalized, 'shanala') || str_contains($normalized, 'શનાળા')) {
        return 'shanala';
    }
    if (str_contains($normalized, 'wankaner') || str_contains($normalized, 'વાંકાનેર')) {
        return 'wankaner';
    }
    if (str_contains($normalized, 'maliya') || str_contains($normalized, 'માળિયા')) {
        return 'maliya';
    }
    if (str_contains($normalized, 'tankara') || str_contains($normalized, 'ટંકારા')) {
        return 'tankara';
    }
    if (str_contains($normalized, 'morbi') || str_contains($normalized, 'મોરબી')) {
        return 'morbi';
    }

    return 'other';
}

function activityAreaGujarati(string $area): string
{
    return match (activityTalukaKey($area)) {
        'morbi' => 'મોરબી',
        'shanala' => 'શનાળા',
        'wankaner' => 'વાંકાનેર',
        'maliya' => 'માળિયા',
        'tankara' => 'ટંકારા',
        default => $area,
    };
}

function activityGujaratiDate(string $date): string
{
    try {
        $dateObject = new DateTimeImmutable($date);
    } catch (Throwable $e) {
        return $date;
    }

    $months = [
        1 => 'જાન્યુઆરી',
        2 => 'ફેબ્રુઆરી',
        3 => 'માર્ચ',
        4 => 'એપ્રિલ',
        5 => 'મે',
        6 => 'જૂન',
        7 => 'જુલાઈ',
        8 => 'ઓગસ્ટ',
        9 => 'સપ્ટેમ્બર',
        10 => 'ઓક્ટોબર',
        11 => 'નવેમ્બર',
        12 => 'ડિસેમ્બર',
    ];

    $dateText = $dateObject->format('d') . ' '
        . $months[(int) $dateObject->format('n')] . ', '
        . $dateObject->format('Y');

    return strtr($dateText, [
        '0' => '૦', '1' => '૧', '2' => '૨', '3' => '૩', '4' => '૪',
        '5' => '૫', '6' => '૬', '7' => '૭', '8' => '૮', '9' => '૯',
    ]);
}

function activityGujaratiDescription(array $activity): string
{
    $text = trim((string) ($activity['short_description'] ?? ''));

    if ($text === '') {
        $text = (string) ($activity['translation_description'] ?? '');
    }

    $text = html_entity_decode(
        strip_tags($text),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 300) {
        return mb_substr($text, 0, 297, 'UTF-8') . '…';
    }

    if (strlen($text) > 600) {
        return substr($text, 0, 597) . '…';
    }

    return $text;
}

function activityImage(array $activity): string
{
    $image = trim((string) ($activity['featured_image'] ?? ''));

    return $image !== '' ? $image : 'assets/photos/02.jpg';
}

function activityCardHtml(array $activity): string
{
    $id = (int) $activity['id'];
    $title = trim((string) ($activity['gujarati_title'] ?? ''));
    $description = activityGujaratiDescription($activity);
    $category = (string) ($activity['category'] ?? '');
    $area = (string) ($activity['area'] ?? '');
    $date = activityGujaratiDate((string) ($activity['activity_date'] ?? ''));
    $categoryKey = activityCategoryKey($category);
    $talukaKey = activityTalukaKey($area);
    $categoryLabel = activityCategoryGujarati($category);
    $areaLabel = activityAreaGujarati($area);
    $image = activityImage($activity);

    ob_start();
    ?>
    <article
      class="activity-card"
      data-activity
      data-category="<?= activityEscape($categoryKey) ?>"
      data-taluka="<?= activityEscape($talukaKey) ?>"
    >
      <div class="activity-card__image">
        <img
          src="<?= activityEscape($image) ?>"
          alt="<?= activityEscape($title) ?>"
          loading="lazy"
        >
      </div>

      <div class="activity-card__body">
        <span class="activity-card__date">
          <i class="fa-regular fa-calendar"></i>
          <?= activityEscape($date) ?>
        </span>

<h3 lang="gu"><?= activityEscape($title) ?></h3>
<span class="activity-sub" lang="gu"><?= activityEscape($categoryLabel) ?></span>
<p lang="gu"><?= activityEscape($description) ?></p>

        <div class="activity-card__foot">
          <span class="activity-card__metric">
            <?= activityEscape($categoryLabel) ?> · <?= activityEscape($areaLabel) ?>
          </span>
          <a href="activity/template.php?id=<?= $id ?>">
            વધુ વાંચો <i class="fa-solid fa-chevron-right"></i>
          </a>
        </div>
      </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

function fetchActivityPage(PDO $pdo, int $featuredId, int $offset, int $limit): array
{
    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.title AS original_title,
            a.category,
            a.area,
            a.activity_date,
            a.featured_image,
            a.created_at,
            t.title AS gujarati_title,
            t.short_description,
            t.description AS translation_description
         FROM activities AS a
         INNER JOIN activity_translations AS t
            ON t.activity_id = a.id
           AND t.language = 'gu'
         WHERE a.status = 'Published'
           AND a.id <> :featured_id
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT :limit OFFSET :offset"
    );

    $stmt->bindValue(':featured_id', $featuredId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
 * AJAX endpoint: return six additional activity cards.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && ($_GET['action'] ?? '') === 'load_more'
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {
        $featuredStmt = $pdo->query(
            "SELECT a.id
             FROM activities AS a
             INNER JOIN activity_translations AS t
                ON t.activity_id = a.id
               AND t.language = 'gu'
             WHERE a.status = 'Published'
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 1"
        );

        $featuredId = (int) ($featuredStmt->fetchColumn() ?: 0);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
        $offset = ($offset !== false && $offset >= 0) ? $offset : 0;

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM activities AS a
             INNER JOIN activity_translations AS t
                ON t.activity_id = a.id
               AND t.language = 'gu'
             WHERE a.status = 'Published'
               AND a.id <> :featured_id"
        );
        $countStmt->execute([':featured_id' => $featuredId]);
        $totalRemaining = (int) $countStmt->fetchColumn();

        $activities = fetchActivityPage($pdo, $featuredId, $offset, 6);
        $html = '';

        foreach ($activities as $activity) {
            $html .= activityCardHtml($activity);
        }

        echo json_encode([
            'ok' => true,
            'html' => $html,
            'next_offset' => $offset + count($activities),
            'has_more' => $offset + count($activities) < $totalRemaining,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        error_log('Activities load-more error: ' . $e->getMessage());
        http_response_code(500);

        echo json_encode([
            'ok' => false,
            'message' => 'પ્રવૃત્તિઓ લોડ થઈ શકી નથી. ફરી પ્રયાસ કરો.',
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    exit;
}

$featuredActivity = null;
$activities = [];
$activityCategories = [];
$totalRemaining = 0;
$pageLoadError = false;

try {
    /*
     * Newest published activity with a Gujarati translation is the flagship.
     */
    $featuredStmt = $pdo->query(
        "SELECT
            a.id,
            a.title AS original_title,
            a.category,
            a.area,
            a.activity_date,
            a.featured_image,
            a.created_at,
            t.title AS gujarati_title,
            t.short_description,
            t.description AS translation_description
         FROM activities AS a
         INNER JOIN activity_translations AS t
            ON t.activity_id = a.id
           AND t.language = 'gu'
         WHERE a.status = 'Published'
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 1"
    );


    
    $featuredActivity = $featuredStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $featuredId = (int) ($featuredActivity['id'] ?? 0);

    /*
     * Dynamic category buttons, restricted to published activities
     * that have Gujarati translations.
     */
    $categoryStmt = $pdo->query(
        "SELECT DISTINCT a.category
         FROM activities AS a
         INNER JOIN activity_translations AS t
            ON t.activity_id = a.id
           AND t.language = 'gu'
         WHERE a.status = 'Published'
         ORDER BY a.category"
    );
    $activityCategories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activities AS a
         INNER JOIN activity_translations AS t
            ON t.activity_id = a.id
           AND t.language = 'gu'
         WHERE a.status = 'Published'
           AND a.id <> :featured_id"
    );
    $countStmt->execute([':featured_id' => $featuredId]);
    $totalRemaining = (int) $countStmt->fetchColumn();

    /*
     * First nine cards, newest first, excluding the flagship activity.
     */
    $activities = fetchActivityPage($pdo, $featuredId, 0, 9);
} catch (Throwable $e) {
    $pageLoadError = true;
    error_log('Activities page load error: ' . $e->getMessage());
}

$initialCount = count($activities);
$hasMore = $initialCount < $totalRemaining;

$categoryLabels = [
    'blood' => 'રક્તદાન કેમ્પ',
    'medical' => 'આરોગ્ય કેમ્પ',
    'education' => 'શિક્ષણ સહાય',
    'environment' => 'વૃક્ષારોપણ',
    'donation' => 'દાન અને સેવાકાર્ય',
    'awareness' => 'જાગૃતિ',
    'event' => 'કાર્યક્રમ',
    'other' => 'અન્ય',
];

$featuredTitle = trim((string) ($featuredActivity['gujarati_title'] ?? ''));
$featuredDescription = '';

if ($featuredActivity) {
    $context = (string) ($featuredDescription = $featuredActivity
    ? activityGujaratiDescription($featuredActivity)
    : '');

    $featuredDescription = html_entity_decode(
        strip_tags($context),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $featuredDescription = trim(
        preg_replace('/\s+/u', ' ', $featuredDescription) ?? $featuredDescription
    );
}

$featuredImage = $featuredActivity
    ? activityImage($featuredActivity)
    : 'assets/photos/01.jpg';

$featuredArea = $featuredActivity
    ? activityAreaGujarati((string) ($featuredActivity['area'] ?? ''))
    : '';

$featuredDate = $featuredActivity
    ? activityGujaratiDate((string) ($featuredActivity['activity_date'] ?? ''))
    : '';

$featuredCategory = $featuredActivity
    ? activityCategoryGujarati((string) ($featuredActivity['category'] ?? ''))
    : '';

$galleryCount = 0;
if ($featuredActivity) {
    try {
        $galleryStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM activity_gallery_images WHERE activity_id = :id'
        );
        $galleryStmt->execute([':id' => (int) $featuredActivity['id']]);
        $galleryCount = (int) $galleryStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Featured activity gallery count error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="gu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Activities &amp; News (પ્રવૃત્તિઓ અને સમાચાર) | Young India Group – Morbi</title>
  <meta name="description" content="મોરબી અને સૌરાષ્ટ્રમાં યંગ ઇન્ડિયા ગ્રુપની પ્રવૃત્તિઓ અને સમુદાય સેવા અભિયાનો.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script>document.documentElement.classList.add("js");</script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'header.php'; ?>

<main>

  <!-- ===== HERO + CATEGORY FILTER ===== -->
  <section class="activity-hero">
    <div class="container">
      <div class="activity-hero__panel">
        <span class="activity-chip">
          <i class="fa-solid fa-bullhorn"></i>
          ફિલ્ડ સેવા અને ગ્રાઉન્ડ અપડેટ્સ • Ground Actions
        </span>
        <h1>પ્રવૃત્તિઓ અને સમાચાર</h1>
        <h2>Activities &amp; Community News</h2>
        <p>મોરબી અને સમગ્ર સૌરાષ્ટ્રમાં સામાજિક ક્રાંતિ, યુવા નેતૃત્વ, રક્તદાન અને લોકસેવા અભિયાન.</p>
        <hr class="activity-hero__rule">

        <div class="activity-pills" id="categoryFilters">
          <button
            type="button"
            class="activity-pill is-active"
            data-group="category"
            data-value="all"
            aria-pressed="true"
          >બધા / All (<?= (int) (count($activities) + ($featuredActivity ? 1 : 0) + $totalRemaining) ?>)</button>

          <?php foreach ($activityCategories as $category): ?>
            <?php
              $category = (string) $category;
              $categoryKey = activityCategoryKey($category);
            ?>
            <button
              type="button"
              class="activity-pill"
              data-group="category"
              data-value="<?= activityEscape($categoryKey) ?>"
              aria-pressed="false"
            ><?= activityEscape($categoryLabels[$categoryKey] ?? activityCategoryGujarati($category)) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== SEARCH + TALUKA FILTER ===== -->
  <section>
    <div class="container">
      <div class="activity-toolbar">
        <label class="activity-search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input
            type="search"
            id="activitySearch"
            placeholder="શોધો / Search dispatches..."
            aria-label="પ્રવૃત્તિઓ શોધો"
          >
        </label>

        <div class="activity-taluka">
          <span>તાલુકો / TALUKA:</span>
          <button type="button" class="activity-pill is-active" data-group="taluka" data-value="all" aria-pressed="true">બધા (All)</button>
          <button type="button" class="activity-pill" data-group="taluka" data-value="morbi" aria-pressed="false">મોરબી શહેર (Morbi)</button>
          <button type="button" class="activity-pill" data-group="taluka" data-value="shanala" aria-pressed="false">શનાળા રોડ (Shanala)</button>
          <button type="button" class="activity-pill" data-group="taluka" data-value="wankaner" aria-pressed="false">વાંકાનેર (Wankaner)</button>
          <button type="button" class="activity-pill" data-group="taluka" data-value="maliya" aria-pressed="false">માળિયા (Maliya)</button>
          <button type="button" class="activity-pill" data-group="taluka" data-value="tankara" aria-pressed="false">ટંકારા (Tankara)</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== FLAGSHIP STORY: NEWEST ACTIVITY ===== -->
  <section>
    <div class="container">
      <?php if ($featuredActivity): ?>
        <?php
          $featuredId = (int) $featuredActivity['id'];
          $featuredCategoryKey = activityCategoryKey(
              (string) $featuredActivity['category']
          );
          $featuredTalukaKey = activityTalukaKey(
              (string) $featuredActivity['area']
          );
        ?>
        <article
          class="activity-feature"
          data-activity
          data-category="<?= activityEscape($featuredCategoryKey) ?>"
          data-taluka="<?= activityEscape($featuredTalukaKey) ?>"
        >
          <div class="activity-feature__image">
            <img src="<?= activityEscape($featuredImage) ?>" alt="<?= activityEscape($featuredTitle) ?>">
          </div>

          <div class="activity-feature__body">
            <div class="activity-meta">
              <span><i class="fa-regular fa-calendar"></i> <?= activityEscape($featuredDate) ?></span>
              <span><?= activityEscape($featuredArea) ?></span>
            </div>

            <h2 lang="gu"><?= activityEscape($featuredTitle) ?></h2>
           <p lang="gu"><?= activityEscape($featuredDescription) ?></p>

            <div class="activity-feature__actions">
              <a
                href="activity/template.php?id=<?= $featuredId ?>"
                class="btn btn--purple"
              >સંપૂર્ણ અહેવાલ <i class="fa-solid fa-arrow-right"></i></a>
            </div>
          </div>
        </article>
      <?php else: ?>
        <p lang="gu">હાલમાં કોઈ ગુજરાતી અનુવાદવાળી પ્રકાશિત પ્રવૃત્તિ ઉપલબ્ધ નથી.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== RECENT DRIVES ===== -->
  <section>
    <div class="container">
      <div class="activity-head">
        <div>
          <h2>તાજેતરના સેવા અભિયાનો અને ગ્રાઉન્ડ ડ્રાઇવ્સ</h2>
          <p>તાજેતરની પ્રવૃત્તિઓ અને માનવસેવા અભિયાનો</p>
        </div>
        <span class="activity-verified">પ્રમાણિત ક્ષેત્રીય અહેવાલો</span>
      </div>

      <p class="activity-count" id="resultCount" role="status"></p>

      <div class="grid grid--3" id="activityGrid">
        <?php foreach ($activities as $activity): ?>
          <?= activityCardHtml($activity) ?>
        <?php endforeach; ?>
      </div>

      <div class="activity-empty" id="emptyState" hidden>
        <i class="fa-solid fa-folder-open"></i>
        <p>તમારા ફિલ્ટર સાથે મેળ ખાતી પ્રવૃત્તિ મળી નથી.</p>
        <button type="button" class="btn btn--purple" id="resetFilters">
          <i class="fa-solid fa-rotate-left"></i> ફિલ્ટર રીસેટ કરો
        </button>
      </div>

      <?php if ($pageLoadError): ?>
        <p class="activity-count" role="alert">પ્રવૃત્તિઓ લોડ કરવામાં સમસ્યા આવી. કૃપા કરીને પછીથી ફરી પ્રયાસ કરો.</p>
      <?php endif; ?>

      <div class="activity-more-wrap" style="text-align:center; margin-top:24px;">
        <button
          type="button"
          class="btn btn--purple"
          id="seeMoreActivities"
          data-offset="<?= $initialCount ?>"
          <?= $hasMore ? '' : 'hidden' ?>
        >વધુ પ્રવૃત્તિઓ જુઓ</button>
      </div>
    </div>
  </section>

  <!-- ===== IMPACT BAND ===== -->
  <section>
    <div class="container">
      <div class="activity-stats">
        <div class="activity-stats__grid">
          <div class="activity-stats__item"><strong><span data-count="28400" style="font-size:30px;">28,400</span>+</strong><span>Total Blood Units Donated</span></div>
          <div class="activity-stats__item"><strong><span data-count="52000" style="font-size:30px;">52,000</span>+</strong><span>Rural Patients Treated Free</span></div>
          <div class="activity-stats__item"><strong><span data-count="18500" style="font-size:30px;">18,500</span>+</strong><span>Students Digitally Equipped</span></div>
          <div class="activity-stats__item"><strong><span data-count="4200" style="font-size:30px;">4,200</span>+</strong><span>Registered Youth Cadre</span></div>
        </div>
      </div>
    </div>
  </section>

</main>

<?php include 'footer.php'; ?>

<script src="assets/js/script.js"></script>
<script>
(() => {
  const grid = document.getElementById("activityGrid");
  const moreButton = document.getElementById("seeMoreActivities");
  const search = document.getElementById("activitySearch");
  const resultCount = document.getElementById("resultCount");
  const emptyState = document.getElementById("emptyState");
  const resetButton = document.getElementById("resetFilters");

  if (!grid) return;

  const filters = {
    category: "all",
    taluka: "all",
    search: ""
  };

  function updateResults() {
    const cards = [...document.querySelectorAll("[data-activity]")];
    let visibleCount = 0;

    cards.forEach(card => {
      const categoryMatches =
        filters.category === "all" ||
        card.dataset.category === filters.category;

      const talukaMatches =
        filters.taluka === "all" ||
        card.dataset.taluka === filters.taluka;

      const searchText = filters.search.trim().toLocaleLowerCase();
      const textMatches =
        searchText === "" ||
        card.textContent.toLocaleLowerCase().includes(searchText);

      const visible = categoryMatches && talukaMatches && textMatches;
      card.hidden = !visible;

      if (visible) visibleCount++;
    });

    if (resultCount) {
      resultCount.textContent = `${visibleCount} પ્રવૃત્તિઓ દર્શાવવામાં આવી રહી છે`;
    }

    if (emptyState) {
      emptyState.hidden = visibleCount !== 0;
    }
  }

  document.querySelectorAll('.activity-pill[data-group="category"]').forEach(button => {
    button.addEventListener("click", () => {
      document.querySelectorAll('.activity-pill[data-group="category"]').forEach(item => {
        item.classList.remove("is-active");
        item.setAttribute("aria-pressed", "false");
      });

      button.classList.add("is-active");
      button.setAttribute("aria-pressed", "true");
      filters.category = button.dataset.value || "all";
      updateResults();
    });
  });

  document.querySelectorAll('.activity-pill[data-group="taluka"]').forEach(button => {
    button.addEventListener("click", () => {
      document.querySelectorAll('.activity-pill[data-group="taluka"]').forEach(item => {
        item.classList.remove("is-active");
        item.setAttribute("aria-pressed", "false");
      });

      button.classList.add("is-active");
      button.setAttribute("aria-pressed", "true");
      filters.taluka = button.dataset.value || "all";
      updateResults();
    });
  });

  if (search) {
    search.addEventListener("input", () => {
      filters.search = search.value;
      updateResults();
    });
  }

  if (resetButton) {
    resetButton.addEventListener("click", () => {
      filters.category = "all";
      filters.taluka = "all";
      filters.search = "";

      if (search) search.value = "";

      document.querySelectorAll(".activity-pill").forEach(button => {
        const active = button.dataset.value === "all";
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-pressed", active ? "true" : "false");
      });

      updateResults();
    });
  }

  if (moreButton) {
    moreButton.addEventListener("click", async () => {
      const offset = Number.parseInt(moreButton.dataset.offset || "0", 10);

      moreButton.disabled = true;
      moreButton.textContent = "લોડ થઈ રહ્યું છે...";

      try {
        const url = new URL(window.location.href);
        url.searchParams.set("action", "load_more");
        url.searchParams.set("offset", String(offset));

        const response = await fetch(url.toString(), {
          method: "GET",
          credentials: "same-origin",
          cache: "no-store",
          headers: { "X-Requested-With": "XMLHttpRequest" }
        });

        const data = await response.json();

        if (!response.ok || !data.ok) {
          throw new Error(data.message || "પ્રવૃત્તિઓ લોડ થઈ શકી નથી.");
        }

        grid.insertAdjacentHTML("beforeend", data.html || "");
        moreButton.dataset.offset = String(data.next_offset || offset);
        moreButton.hidden = !data.has_more;
        moreButton.textContent = "વધુ પ્રવૃત્તિઓ જુઓ";

        updateResults();
      } catch (error) {
        moreButton.textContent = error.message || "ફરી પ્રયાસ કરો";
      } finally {
        moreButton.disabled = false;
      }
    });
  }

  updateResults();
})();
</script>

</body>
</html>
<!-- ```**Note:** This expects each published activity to have one Gujarati row in `activity_translations` (`language = 'gu'`). Activities without that translation are intentionally omitted so the cards don’t fall back to English. The detail links pass the activity ID to `activity/template.php?id=...&lang=gu`. -->