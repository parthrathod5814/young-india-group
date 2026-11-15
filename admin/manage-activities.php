<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

function activityManageEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setActivityManageToast(string $message, string $type = 'success'): void
{
    $_SESSION['activity_manage_toast_message'] = $message;
    $_SESSION['activity_manage_toast_type'] = $type;
}

function activityManageLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function activityManagePlainText(string $value, int $maxLength): string
{
    $value = trim(strip_tags($value));

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function activityManageCleanHtml(string $html): string
{
    $allowedTags = '<p><br><strong><b><em><i><u><s><h2><h3><blockquote><ul><ol><li><a>';

    if (!class_exists(DOMDocument::class)) {
        return trim(strip_tags($html, $allowedTags));
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="activity-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="activity-root"]')->item(0);

    if (!$root) {
        return '';
    }

    $allowed = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'a',
    ];

    $cleanNode = function (DOMNode $node) use (&$cleanNode, $allowed): string {
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
                $output .= $cleanNode($child);
            }
            return $output;
        }

        $tag = strtolower($node->tagName);
        $inner = '';

        foreach ($node->childNodes as $child) {
            $inner .= $cleanNode($child);
        }

        if (!in_array($tag, $allowed, true)) {
            return $inner;
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));

            if (preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                    . '" rel="nofollow noopener noreferrer">' . $inner . '</a>';
            }

            return $inner;
        }

        return '<' . $tag . '>' . $inner . '</' . $tag . '>';
    };

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $cleanNode($child);
    }

    return trim($output);
}

function activityManageValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

/**
 * Validates image format and a maximum file size of 1 MB.
 * Image dimensions are not restricted.
 */
function activityManageValidateImage(array $file, string $label): ?string
{
    if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
        return $label . ': upload data is missing.';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ': file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => $label . ': upload was incomplete.',
            UPLOAD_ERR_NO_FILE => $label . ': please choose an image.',
            default => $label . ': upload failed.',
        };
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $label . ': invalid uploaded file.';
    }

    if ((int) $file['size'] <= 0 || (int) $file['size'] > 1024 * 1024) {
        return $label . ': image must be 1 MB or smaller.';
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($allowedTypes[$extension])) {
        return $label . ': use a JPG, PNG, or WebP image.';
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $allowedTypes[$extension]) {
        return $label . ': upload a valid JPG, PNG, or WebP image.';
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) !== $allowedTypes[$extension]) {
            return $label . ': image type could not be verified.';
        }
    }

    return null;
}

function activityManageStoreImage(array $file, string $directory, string $mime): array
{
    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => throw new RuntimeException('Unsupported image type.'),
    };

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save an uploaded image.');
    }

    @chmod($destination, 0644);

    return [
        'path' => 'uploads/activities/' . $filename,
        'disk_path' => $destination,
    ];
}

function activityManageRemoveImage(string $relativePath): void
{
    if (!str_starts_with($relativePath, 'uploads/activities/')) {
        return;
    }

    $filename = basename($relativePath);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR
        . 'uploads' . DIRECTORY_SEPARATOR
        . 'activities' . DIRECTORY_SEPARATOR
        . $filename;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$languages = [
    'gu' => 'ગુજરાતી',
    'en' => 'English',
    'hi' => 'हिन्दी',
];

$allowedCategories = [
    'Event',
    'Donation & Charity',
    'Blood Drive',
    'Medical Camp',
    'Education',
    'Environment',
    'Awareness',
];

$allowedAreas = [
    'Morbi City',
    'Shanala Road',
    'Wankaner',
    'Maliya',
    'Tankara',
    'Halvad',
    'Ravapar',
    'Other',
];

$allowedStatuses = ['Published', 'Draft', 'Unpublished'];
$maxGalleryImages = 8;
$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'uploads' . DIRECTORY_SEPARATOR . 'activities';

if (
    !is_dir($uploadDirectory)
    && !mkdir($uploadDirectory, 0755, true)
    && !is_dir($uploadDirectory)
) {
    error_log('Could not create activity upload directory: ' . $uploadDirectory);
}

if (empty($_SESSION['activity_manage_csrf_token'])) {
    $_SESSION['activity_manage_csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * Process update and delete requests.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['activity_manage_csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        setActivityManageToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        setActivityManageToast('Invalid activity record.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'delete') {
        try {
            $featuredStmt = $pdo->prepare(
                'SELECT featured_image FROM activities WHERE id = :id'
            );
            $featuredStmt->execute([':id' => $id]);
            $featuredPath = $featuredStmt->fetchColumn();

            $galleryStmt = $pdo->prepare(
                'SELECT image_path FROM activity_gallery_images WHERE activity_id = :id'
            );
            $galleryStmt->execute([':id' => $id]);
            $galleryPaths = $galleryStmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM activity_gallery_images WHERE activity_id = :id')
                ->execute([':id' => $id]);
            $pdo->prepare('DELETE FROM activity_translations WHERE activity_id = :id')
                ->execute([':id' => $id]);

            $deleteStmt = $pdo->prepare('DELETE FROM activities WHERE id = :id');
            $deleteStmt->execute([':id' => $id]);

            if ($deleteStmt->rowCount() < 1) {
                $pdo->rollBack();
                setActivityManageToast('That activity was not found.', 'error');
            } else {
                $pdo->commit();

                if (is_string($featuredPath) && $featuredPath !== '') {
                    activityManageRemoveImage($featuredPath);
                }

                foreach ($galleryPaths as $path) {
                    if (is_string($path) && $path !== '') {
                        activityManageRemoveImage($path);
                    }
                }

                setActivityManageToast('Activity deleted successfully.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Activity delete failed: ' . $e->getMessage());
            setActivityManageToast('Could not delete the activity. Please try again.', 'error');
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action !== 'update') {
        setActivityManageToast('Unknown action.', 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $category = trim((string) ($_POST['category'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $activityDate = trim((string) ($_POST['activity_date'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));

    $postedTranslations = is_array($_POST['translations'] ?? null)
        ? $_POST['translations']
        : [];

    $translations = [];
    $errors = [];

    foreach ($languages as $language => $label) {
        $input = is_array($postedTranslations[$language] ?? null)
            ? $postedTranslations[$language]
            : [];

        $title = activityManagePlainText((string) ($input['title'] ?? ''), 200);
        $shortDescription = activityManagePlainText(
            (string) ($input['short_description'] ?? ''),
            300
        );
        $contextBackground = activityManageCleanHtml(
            (string) ($input['context_background'] ?? '')
        );
        $description = activityManageCleanHtml(
            (string) ($input['description'] ?? '')
        );
        $imageCaption = activityManagePlainText(
            (string) ($input['image_caption'] ?? ''),
            255
        );

        if ($title === '') {
            $errors[] = $label . ': title is required.';
        }

        if ($shortDescription === '') {
            $errors[] = $label . ': short description is required.';
        }

        if (trim(strip_tags($contextBackground)) === '') {
            $errors[] = $label . ': context and background is required.';
        }

        if (trim(strip_tags($description)) === '') {
            $errors[] = $label . ': detailed description is required.';
        }

        $translations[$language] = [
            'title' => $title,
            'short_description' => $shortDescription,
            'context_background' => $contextBackground,
            'description' => $description,
            'image_caption' => $imageCaption,
        ];
    }

    if (!in_array($category, $allowedCategories, true)) {
        $errors[] = 'Select a valid category.';
    }

    if (!in_array($area, $allowedAreas, true)) {
        $errors[] = 'Select a valid area / taluka.';
    }

    if (!activityManageValidDate($activityDate)) {
        $errors[] = 'Enter a valid activity date.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Select Published, Draft, or Unpublished status.';
    }

    $featuredFile = $_FILES['featured_image'] ?? null;
    $hasFeaturedUpload = is_array($featuredFile)
        && isset($featuredFile['error'])
        && $featuredFile['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasFeaturedUpload) {
        $imageError = activityManageValidateImage($featuredFile, 'Featured image');
        if ($imageError !== null) {
            $errors[] = $imageError;
        }
    }

    $galleryFiles = [];
    $galleryUpload = $_FILES['gallery_images'] ?? null;

    if (
        is_array($galleryUpload)
        && isset($galleryUpload['name'])
        && is_array($galleryUpload['name'])
    ) {
        foreach ($galleryUpload['name'] as $index => $name) {
            if ((string) $name === '') {
                continue;
            }

            $file = [
                'name' => $galleryUpload['name'][$index] ?? '',
                'type' => $galleryUpload['type'][$index] ?? '',
                'tmp_name' => $galleryUpload['tmp_name'][$index] ?? '',
                'error' => $galleryUpload['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $galleryUpload['size'][$index] ?? 0,
            ];

            $imageError = activityManageValidateImage(
                $file,
                'Gallery image ' . (count($galleryFiles) + 1)
            );

            if ($imageError !== null) {
                $errors[] = $imageError;
            }

            $galleryFiles[] = $file;
        }
    }

    if (count($galleryFiles) > $maxGalleryImages) {
        $errors[] = 'You can upload no more than ' . $maxGalleryImages . ' gallery images at a time.';
    }

    if ($errors) {
        setActivityManageToast(implode(' ', $errors), 'error');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $newDiskFiles = [];
    $oldFeaturedPath = null;

    try {
        $pdo->beginTransaction();

        $existsStmt = $pdo->prepare('SELECT id FROM activities WHERE id = :id');
        $existsStmt->execute([':id' => $id]);

        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Activity record was not found.');
        }

        $featuredPath = null;

        if ($hasFeaturedUpload) {
            $oldFeaturedStmt = $pdo->prepare(
                'SELECT featured_image FROM activities WHERE id = :id'
            );
            $oldFeaturedStmt->execute([':id' => $id]);
            $oldFeaturedPath = $oldFeaturedStmt->fetchColumn() ?: null;

            $mime = getimagesize($featuredFile['tmp_name'])['mime'] ?? '';
            $storedFeatured = activityManageStoreImage(
                $featuredFile,
                $uploadDirectory,
                $mime
            );
            $newDiskFiles[] = $storedFeatured['disk_path'];
            $featuredPath = $storedFeatured['path'];
        }

        $galleryCountStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM activity_gallery_images WHERE activity_id = :id'
        );
        $galleryCountStmt->execute([':id' => $id]);
        $currentGalleryCount = (int) $galleryCountStmt->fetchColumn();

        if ($currentGalleryCount + count($galleryFiles) > $maxGalleryImages) {
            throw new RuntimeException(
                'This activity cannot have more than ' . $maxGalleryImages . ' gallery images.'
            );
        }

        $gujarati = $translations['gu'];

        if ($featuredPath !== null) {
            $activityUpdate = $pdo->prepare(
                'UPDATE activities
                 SET title = :title,
                     description = :description,
                     category = :category,
                     area = :area,
                     activity_date = :activity_date,
                     status = :status,
                     featured_image = :featured_image
                 WHERE id = :id'
            );

            $activityUpdate->execute([
                ':title' => $gujarati['title'],
                ':description' => $gujarati['description'],
                ':category' => $category,
                ':area' => $area,
                ':activity_date' => $activityDate,
                ':status' => $status,
                ':featured_image' => $featuredPath,
                ':id' => $id,
            ]);
        } else {
            $activityUpdate = $pdo->prepare(
                'UPDATE activities
                 SET title = :title,
                     description = :description,
                     category = :category,
                     area = :area,
                     activity_date = :activity_date,
                     status = :status
                 WHERE id = :id'
            );

            $activityUpdate->execute([
                ':title' => $gujarati['title'],
                ':description' => $gujarati['description'],
                ':category' => $category,
                ':area' => $area,
                ':activity_date' => $activityDate,
                ':status' => $status,
                ':id' => $id,
            ]);
        }

        $translationCheck = $pdo->prepare(
            'SELECT 1 FROM activity_translations
             WHERE activity_id = :activity_id AND language = :language'
        );

        $translationUpdate = $pdo->prepare(
            'UPDATE activity_translations
             SET title = :title,
                 short_description = :short_description,
                 context_background = :context_background,
                 description = :description,
                 image_caption = :image_caption
             WHERE activity_id = :activity_id AND language = :language'
        );

        $translationInsert = $pdo->prepare(
            'INSERT INTO activity_translations
                (activity_id, language, title, short_description, context_background,
                 description, image_caption)
             VALUES
                (:activity_id, :language, :title, :short_description, :context_background,
                 :description, :image_caption)'
        );

        foreach ($translations as $language => $translation) {
            $values = [
                ':activity_id' => $id,
                ':language' => $language,
                ':title' => $translation['title'],
                ':short_description' => $translation['short_description'],
                ':context_background' => $translation['context_background'],
                ':description' => $translation['description'],
                ':image_caption' => $translation['image_caption'] !== ''
                    ? $translation['image_caption']
                    : null,
            ];

            $translationCheck->execute([
                ':activity_id' => $id,
                ':language' => $language,
            ]);

            if ($translationCheck->fetchColumn()) {
                $translationUpdate->execute($values);
            } else {
                $translationInsert->execute($values);
            }
        }

        if ($galleryFiles) {
            $maxOrderStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1)
                 FROM activity_gallery_images
                 WHERE activity_id = :id'
            );
            $maxOrderStmt->execute([':id' => $id]);
            $sortOrder = (int) $maxOrderStmt->fetchColumn() + 1;

            $galleryInsert = $pdo->prepare(
                'INSERT INTO activity_gallery_images
                    (activity_id, image_path, sort_order)
                 VALUES (:activity_id, :image_path, :sort_order)'
            );

            foreach ($galleryFiles as $file) {
                $mime = getimagesize($file['tmp_name'])['mime'] ?? '';
                $storedGallery = activityManageStoreImage(
                    $file,
                    $uploadDirectory,
                    $mime
                );
                $newDiskFiles[] = $storedGallery['disk_path'];

                $galleryInsert->execute([
                    ':activity_id' => $id,
                    ':image_path' => $storedGallery['path'],
                    ':sort_order' => $sortOrder++,
                ]);
            }
        }

        $pdo->commit();

        if (is_string($oldFeaturedPath) && $oldFeaturedPath !== '') {
            activityManageRemoveImage($oldFeaturedPath);
        }

        setActivityManageToast('Activity and all translations updated successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        foreach ($newDiskFiles as $diskPath) {
            if (is_file($diskPath)) {
                @unlink($diskPath);
            }
        }

        error_log('Activity update failed: ' . $e->getMessage());
        setActivityManageToast(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Could not update the activity. Please try again.',
            'error'
        );
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$toastMessage = $_SESSION['activity_manage_toast_message'] ?? '';
$toastType = $_SESSION['activity_manage_toast_type'] ?? 'success';
unset($_SESSION['activity_manage_toast_message'], $_SESSION['activity_manage_toast_type']);

$activities = [];
$loadError = false;

try {
    $activityStmt = $pdo->query(
        'SELECT id, category, area, activity_date, status, featured_image
         FROM activities
         ORDER BY activity_date DESC, id DESC'
    );
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

    $translationStmt = $pdo->query(
        'SELECT activity_id, language, title, short_description,
                context_background, description, image_caption
         FROM activity_translations
         WHERE language IN ("gu", "en", "hi")'
    );

    $translationsByActivity = [];
    foreach ($translationStmt->fetchAll(PDO::FETCH_ASSOC) as $translation) {
        $translationsByActivity[(int) $translation['activity_id']][$translation['language']] = $translation;
    }

    foreach ($activities as &$activity) {
        $activity['translations'] = $translationsByActivity[(int) $activity['id']] ?? [];
    }
    unset($activity);
} catch (PDOException $e) {
    error_log('Could not load activities: ' . $e->getMessage());
    $loadError = true;
}

$activityJson = json_encode(
    $activities,
    JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
);

if ($activityJson === false) {
    $activityJson = '[]';
}

$csrfToken = (string) $_SESSION['activity_manage_csrf_token'];
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
        <section class="adm-page" id="page-activities-manage">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-bullhorn"></i>
              <span>Manage Activities</span>
              <span class="adm-chip">Activities &amp; News</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="activitySearch"
                  placeholder="Search activities..."
                  aria-label="Search activities"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-activity.php">
                  <i class="fa-solid fa-plus"></i> Add Activity
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load activities. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="activityResultInfo"><?= count($activities) ?> activity/activities found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Title (English)</th>
                      <th>Category</th>
                      <th>Area / Taluka</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$activities): ?>
                      <tr class="empty-row">
                        <td colspan="7">No activities have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($activities as $index => $activity): ?>
                        <?php
                        $dateDisplay = (string) ($activity['activity_date'] ?? '');
                        if ($dateDisplay !== '') {
                            $dateObject = DateTime::createFromFormat('Y-m-d', $dateDisplay);
                            if ($dateObject) {
                                $dateDisplay = $dateObject->format('d M Y');
                            }
                        }

                        $englishTitle = $activity['translations']['en']['title'] ?? '';
                        ?>
                        <tr class="activity-row">
                          <td class="activity-number"><?= $index + 1 ?></td>
                          <td><?= activityManageEscape($englishTitle ?: '(English translation not added)') ?></td>
                          <td><?= activityManageEscape($activity['category']) ?></td>
                          <td><?= activityManageEscape($activity['area']) ?></td>
                          <td><?= activityManageEscape($dateDisplay) ?></td>
                          <td><?= activityManageEscape($activity['status']) ?></td>
                          <td class="is-actions">
                            <button
                              type="button"
                              class="adm-btn adm-btn--sm edit-activity"
                              data-id="<?= (int) $activity['id'] ?>"
                            >
                              <i class="fa-solid fa-pen"></i> Update
                            </button>

                            <form method="post" class="delete-activity-form">
                              <input type="hidden" name="csrf_token" value="<?= activityManageEscape($csrfToken) ?>">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int) $activity['id'] ?>">
                              <button type="submit" class="adm-btn adm-btn--danger adm-btn--sm">
                                <i class="fa-solid fa-trash"></i> Delete
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="noActivitySearchResults" hidden>
                      <td colspan="7">No matching activities found.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span id="activityPageInfo"></span>

                <div class="adm-pager">
                  <button type="button" class="adm-btn adm-btn--sm" id="activityPreviousPage">
                    <i class="fa-solid fa-chevron-left"></i> Previous
                  </button>
                  <span id="activityPageNumber"></span>
                  <button type="button" class="adm-btn adm-btn--sm" id="activityNextPage">
                    Next <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </main>
  </div>

  <div class="adm-modal" id="editActivityModal" hidden>
    <div
      class="adm-modal__box is-wide"
      role="dialog"
      aria-modal="true"
      aria-labelledby="editActivityTitle"
    >
      <div class="adm-modal__head">
        <h3 id="editActivityTitle">
          <i class="fa-solid fa-pen-to-square"></i> Update Activity
        </h3>
        <button type="button" class="adm-x" data-close-activity-modal aria-label="Close">&times;</button>
      </div>

      <div class="adm-modal__body">
        <form class="adm-form" id="editActivityForm" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= activityManageEscape($csrfToken) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-activity-id">

          <div class="adm-field">
            <label for="edit-activity-category">Category *</label>
            <select id="edit-activity-category" name="category" required>
              <option value="">Select...</option>
              <?php foreach ($allowedCategories as $category): ?>
                <option value="<?= activityManageEscape($category) ?>"><?= activityManageEscape($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="adm-field">
            <label for="edit-activity-area">Area / Taluka *</label>
            <select id="edit-activity-area" name="area" required>
              <option value="">Select...</option>
              <?php foreach ($allowedAreas as $area): ?>
                <option value="<?= activityManageEscape($area) ?>"><?= activityManageEscape($area) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="adm-field">
            <label for="edit-activity-date">Date *</label>
            <input id="edit-activity-date" name="activity_date" type="date" required>
          </div>

          <div class="adm-field">
            <label for="edit-activity-status">Status *</label>
            <select id="edit-activity-status" name="status" required>
              <option value="">Select...</option>
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= activityManageEscape($status) ?>"><?= activityManageEscape($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="adm-field is-wide">
            <fieldset class="activity-language-fieldset">
              <legend>Translations — update each language</legend>

              <div class="activity-language-tabs" role="tablist">
                <?php foreach ($languages as $language => $label): ?>
                  <button
                    type="button"
                    class="adm-btn adm-btn--sm activity-language-tab<?= $language === 'gu' ? ' is-active' : '' ?>"
                    role="tab"
                    aria-selected="<?= $language === 'gu' ? 'true' : 'false' ?>"
                    data-language-tab="<?= activityManageEscape($language) ?>"
                  ><?= activityManageEscape($label) ?></button>
                <?php endforeach; ?>
              </div>

              <?php foreach ($languages as $language => $label): ?>
                <div
                  class="activity-language-panel"
                  data-language-panel="<?= activityManageEscape($language) ?>"
                  <?= $language !== 'gu' ? 'hidden' : '' ?>
                >
                  <div class="adm-field is-wide">
                    <label for="edit-title-<?= activityManageEscape($language) ?>">
                      Title (<?= activityManageEscape($label) ?>) *
                    </label>
                    <input
                      id="edit-title-<?= activityManageEscape($language) ?>"
                      name="translations[<?= activityManageEscape($language) ?>][title]"
                      type="text"
                      maxlength="200"
                      required
                    >
                  </div>

                  <div class="adm-field is-wide">
                    <label for="edit-short-<?= activityManageEscape($language) ?>">
                      Short description (<?= activityManageEscape($label) ?>) *
                    </label>
                    <textarea
                      id="edit-short-<?= activityManageEscape($language) ?>"
                      name="translations[<?= activityManageEscape($language) ?>][short_description]"
                      rows="2"
                      maxlength="300"
                      required
                    ></textarea>
                  </div>

                  <div class="adm-field is-wide">
                    <label>Context &amp; background (<?= activityManageEscape($label) ?>) *</label>
                    <div class="adm-editor">
                      <div class="adm-editor__bar">
                        <button type="button" data-editor-command="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
                        <button type="button" data-editor-command="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
                        <button type="button" data-editor-command="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
                        <button type="button" data-editor-command="formatBlock:h2" title="Heading"><i class="fa-solid fa-heading"></i></button>
                        <button type="button" data-editor-command="formatBlock:p" title="Paragraph"><i class="fa-solid fa-paragraph"></i></button>
                        <button type="button" data-editor-command="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
                        <button type="button" data-editor-command="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
                      </div>
                      <div
                        class="adm-editor__area"
                        contenteditable="true"
                        data-rich-editor="context-<?= activityManageEscape($language) ?>"
                        aria-label="Context and background in <?= activityManageEscape($label) ?>"
                      ></div>
                      <textarea
                        name="translations[<?= activityManageEscape($language) ?>][context_background]"
                        data-rich-value="context-<?= activityManageEscape($language) ?>"
                        hidden
                      ></textarea>
                    </div>
                  </div>

                  <div class="adm-field is-wide">
                    <label>Detailed description (<?= activityManageEscape($label) ?>) *</label>
                    <div class="adm-editor">
                      <div class="adm-editor__bar">
                        <button type="button" data-editor-command="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
                        <button type="button" data-editor-command="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
                        <button type="button" data-editor-command="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
                        <button type="button" data-editor-command="formatBlock:h2" title="Heading"><i class="fa-solid fa-heading"></i></button>
                        <button type="button" data-editor-command="formatBlock:p" title="Paragraph"><i class="fa-solid fa-paragraph"></i></button>
                        <button type="button" data-editor-command="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
                        <button type="button" data-editor-command="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
                      </div>
                      <div
                        class="adm-editor__area"
                        contenteditable="true"
                        data-rich-editor="description-<?= activityManageEscape($language) ?>"
                        aria-label="Detailed description in <?= activityManageEscape($label) ?>"
                      ></div>
                      <textarea
                        name="translations[<?= activityManageEscape($language) ?>][description]"
                        data-rich-value="description-<?= activityManageEscape($language) ?>"
                        hidden
                      ></textarea>
                    </div>
                  </div>

                  <div class="adm-field is-wide">
                    <label for="edit-caption-<?= activityManageEscape($language) ?>">
                      Featured image caption (<?= activityManageEscape($label) ?>)
                    </label>
                    <input
                      id="edit-caption-<?= activityManageEscape($language) ?>"
                      name="translations[<?= activityManageEscape($language) ?>][image_caption]"
                      type="text"
                      maxlength="255"
                    >
                  </div>
                </div>
              <?php endforeach; ?>
            </fieldset>
          </div>

          <div class="adm-field is-wide">
            <label>Current featured image</label>
            <div id="currentFeaturedImage">No featured image</div>
          </div>

          <div class="adm-field is-wide">
            <label for="edit-activity-featured">Replace featured image (optional; maximum 1 MB)</label>
            <input
              id="edit-activity-featured"
              name="featured_image"
              type="file"
              accept="image/jpeg,image/png,image/webp"
            >
            <small>JPG, PNG, or WebP. Dimensions are not restricted.</small>
          </div>

          <div class="adm-field is-wide">
            <label for="edit-activity-gallery">Add gallery images (optional; maximum 1 MB each)</label>
            <input
              id="edit-activity-gallery"
              name="gallery_images[]"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              multiple
            >
            <small>Gallery images are added to the existing gallery. Maximum 8 gallery images per activity.</small>
          </div>
        </form>
      </div>

      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close-activity-modal>Cancel</button>
        <button type="submit" form="editActivityForm" class="adm-btn adm-btn--primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </div>
  </div>

  <div
    class="adm-toast"
    id="toast"
    role="status"
    aria-live="polite"
    data-message="<?= activityManageEscape($toastMessage) ?>"
    data-type="<?= activityManageEscape($toastType) ?>"
  ></div>

  <script type="application/json" id="activityData"><?= $activityJson ?></script>

  <style>
    .activity-language-fieldset {
      border: 0;
      padding: 0;
      margin: 0;
      min-width: 0;
    }

    .activity-language-fieldset legend {
      font-weight: 700;
      margin-bottom: 12px;
    }

    .activity-language-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 16px;
    }

    .activity-language-tab.is-active {
      background: #4a1480;
      color: #fff;
    }

    .activity-language-panel {
      display: grid;
      gap: 12px;
    }

    .activity-language-panel[hidden] {
      display: none;
    }

    .adm-editor__area {
      min-height: 150px;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
    }

    .adm-editor__area:focus {
      outline: 2px solid #6b2bbd;
      outline-offset: 1px;
    }
  </style>

  <script>
    (() => {
      const allRows = Array.from(document.querySelectorAll('.activity-row'));
      const searchInput = document.getElementById('activitySearch');
      const noSearchResults = document.getElementById('noActivitySearchResults');
      const pageSize = 10;
      let currentPage = 1;
      let filteredRows = allRows;

      function renderRows() {
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);

        allRows.forEach(row => {
          row.hidden = true;
        });

        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);

        pageRows.forEach((row, index) => {
          row.hidden = false;
          const numberCell = row.querySelector('.activity-number');
          if (numberCell) numberCell.textContent = String(start + index + 1);
        });

        if (noSearchResults) {
          noSearchResults.hidden = filteredRows.length !== 0 || allRows.length === 0;
        }

        const pageCountLabel = document.getElementById('activityPageNumber');
        const pageInfo = document.getElementById('activityPageInfo');
        const resultInfo = document.getElementById('activityResultInfo');
        const previousButton = document.getElementById('activityPreviousPage');
        const nextButton = document.getElementById('activityNextPage');

        if (pageCountLabel) {
          pageCountLabel.textContent = `Page ${currentPage} of ${pageCount}`;
        }

        if (pageInfo) {
          const first = filteredRows.length === 0 ? 0 : start + 1;
          const last = start + pageRows.length;
          pageInfo.textContent =
            `Showing ${first}–${last} of ${filteredRows.length} activity/activities`;
        }

        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} activity/activities found.`;
        }

        if (previousButton) previousButton.disabled = currentPage <= 1;
        if (nextButton) nextButton.disabled = currentPage >= pageCount;
      }

      searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        filteredRows = allRows.filter(row =>
          row.textContent.toLowerCase().includes(query)
        );
        currentPage = 1;
        renderRows();
      });

      document.getElementById('activityPreviousPage')?.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderRows();
        }
      });

      document.getElementById('activityNextPage')?.addEventListener('click', () => {
        if (currentPage < Math.ceil(filteredRows.length / pageSize)) {
          currentPage++;
          renderRows();
        }
      });

      const activityData = JSON.parse(
        document.getElementById('activityData')?.textContent || '[]'
      );
      const activityById = new Map(
        activityData.map(activity => [String(activity.id), activity])
      );

      const modal = document.getElementById('editActivityModal');
      const form = document.getElementById('editActivityForm');

      document.querySelectorAll('.edit-activity').forEach(button => {
        button.addEventListener('click', () => {
          const activity = activityById.get(button.dataset.id);
          if (!activity) return;

          document.getElementById('edit-activity-id').value = activity.id;
          document.getElementById('edit-activity-category').value = activity.category || '';
          document.getElementById('edit-activity-area').value = activity.area || '';
          document.getElementById('edit-activity-date').value = activity.activity_date || '';
          document.getElementById('edit-activity-status').value = activity.status || '';

          const translations = activity.translations || {};

          for (const language of ['gu', 'en', 'hi']) {
            const translation = translations[language] || {};

            document.getElementById(`edit-title-${language}`).value = translation.title || '';
            document.getElementById(`edit-short-${language}`).value =
              translation.short_description || '';
            document.getElementById(`edit-caption-${language}`).value =
              translation.image_caption || '';

            const contextEditor = document.querySelector(
              `[data-rich-editor="context-${language}"]`
            );
            const descriptionEditor = document.querySelector(
              `[data-rich-editor="description-${language}"]`
            );

            if (contextEditor) {
              contextEditor.innerHTML = translation.context_background || '';
            }

            if (descriptionEditor) {
              descriptionEditor.innerHTML = translation.description || '';
            }
          }

          const imageBox = document.getElementById('currentFeaturedImage');
          imageBox.replaceChildren();

          if (activity.featured_image) {
            const image = document.createElement('img');
            image.src = '../' + String(activity.featured_image).replace(/^\/+/, '');
            image.alt = 'Current featured image';
            image.style.maxWidth = '220px';
            image.style.maxHeight = '150px';
            image.style.objectFit = 'cover';
            imageBox.appendChild(image);
          } else {
            imageBox.textContent = 'No featured image';
          }

          const firstTab = document.querySelector('[data-language-tab="gu"]');
          firstTab?.click();
          modal.hidden = false;
        });
      });

      document.querySelectorAll('[data-language-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
          const language = tab.dataset.languageTab;

          document.querySelectorAll('[data-language-tab]').forEach(item => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
          });

          document.querySelectorAll('[data-language-panel]').forEach(panel => {
            panel.hidden = panel.dataset.languagePanel !== language;
          });
        });
      });

      document.querySelectorAll('[data-editor-command]').forEach(button => {
        button.addEventListener('click', () => {
          const editor = button.closest('.adm-editor')?.querySelector('[contenteditable="true"]');
          if (!editor) return;

          editor.focus();
          const command = button.dataset.editorCommand;

          if (command.startsWith('formatBlock:')) {
            document.execCommand('formatBlock', false, command.split(':')[1]);
          } else {
            document.execCommand(command, false, null);
          }
        });
      });

      form?.addEventListener('submit', () => {
        document.querySelectorAll('[data-rich-editor]').forEach(editor => {
          const target = document.querySelector(
            `[data-rich-value="${editor.dataset.richEditor}"]`
          );
          if (target) target.value = editor.innerHTML.trim();
        });
      });

      document.querySelectorAll('[data-close-activity-modal]').forEach(button => {
        button.addEventListener('click', () => {
          modal.hidden = true;
        });
      });

      modal?.addEventListener('click', event => {
        if (event.target === modal) modal.hidden = true;
      });

      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal) modal.hidden = true;
      });

      document.querySelectorAll('.delete-activity-form').forEach(deleteForm => {
        deleteForm.addEventListener('submit', event => {
          if (!window.confirm('Delete this activity, all its translations, and its uploaded images?')) {
            event.preventDefault();
          }
        });
      });

      const toast = document.getElementById('toast');
      if (toast?.dataset.message) {
        toast.textContent = toast.dataset.message;
        toast.classList.add('is-show');

        if (toast.dataset.type === 'error') {
          toast.classList.add('is-error');
        }

        window.setTimeout(() => {
          toast.classList.remove('is-show', 'is-error');
        }, 5000);
      }

      renderRows();
    })();
  </script>

  <script src="admin.js"></script>
</body>
</html>