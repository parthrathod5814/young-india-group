<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/inc/config.php';
$pdo = db();

function detailEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function detailAssetUrl(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '../assets/photos/01.jpg';
    }

    if (
        str_starts_with($path, 'https://')
        || str_starts_with($path, 'http://')
        || str_starts_with($path, '//')
        || str_starts_with($path, '/')
        || str_starts_with($path, '../')
    ) {
        return $path;
    }

    // Database image paths are assumed to be relative to the project root.
    return '../' . ltrim($path, '/');
}

function detailCategoryGujarati(string $category): string
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

function detailCategoryLabel(string $category, string $language): string
{
    if ($language === 'gu') {
        return detailCategoryGujarati($category);
    }

    $labels = [
        'en' => [
            'Event' => 'Event',
            'Donation & Charity' => 'Donation & Charity',
            'Blood Drive' => 'Blood Drive',
            'Medical Camp' => 'Medical Camp',
            'Education' => 'Education',
            'Environment' => 'Environment',
            'Awareness' => 'Awareness',
        ],
        'hi' => [
            'Event' => 'कार्यक्रम',
            'Donation & Charity' => 'दान और सेवा',
            'Blood Drive' => 'रक्तदान शिविर',
            'Medical Camp' => 'चिकित्सा शिविर',
            'Education' => 'शिक्षा',
            'Environment' => 'पर्यावरण',
            'Awareness' => 'जागरूकता',
        ],
    ];

    return $labels[$language][$category] ?? $category;
}

function detailAreaLabel(string $area, string $language): string
{
    $areaKey = strtolower(trim($area));

    $labels = [
        'morbi' => ['gu' => 'મોરબી', 'en' => 'Morbi', 'hi' => 'मोरबी'],
        'shanala' => ['gu' => 'શનાળા', 'en' => 'Shanala', 'hi' => 'शनाला'],
        'wankaner' => ['gu' => 'વાંકાનેર', 'en' => 'Wankaner', 'hi' => 'वांकानेर'],
        'maliya' => ['gu' => 'માળિયા', 'en' => 'Maliya', 'hi' => 'मालिया'],
        'tankara' => ['gu' => 'ટંકારા', 'en' => 'Tankara', 'hi' => 'टंकारा'],
        'halvad' => ['gu' => 'હળવદ', 'en' => 'Halvad', 'hi' => 'हलवद'],
    ];

    foreach ($labels as $key => $translations) {
        if (str_contains($areaKey, $key)) {
            return $translations[$language] ?? $area;
        }
    }

    return $area;
}

function detailDate(string $date, string $language): string
{
    try {
        $dateObject = new DateTimeImmutable($date);
    } catch (Throwable $e) {
        return $date;
    }

    $monthNames = [
        'gu' => [
            1 => 'જાન્યુઆરી', 2 => 'ફેબ્રુઆરી', 3 => 'માર્ચ',
            4 => 'એપ્રિલ', 5 => 'મે', 6 => 'જૂન',
            7 => 'જુલાઈ', 8 => 'ઓગસ્ટ', 9 => 'સપ્ટેમ્બર',
            10 => 'ઓક્ટોબર', 11 => 'નવેમ્બર', 12 => 'ડિસેમ્બર',
        ],
        'en' => [
            1 => 'January', 2 => 'February', 3 => 'March',
            4 => 'April', 5 => 'May', 6 => 'June',
            7 => 'July', 8 => 'August', 9 => 'September',
            10 => 'October', 11 => 'November', 12 => 'December',
        ],
        'hi' => [
            1 => 'जनवरी', 2 => 'फ़रवरी', 3 => 'मार्च',
            4 => 'अप्रैल', 5 => 'मई', 6 => 'जून',
            7 => 'जुलाई', 8 => 'अगस्त', 9 => 'सितंबर',
            10 => 'अक्टूबर', 11 => 'नवंबर', 12 => 'दिसंबर',
        ],
    ];

    $dateText = $dateObject->format('j') . ' '
        . ($monthNames[$language][(int) $dateObject->format('n')] ?? '')
        . ' ' . $dateObject->format('Y');

    if ($language === 'gu') {
        return strtr($dateText, [
            '0' => '૦', '1' => '૧', '2' => '૨', '3' => '૩', '4' => '૪',
            '5' => '૫', '6' => '૬', '7' => '૭', '8' => '૮', '9' => '૯',
        ]);
    }

    if ($language === 'hi') {
        return strtr($dateText, [
            '0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४',
            '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९',
        ]);
    }

    return $dateText;
}

/**
 * Allow safe rich-text formatting from the admin editor.
 * Scripts, event-handler attributes, and unsafe links are discarded.
 */
function detailSafeHtml(string $html): string
{
    if ($html === '') {
        return '';
    }

    if (!class_exists(DOMDocument::class)) {
        return nl2br(detailEscape(trim(strip_tags($html))));
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'a', 'hr',
    ];

    $document = new DOMDocument('1.0', 'UTF-8');
    $previousSetting = libxml_use_internal_errors(true);

    $document->loadHTML(
        '<?xml encoding="UTF-8"><div id="detail-content">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previousSetting);

    $root = $document->getElementById('detail-content');
    if (!$root) {
        return nl2br(detailEscape(trim(strip_tags($html))));
    }

    $renderNode = static function (DOMNode $node) use (&$renderNode, $allowedTags): string {
        if ($node instanceof DOMText) {
            return htmlspecialchars(
                $node->nodeValue ?? '',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        if (!$node instanceof DOMElement) {
            $output = '';
            foreach ($node->childNodes as $child) {
                $output .= $renderNode($child);
            }
            return $output;
        }

        $tag = strtolower($node->tagName);
        if (!in_array($tag, $allowedTags, true)) {
            $output = '';
            foreach ($node->childNodes as $child) {
                $output .= $renderNode($child);
            }
            return $output;
        }

        $attributes = '';
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

            if (
                $href !== ''
                && (
                    $scheme === ''
                    || in_array($scheme, ['http', 'https', 'mailto'], true)
                )
            ) {
                $attributes = ' href="' . htmlspecialchars(
                    $href,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) . '" rel="noopener noreferrer"';
            }
        }

        if ($tag === 'br' || $tag === 'hr') {
            return '<' . $tag . '>';
        }

        $content = '';
        foreach ($node->childNodes as $child) {
            $content .= $renderNode($child);
        }

        return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
    };

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $renderNode($child);
    }

    return $output;
}

function detailPlainText(string $html): string
{
    $text = html_entity_decode(
        strip_tags($html),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function detailTranslationValue(array $translation, string $key): string
{
    return trim((string) ($translation[$key] ?? ''));
}

$activityId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$activityId) {
    http_response_code(400);
    exit('Invalid activity ID.');
}

$requestedLanguage = strtolower(trim((string) ($_GET['lang'] ?? 'gu')));
if (!in_array($requestedLanguage, ['gu', 'en', 'hi'], true)) {
    $requestedLanguage = 'gu';
}

try {
    $activityStmt = $pdo->prepare(
        "SELECT
            id,
            title,
            category,
            area,
            activity_date,
            featured_image,
            status
         FROM activities
         WHERE id = :id
           AND status = 'Published'
         LIMIT 1"
    );
    $activityStmt->execute([':id' => $activityId]);
    $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        http_response_code(404);
        exit('Activity not found.');
    }

    $translationStmt = $pdo->prepare(
        "SELECT
            language,
            title,
            short_description,
            context_background,
            description,
            image_caption
         FROM activity_translations
         WHERE activity_id = :activity_id"
    );
    $translationStmt->execute([':activity_id' => $activityId]);

    $translations = [];
    foreach ($translationStmt->fetchAll(PDO::FETCH_ASSOC) as $translation) {
        $translations[$translation['language']] = $translation;
    }

    if (!$translations) {
        http_response_code(404);
        exit('Activity translation not found.');
    }

    // Prefer the requested language; fall back to Gujarati, then any available translation.
    if (isset($translations[$requestedLanguage])) {
        $activeLanguage = $requestedLanguage;
    } elseif (isset($translations['gu'])) {
        $activeLanguage = 'gu';
    } else {
        $activeLanguage = (string) array_key_first($translations);
    }

    $translation = $translations[$activeLanguage];

    $galleryStmt = $pdo->prepare(
        "SELECT image_path
         FROM activity_gallery_images
         WHERE activity_id = :activity_id
         ORDER BY sort_order ASC, id ASC"
    );
    $galleryStmt->execute([':activity_id' => $activityId]);
    $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_COLUMN);

    $relatedStmt = $pdo->prepare(
        "SELECT
            a.id,
            a.category,
            a.area,
            a.activity_date,
            a.featured_image,
            COALESCE(NULLIF(t.title, ''), g.title, a.title) AS translated_title,
            COALESCE(NULLIF(t.short_description, ''), g.short_description, '') AS translated_summary
         FROM activities AS a
         LEFT JOIN activity_translations AS t
            ON t.activity_id = a.id
           AND t.language = :language
         LEFT JOIN activity_translations AS g
            ON g.activity_id = a.id
           AND g.language = 'gu'
         WHERE a.status = 'Published'
           AND a.id <> :current_id
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 3"
    );
    $relatedStmt->execute([
        ':language' => $activeLanguage,
        ':current_id' => $activityId,
    ]);
    $relatedActivities = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Activity detail page error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load this activity right now.');
}

$title = detailTranslationValue($translation, 'title');
if ($title === '') {
    $title = (string) $activity['title'];
}

$contextHtml = detailTranslationValue($translation, 'context_background');
$descriptionHtml = detailTranslationValue($translation, 'description');
$summary = detailTranslationValue($translation, 'short_description');
$imageCaption = detailTranslationValue($translation, 'image_caption');

$coverImage = detailAssetUrl(
    (string) ($activity['featured_image'] ?? '')
);

if (!$galleryImages) {
    $galleryImages = [$activity['featured_image'] ?: 'assets/photos/01.jpg'];
}

$languageNames = [
    'gu' => 'ગુજરાતી',
    'en' => 'English',
    'hi' => 'हिन्दी',
];

$labelText = [
    'gu' => [
        'home' => 'મુખ્ય પેજ',
        'activities' => 'પ્રવૃત્તિઓ',
        'report' => 'અહેવાલ દસ્તાવેજીકરણ',
        'official' => 'સત્તાવાર યાદી / સક્રિય અહેવાલ',
        'read_time' => 'વાંચન સમય',
        'minutes' => 'મિનિટ',
        'context' => 'પૃષ્ઠભૂમિ અને સંદર્ભ',
        'description' => 'વિગતવાર વર્ણન અને સમગ્ર અહેવાલ',
        'gallery' => 'ફોટો ગેલેરી',
        'click_photo' => 'ક્લિક કરીને તસવીર જુઓ',
        'photos_total' => 'કુલ તસવીરો',
        'related_small' => 'વધુ તાજા સમાચારો',
        'related' => 'તાજેતરના અન્ય અહેવાલો અને પ્રવૃત્તિઓ',
        'all_reports' => 'તમામ અહેવાલો જુઓ',
        'read_report' => 'અહેવાલ વાંચો',
        'no_related' => 'હાલમાં અન્ય અહેવાલો ઉપલબ્ધ નથી.',
        'no_context' => 'આ અહેવાલ માટે પૃષ્ઠભૂમિ અને સંદર્ભ ઉપલબ્ધ નથી.',
        'no_description' => 'આ અહેવાલનું વિગતવાર વર્ણન ઉપલબ્ધ નથી.',
        'photo' => 'તસવીર',
        'byline' => 'યંગ ઇન્ડિયા ગ્રુપ મીડિયા ડેસ્ક',
        'byline_sub' => 'અધિકૃત સંસ્થાકીય અહેવાલ પ્રકાશન',
        'copy_success' => 'લિંક કૉપી થઈ ગઈ. Instagram માં પેસ્ટ કરો.',
        'copy_error' => 'લિંક કૉપી થઈ શકી નથી.',
    ],
    'en' => [
        'home' => 'Home',
        'activities' => 'Activities',
        'report' => 'Activity report',
        'official' => 'Official report',
        'read_time' => 'Read time',
        'minutes' => 'min',
        'context' => 'Context & Background',
        'description' => 'Detailed Description & Event Overview',
        'gallery' => 'Event Gallery',
        'click_photo' => 'Click a photo to view',
        'photos_total' => 'photos',
        'related_small' => 'More latest news',
        'related' => 'Recent Reports & Activities',
        'all_reports' => 'View all reports',
        'read_report' => 'Read report',
        'no_related' => 'No other reports are available yet.',
        'no_context' => 'Context and background are not available for this report.',
        'no_description' => 'A detailed description is not available for this report.',
        'photo' => 'Photo',
        'byline' => 'Young India Group Media Desk',
        'byline_sub' => 'Official institutional report publication',
        'copy_success' => 'Link copied. Paste it in Instagram.',
        'copy_error' => 'Could not copy the link.',
    ],
    'hi' => [
        'home' => 'मुख्य पृष्ठ',
        'activities' => 'गतिविधियाँ',
        'report' => 'गतिविधि रिपोर्ट',
        'official' => 'आधिकारिक रिपोर्ट',
        'read_time' => 'पढ़ने का समय',
        'minutes' => 'मिनट',
        'context' => 'पृष्ठभूमि और संदर्भ',
        'description' => 'विस्तृत विवरण और कार्यक्रम रिपोर्ट',
        'gallery' => 'फोटो गैलरी',
        'click_photo' => 'तस्वीर देखने के लिए क्लिक करें',
        'photos_total' => 'तस्वीरें',
        'related_small' => 'और ताज़ा समाचार',
        'related' => 'हाल की अन्य रिपोर्ट और गतिविधियाँ',
        'all_reports' => 'सभी रिपोर्ट देखें',
        'read_report' => 'रिपोर्ट पढ़ें',
        'no_related' => 'अभी कोई अन्य रिपोर्ट उपलब्ध नहीं है।',
        'no_context' => 'इस रिपोर्ट के लिए पृष्ठभूमि और संदर्भ उपलब्ध नहीं हैं।',
        'no_description' => 'इस रिपोर्ट का विस्तृत विवरण उपलब्ध नहीं है।',
        'photo' => 'तस्वीर',
        'byline' => 'यंग इंडिया ग्रुप मीडिया डेस्क',
        'byline_sub' => 'आधिकारिक संस्थागत रिपोर्ट प्रकाशन',
        'copy_success' => 'लिंक कॉपी हो गया। Instagram में पेस्ट करें।',
        'copy_error' => 'लिंक कॉपी नहीं हो सका।',
    ],
];

$labels = $labelText[$activeLanguage] ?? $labelText['gu'];

$contextContent = $contextHtml !== ''
    ? detailSafeHtml($contextHtml)
    : '<p>' . detailEscape($labels['no_context']) . '</p>';

$descriptionContent = $descriptionHtml !== ''
    ? detailSafeHtml($descriptionHtml)
    : '<p>' . detailEscape($labels['no_description']) . '</p>';

$plainDescription = detailPlainText($descriptionHtml);
$wordCount = str_word_count($plainDescription);
$readMinutes = max(1, (int) ceil($wordCount / 180));

$scheme = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
) ? 'https' : 'http';

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^[a-zA-Z0-9.-]+(?::\d+)?$/', $host)) {
    $host = 'localhost';
}

$currentUrl = $scheme . '://' . $host
    . strtok((string) ($_SERVER['REQUEST_URI'] ?? '/activity/template.php'), '?')
    . '?id=' . (int) $activityId
    . '&lang=' . rawurlencode($activeLanguage);

$shareText = $title . ' — ' . $currentUrl;
$whatsappUrl = 'https://wa.me/?text=' . rawurlencode($shareText);

$pageTitle = $title . ' | Young India Group – Morbi';
$pageDescription = $summary !== ''
    ? detailPlainText($summary)
    : detailPlainText($descriptionHtml);

if ($pageDescription === '') {
    $pageDescription = $title;
}
?>
<!DOCTYPE html>
<html lang="<?= detailEscape($activeLanguage) ?>" data-lang="<?= detailEscape($activeLanguage) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= detailEscape($pageTitle) ?></title>
  <meta name="description" content="<?= detailEscape($pageDescription) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;600;700&family=Noto+Sans+Gujarati:wght@400;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script>document.documentElement.classList.add("js");</script>

  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="css/activity-detail.css">
</head>
<body>

<?php include __DIR__ . '../header.php'; ?>

<main class="actd">

  <!-- ===== BREADCRUMB + LANGUAGE SWITCH ===== -->
  <div class="actd-bar">
    <div class="container actd-bar__inner">
      <nav class="actd-crumbs" aria-label="Breadcrumb">
        <a href="../index.php">
          <i class="fa-solid fa-house"></i> <?= detailEscape($labels['home']) ?>
        </a>
        <span>/</span>
        <a href="../activities.php"><?= detailEscape($labels['activities']) ?></a>
        <span>/</span>
        <strong><?= detailEscape($labels['report']) ?></strong>
      </nav>

      <div class="actd-bar__right">
        <div class="actd-langs" role="group" aria-label="Language">
          <?php foreach ($languageNames as $languageCode => $languageName): ?>
            <a
              class="actd-lang <?= $activeLanguage === $languageCode ? 'is-active' : '' ?>"
              href="template.php?id=<?= (int) $activityId ?>&amp;lang=<?= detailEscape($languageCode) ?>"
              lang="<?= detailEscape($languageCode) ?>"
              <?= $activeLanguage === $languageCode ? 'aria-current="page"' : '' ?>
            ><?= detailEscape($languageName) ?></a>
          <?php endforeach; ?>
        </div>

        <span class="actd-status">
          <i></i> <?= detailEscape($labels['official']) ?>
        </span>

        <span class="actd-meta-chip">
          <i class="fa-regular fa-calendar"></i>
          <?= detailEscape(detailDate((string) $activity['activity_date'], $activeLanguage)) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- ===== CATEGORY + TITLE ===== -->
  <header class="container actd-head">
    <span class="actd-tag"><?= detailEscape(detailCategoryLabel((string) $activity['category'], $activeLanguage)) ?></span>
    <h1 class="actd-title"><?= detailEscape($title) ?></h1>

    <div class="actd-byline">
      <div class="actd-author">
        <img src="../assets/logo.png" alt="" width="44" height="44">
        <div>
          <strong><?= detailEscape($labels['byline']) ?></strong>
          <small><?= detailEscape($labels['byline_sub']) ?></small>
        </div>
      </div>

      <span class="actd-info">
        <i class="fa-regular fa-clock"></i>
        <?= detailEscape($labels['read_time']) ?>: <?= (int) $readMinutes ?> <?= detailEscape($labels['minutes']) ?>
      </span>

      <span class="actd-info">
        <i class="fa-solid fa-location-dot"></i>
        <?= detailEscape(detailAreaLabel((string) $activity['area'], $activeLanguage)) ?>
      </span>

      <div class="actd-share">
        <a
          class="actd-share__btn actd-share__btn--wa"
          id="shareWhatsapp"
          href="<?= detailEscape($whatsappUrl) ?>"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Share on WhatsApp"
          title="WhatsApp"
        ><i class="fa-brands fa-whatsapp"></i></a>

        <button
          type="button"
          class="actd-share__btn actd-share__btn--ig"
          id="shareInstagram"
          aria-label="Copy link to share on Instagram"
          title="Copy link for Instagram"
          data-copy-success="<?= detailEscape($labels['copy_success']) ?>"
          data-copy-error="<?= detailEscape($labels['copy_error']) ?>"
          data-share-url="<?= detailEscape($currentUrl) ?>"
        ><i class="fa-brands fa-instagram"></i></button>
      </div>
    </div>
  </header>

  <!-- ===== COVER PICTURE ===== -->
  <section class="container">
    <figure class="actd-cover">
      <img src="<?= detailEscape($coverImage) ?>" alt="<?= detailEscape($title) ?>">
      <figcaption>
        <?php if ($imageCaption !== ''): ?>
          <small><?= detailEscape($imageCaption) ?></small>
        <?php endif; ?>
        <strong><?= detailEscape($title) ?></strong>
      </figcaption>
      <span class="actd-cover__credit"><?= detailEscape(detailCategoryLabel((string) $activity['category'], $activeLanguage)) ?></span>
    </figure>
  </section>

  <!-- ===== ARTICLE SECTIONS ===== -->
  <div class="container actd-article">

    <section class="actd-card">
      <div class="actd-card__head">
        <h2><i class="fa-solid fa-book-open"></i> <?= detailEscape($labels['context']) ?></h2>
        <span class="actd-tag">01</span>
      </div>
      <div class="actd-prose" lang="<?= detailEscape($activeLanguage) ?>">
        <?= $contextContent ?>
      </div>
    </section>

    <section class="actd-card">
      <div class="actd-card__head">
        <h2><i class="fa-solid fa-file-lines"></i> <?= detailEscape($labels['description']) ?></h2>
        <span class="actd-tag">02</span>
      </div>
      <div class="actd-prose" lang="<?= detailEscape($activeLanguage) ?>">
        <?= $descriptionContent ?>
      </div>
    </section>

  </div>

  <!-- ===== PHOTO GALLERY ===== -->
  <?php if ($galleryImages): ?>
    <section class="container">
      <div
        class="actd-card actd-gallery"
        id="actdGallery"
        data-photo-label="<?= detailEscape($labels['photo']) ?>"
      >
        <div class="actd-card__head">
          <h2><i class="fa-regular fa-images"></i> <?= detailEscape($labels['gallery']) ?></h2>
          <div class="actd-gallery__tools"></div>
        </div>

        <div class="actd-stage" id="actdStage" tabindex="0" aria-roledescription="carousel">
          <img
            id="actdMain"
            src="<?= detailEscape(detailAssetUrl((string) $galleryImages[0])) ?>"
            alt="<?= detailEscape($imageCaption !== '' ? $imageCaption : $title) ?>"
          >
          <?php if (count($galleryImages) > 1): ?>
            <button type="button" class="actd-arrow actd-arrow--prev" id="actdPrev" aria-label="Previous">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button type="button" class="actd-arrow actd-arrow--next" id="actdNext" aria-label="Next">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          <?php endif; ?>
          <div class="actd-stage__caption">
            <small id="actdCapLabel"></small>
            <strong id="actdCaption"><?= detailEscape($imageCaption) ?></strong>
          </div>
        </div>

        <div class="actd-thumbs-meta">
          <span><i class="fa-regular fa-hand-pointer"></i> <?= detailEscape($labels['click_photo']) ?></span>
          <strong><?= count($galleryImages) ?> <?= detailEscape($labels['photos_total']) ?></strong>
        </div>

        <div class="actd-thumbs" id="actdThumbs">
          <?php foreach ($galleryImages as $index => $galleryImage): ?>
            <?php $galleryImageUrl = detailAssetUrl((string) $galleryImage); ?>
            <button
              type="button"
              class="actd-thumb <?= $index === 0 ? 'is-active' : '' ?>"
              data-src="<?= detailEscape($galleryImageUrl) ?>"
              data-caption="<?= detailEscape($imageCaption !== '' ? $imageCaption : $title) ?>"
              aria-label="<?= detailEscape($labels['photo']) ?> <?= $index + 1 ?>"
            >
              <img src="<?= detailEscape($galleryImageUrl) ?>" alt="" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ===== RECENT REPORTS ===== -->
  <section class="container actd-related" id="actdRelated">
    <div class="actd-related__head">
      <div>
        <small><?= detailEscape($labels['related_small']) ?></small>
        <h2><?= detailEscape($labels['related']) ?></h2>
      </div>
      <a href="../activities.php" class="actd-link">
        <?= detailEscape($labels['all_reports']) ?> <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php if ($relatedActivities): ?>
      <div class="actd-related__grid">
        <?php foreach ($relatedActivities as $related): ?>
          <?php
            $relatedTitle = trim((string) ($related['translated_title'] ?? ''));
            $relatedSummary = trim((string) ($related['translated_summary'] ?? ''));
            $relatedUrl = 'template.php?id=' . (int) $related['id']
                . '&lang=' . rawurlencode($activeLanguage);
          ?>
          <article class="actd-news" data-type="activity">
            <a href="<?= detailEscape($relatedUrl) ?>" class="actd-news__image">
              <img
                src="<?= detailEscape(detailAssetUrl((string) ($related['featured_image'] ?? ''))) ?>"
                alt="<?= detailEscape($relatedTitle) ?>"
                loading="lazy"
              >
              <span class="actd-news__tag">
                <?= detailEscape(detailCategoryLabel((string) $related['category'], $activeLanguage)) ?>
              </span>
            </a>

            <div class="actd-news__body">
              <small><?= detailEscape(detailDate((string) $related['activity_date'], $activeLanguage)) ?></small>
              <h3>
                <a href="<?= detailEscape($relatedUrl) ?>">
                  <?= detailEscape($relatedTitle) ?>
                </a>
              </h3>
              <?php if ($relatedSummary !== ''): ?>
                <p><?= detailEscape(detailPlainText($relatedSummary)) ?></p>
              <?php endif; ?>
              <a href="<?= detailEscape($relatedUrl) ?>" class="actd-link">
                <?= detailEscape($labels['read_report']) ?>
                <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="actd-nomatch"><?= detailEscape($labels['no_related']) ?></p>
    <?php endif; ?>
  </section>

</main>

<?php include __DIR__ . '../footer.php'; ?>

<div
  id="detailToast"
  role="status"
  aria-live="polite"
  style="position:fixed;right:24px;bottom:24px;z-index:9999;display:none;padding:12px 18px;border-radius:10px;background:#35105e;color:#fff;"
></div>

<script src="../assets/js/script.js"></script>
<script>
(() => {
  const thumbs = [...document.querySelectorAll(".actd-thumb")];
  const mainImage = document.getElementById("actdMain");
  const caption = document.getElementById("actdCaption");
  let currentIndex = 0;

  function showPhoto(index) {
    if (!thumbs.length || !mainImage) return;

    currentIndex = (index + thumbs.length) % thumbs.length;
    const button = thumbs[currentIndex];

    mainImage.src = button.dataset.src || "";
    mainImage.alt = button.dataset.caption || "";
    if (caption) caption.textContent = button.dataset.caption || "";

    thumbs.forEach((thumb, thumbIndex) => {
      thumb.classList.toggle("is-active", thumbIndex === currentIndex);
    });
  }

  thumbs.forEach((button, index) => {
    button.addEventListener("click", () => showPhoto(index));
  });

  document.getElementById("actdPrev")?.addEventListener("click", () => {
    showPhoto(currentIndex - 1);
  });

  document.getElementById("actdNext")?.addEventListener("click", () => {
    showPhoto(currentIndex + 1);
  });

  document.getElementById("shareInstagram")?.addEventListener("click", async event => {
    const button = event.currentTarget;
    const url = button.dataset.shareUrl || window.location.href;
    const toast = document.getElementById("detailToast");

    try {
      await navigator.clipboard.writeText(url);
      toast.textContent = button.dataset.copySuccess || "Link copied.";
      toast.style.background = "#23734a";
    } catch (error) {
      const input = document.createElement("textarea");
      input.value = url;
      input.style.position = "fixed";
      input.style.opacity = "0";
      document.body.appendChild(input);
      input.select();

      const copied = document.execCommand("copy");
      input.remove();

      toast.textContent = copied
        ? (button.dataset.copySuccess || "Link copied.")
        : (button.dataset.copyError || "Could not copy the link.");
      toast.style.background = copied ? "#23734a" : "#a51d2d";
    }

    toast.style.display = "block";
    window.setTimeout(() => {
      toast.style.display = "none";
    }, 3500);
  });
})();
</script>

</body>
</html>