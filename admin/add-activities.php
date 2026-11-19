<?php
require_once __DIR__ . '/inc/auth.php';
require_login();
require_once __DIR__ . '/inc/config.php';

function activityEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function activityToast(string $message, string $type = 'success'): void
{
    $_SESSION['activity_toast_message'] = $message;
    $_SESSION['activity_toast_type'] = $type;
}

function activityPlainText(string $value, int $maxLength): string
{
    $value = trim(strip_tags($value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function activityCleanHtml(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        return trim(strip_tags(
            $html,
            '<p><br><strong><b><em><i><u><s><h2><h3><blockquote><ul><ol><li><a>'
        ));
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');

    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="activity-root">'
        . $html .
        '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $dom->getElementById('activity-root');
    if (!$root) {
        return '';
    }

    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'a'];

    $cleanNode = function (DOMNode $node) use (&$cleanNode, $allowed): string {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            if ($href !== '' && preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
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
function validateActivityImage(array $file, string $label, int $maxBytes): ?string
{
    if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
        return $label . ': upload data is missing.';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ': file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => $label . ': upload was incomplete; try again.',
            UPLOAD_ERR_NO_FILE => $label . ': please choose an image.',
            default => $label . ': upload failed.',
        };
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $label . ': invalid uploaded file.';
    }

    if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
        return $label . ': file must be 1 MB or smaller.';
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
    if ($imageInfo === false) {
        return $label . ': the uploaded file is not a valid image.';
    }

    if (($imageInfo['mime'] ?? '') !== $allowedTypes[$extension]) {
        return $label . ': file extension does not match the image type.';
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) !== $allowedTypes[$extension]) {
            return $label . ': image file type could not be verified.';
        }
    }

    return null;
}

function storeActivityImage(array $file, string $directory, string $mime): array
{
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => throw new RuntimeException('Unsupported image type.'),
    };

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    @chmod($destination, 0644);

    return [
        'path' => 'uploads/activities/' . $filename,
        'disk_path' => $destination,
    ];
}

$pdo = db();
$currentAdmin = current_admin();
$createdBy = isset($currentAdmin['id']) ? (int) $currentAdmin['id'] : null;

if (empty($_SESSION['add_activity_csrf'])) {
    $_SESSION['add_activity_csrf'] = bin2hex(random_bytes(32));
}

$languages = [
    'gu' => 'ગુજરાતી',
    'en' => 'English',
    'hi' => 'हिन्दी',
];

$categories = [
    'Event',
    'Donation & Charity',
    'Blood Drive',
    'Medical Camp',
    'Education',
    'Environment',
    'Awareness',
];

$areas = [
    'Morbi City',
    'Shanala Road',
    'Wankaner',
    'Maliya',
    'Tankara',
    'Halvad',
    'Ravapar',
    'Other',
];

$maxGalleryImages = 8;
$maxFileSize = 1 * 1024 * 1024; // 1 MB per image

$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'activities';
if (
    !is_dir($uploadDirectory)
    && !mkdir($uploadDirectory, 0755, true)
    && !is_dir($uploadDirectory)
) {
    error_log('Could not create activity upload dir: ' . $uploadDirectory);
    http_response_code(500);
    exit('Image upload directory is unavailable.');
}

$old = $_SESSION['add_activity_old'] ?? [];
unset($_SESSION['add_activity_old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['add_activity_csrf'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        activityToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: add-activity.php');
        exit;
    }

    $old = $_POST;
    unset($old['csrf_token']);

    $category = trim((string) ($_POST['category'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $activityDate = trim((string) ($_POST['activity_date'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));

    $errors = [];

    if (!in_array($category, $categories, true)) {
        $errors[] = 'Select a valid category.';
    }

    if (!in_array($area, $areas, true)) {
        $errors[] = 'Select a valid area or taluka.';
    }

    $dateObj = DateTime::createFromFormat('!Y-m-d', $activityDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $activityDate) {
        $errors[] = 'Enter a valid activity date.';
    }

   if (!in_array($status, ['Published', 'Draft', 'Unpublished'], true)) {
        $errors[] = 'Select Published or Draft.';
    }

    $postedTranslations = is_array($_POST['translations'] ?? null) ? $_POST['translations'] : [];
    $translations = [];

    foreach ($languages as $language => $label) {
        $input = is_array($postedTranslations[$language] ?? null) ? $postedTranslations[$language] : [];

        $title = activityPlainText((string) ($input['title'] ?? ''), 200);
        $shortDescription = activityPlainText((string) ($input['short_description'] ?? ''), 300);
        $description = activityCleanHtml(
            (string) ($input['description'] ?? '')
        );

        if ($title === '') {
            $errors[] = $label . ': title is required.';
        }

        if (trim($shortDescription) === '') {
            $errors[] = $label . ': short description is required.';
        }

        if (trim(strip_tags($description)) === '') {
            $errors[] = $label . ': description is required.';
        }

        $translations[$language] = [
            'title' => $title,
            'short_description' => $shortDescription,
            'description' => $description,
        ];
    }

    $featuredFile = $_FILES['featured_image'] ?? null;
    if (!is_array($featuredFile) || !isset($featuredFile['tmp_name'])) {
        $errors[] = 'Featured image is required.';
    } else {
            $imageError = validateActivityImage(
                $featuredFile,
                'Featured image',
                $maxFileSize
            );
        if ($imageError !== null) {
            $errors[] = $imageError;
        }
    }

    $galleryFiles = $_FILES['gallery_images'] ?? null;
    $galleryItems = [];

    if (is_array($galleryFiles) && isset($galleryFiles['name']) && is_array($galleryFiles['name'])) {
        $selected = array_filter($galleryFiles['name'], static fn($name) => trim((string) $name) !== '');
        if (count($selected) > $maxGalleryImages) {
            $errors[] = 'Choose no more than ' . $maxGalleryImages . ' gallery images.';
        }

        foreach ($galleryFiles['name'] as $index => $name) {
            if ((string) $name === '') {
                continue;
            }

            $file = [
                'name' => $galleryFiles['name'][$index] ?? '',
                'type' => $galleryFiles['type'][$index] ?? '',
                'tmp_name' => $galleryFiles['tmp_name'][$index] ?? '',
                'error' => $galleryFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $galleryFiles['size'][$index] ?? 0,
            ];

              $imageError = validateActivityImage(
                  $file,
                  'Gallery image ' . ($index + 1),
                  $maxFileSize
              );

            if ($imageError !== null) {
                $errors[] = $imageError;
            } else {
                $galleryItems[] = $file;
            }
        }
    }

    if ($errors) {
        $_SESSION['add_activity_old'] = $old;
        activityToast(implode(' ', $errors), 'error');
        header('Location: add-activities.php');
        exit;
    }

    $savedDiskFiles = [];
    $featuredStored = null;
    $galleryStored = [];

    try {
        $featuredMime = getimagesize($featuredFile['tmp_name'])['mime'] ?? '';
        $featuredStored = storeActivityImage($featuredFile, $uploadDirectory, $featuredMime);
        $savedDiskFiles[] = $featuredStored['disk_path'];

        foreach ($galleryItems as $galleryFile) {
            $galleryMime = getimagesize($galleryFile['tmp_name'])['mime'] ?? '';
            $stored = storeActivityImage($galleryFile, $uploadDirectory, $galleryMime);
            $savedDiskFiles[] = $stored['disk_path'];
            $galleryStored[] = $stored;
        }

        $pdo->beginTransaction();

        $gu = $translations['gu'];

        $activityStmt = $pdo->prepare(
            'INSERT INTO activities
                (title, category, area, activity_date, status, description, featured_image, created_by)
             VALUES
                (:title, :category, :area, :activity_date, :status, :description, :featured_image, :created_by)'
        );

        $activityStmt->execute([
            ':title' => $gu['title'],
            ':category' => $category,
            ':area' => $area,
            ':activity_date' => $activityDate,
            ':status' => $status,
            ':description' => $gu['description'],
            ':featured_image' => $featuredStored['path'],
            ':created_by' => $createdBy,
        ]);

        $activityId = (int) $pdo->lastInsertId();

        if ($activityId < 1) {
            throw new RuntimeException('Could not create activity record.');
        }

        $translationStmt = $pdo->prepare(
            'INSERT INTO activity_translations
                (activity_id, language, title, short_description, description)
            VALUES
                (:activity_id, :language, :title, :short_description, :description)'
        );

        foreach ($translations as $language => $translation) {
          $translationStmt->execute([
              ':activity_id' => $activityId,
              ':language' => $language,
              ':title' => $translation['title'],
              ':short_description' => $translation['short_description'],
              ':description' => $translation['description'],
          ]);
        }

        if (!empty($galleryStored)) {
            $galleryStmt = $pdo->prepare(
                'INSERT INTO activity_gallery_images (activity_id, image_path, sort_order)
                 VALUES (:activity_id, :image_path, :sort_order)'
            );

            foreach ($galleryStored as $index => $image) {
                $galleryStmt->execute([
                    ':activity_id' => $activityId,
                    ':image_path' => $image['path'],
                    ':sort_order' => $index,
                ]);
            }
        }

        $pdo->commit();

        unset($_SESSION['add_activity_old']);
        activityToast('Activity added successfully.');
        header('Location: add-activities.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        foreach ($savedDiskFiles as $diskPath) {
            if (is_file($diskPath)) {
                @unlink($diskPath);
            }
        }

        error_log('Add activity failed: ' . $e->getMessage());
        $_SESSION['add_activity_old'] = $old;
        activityToast('Could not save the activity. Check the PHP error log.', 'error');
        header('Location: add-activities.php');
        exit;
    }
}

$toastMessage = $_SESSION['activity_toast_message'] ?? '';
$toastType = $_SESSION['activity_toast_type'] ?? 'success';
unset($_SESSION['activity_toast_message'], $_SESSION['activity_toast_type']);

$oldTranslations = is_array($old['translations'] ?? null) ? $old['translations'] : [];
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
      <section class="adm-page" id="page-activities-add" data-module="activities">
        <div class="adm-page-head">
          <h2><i class="fa-solid fa-bullhorn"></i> Add New Activity <span class="adm-chip">Activities &amp; News</span></h2>
        </div>

        <div class="adm-panel">
          <div class="adm-panel__head">
            <h3>Activity Details</h3>
            <a class="adm-btn adm-btn--sm" href="#/activities/list"><i class="fa-solid fa-table-list"></i> View all Activities</a>
          </div>

          <div class="adm-panel__body">
            <form method="post" class="adm-form" enctype="multipart/form-data" id="form-activities">
              <input type="hidden" name="csrf_token" value="<?= activityEscape($_SESSION['add_activity_csrf']) ?>">

              <div class="adm-field">
                <label for="activity-category">Category *</label>
                <select id="activity-category" name="category" required>
                  <option value="">Select...</option>
                  <?php foreach ($categories as $option): ?>
                    <option value="<?= activityEscape($option) ?>" <?= ($old['category'] ?? '') === $option ? 'selected' : '' ?>>
                      <?= activityEscape($option) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="adm-field">
                <label for="activity-area">Area / Taluka *</label>
                <select id="activity-area" name="area" required>
                  <option value="">Select...</option>
                  <?php foreach ($areas as $option): ?>
                    <option value="<?= activityEscape($option) ?>" <?= ($old['area'] ?? '') === $option ? 'selected' : '' ?>>
                      <?= activityEscape($option) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="adm-field">
                <label for="activity-date">Date *</label>
                <input id="activity-date" name="activity_date" type="date" required value="<?= activityEscape($old['activity_date'] ?? '') ?>">
              </div>

              <div class="adm-field">
                <label for="activity-status">Status *</label>
                <select id="activity-status" name="status" required>
                  <option value="">Select...</option>
                 <option value="Published" <?= ($old['status'] ?? '') === 'Published' ? 'selected' : '' ?>>Published</option>
                <option value="Draft" <?= ($old['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Unpublished" <?= ($old['status'] ?? '') === 'Unpublished' ? 'selected' : '' ?>>Unpublished</option>
                </select>
              </div>

              <div class="adm-field is-wide">
                <label for="featured-image">
                  Featured image * — Recommended: 1200 × 800 px | size must be : less than 1 MB
                </label>
                <input id="featured-image" type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" required>
                <small>JPG, PNG, or WebP. Exact dimensions are validated.</small>
              </div>

              <div class="adm-field is-wide">
                <label for="gallery-images">
                  Gallery images — Recommended: 1000 × 667 px | size must be : less than 1 MB
                </label>
                <input id="gallery-images" type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple>
                <small>Optional; maximum <?= $maxGalleryImages ?> images. Gallery images do not use captions.</small>
              </div>

              <div class="adm-field is-wide">
                <fieldset class="activity-language-fieldset">
                  <legend>Translated activity content</legend>

                  <div class="activity-language-tabs" role="tablist">
                    <?php foreach ($languages as $language => $label): ?>
                      <button type="button"
                              class="adm-btn adm-btn--sm activity-language-tab<?= $language === 'gu' ? ' is-active' : '' ?>"
                              role="tab"
                              aria-selected="<?= $language === 'gu' ? 'true' : 'false' ?>"
                              data-language-tab="<?= activityEscape($language) ?>">
                        <?= activityEscape($label) ?>
                      </button>
                    <?php endforeach; ?>
                  </div>

                  <?php foreach ($languages as $language => $label):
                    $translation = is_array($oldTranslations[$language] ?? null) ? $oldTranslations[$language] : [];
                  ?>
                    <div class="activity-language-panel" data-language-panel="<?= activityEscape($language) ?>" <?= $language !== 'gu' ? 'hidden' : '' ?>>
                      <div class="adm-field is-wide">
                        <label for="title-<?= $language ?>">Title (<?= activityEscape($label) ?>) *</label>
                        <input id="title-<?= $language ?>"
                               name="translations[<?= $language ?>][title]"
                               type="text"
                               maxlength="200"
                               value="<?= activityEscape($translation['title'] ?? '') ?>"
                               required>
                      </div>

                      <div class="adm-field is-wide">
                        <label for="short-<?= $language ?>">Short description (<?= activityEscape($label) ?>) * — 1–2 lines</label>
                        <textarea id="short-<?= $language ?>"
                                  name="translations[<?= $language ?>][short_description]"
                                  rows="2"
                                  maxlength="300"
                                  required><?= activityEscape($translation['short_description'] ?? '') ?></textarea>
                      </div>

                      <div class="adm-field is-wide">
                      <label>Description (<?= activityEscape($label) ?>) *</label>

                      <div class="adm-editor">
                        <div class="adm-editor__bar">
                          <button type="button" data-editor-command="bold" title="Bold">
                            <i class="fa-solid fa-bold"></i>
                          </button>

                          <button type="button" data-editor-command="italic" title="Italic">
                            <i class="fa-solid fa-italic"></i>
                          </button>

                          <button type="button" data-editor-command="underline" title="Underline">
                            <i class="fa-solid fa-underline"></i>
                          </button>

                          <button type="button" data-editor-command="formatBlock:h2" title="Heading">
                            <i class="fa-solid fa-heading"></i>
                          </button>

                          <button type="button" data-editor-command="formatBlock:p" title="Paragraph">
                            <i class="fa-solid fa-paragraph"></i>
                          </button>

                          <button type="button" data-editor-command="insertUnorderedList" title="Bullet list">
                            <i class="fa-solid fa-list-ul"></i>
                          </button>

                          <button type="button" data-editor-command="insertOrderedList" title="Numbered list">
                            <i class="fa-solid fa-list-ol"></i>
                          </button>

                          <button type="button" data-editor-command="createLink" title="Insert link">
                            <i class="fa-solid fa-link"></i>
                          </button>
                        </div>

                        <div
                          class="adm-editor__area"
                          contenteditable="true"
                          data-description-editor="<?= activityEscape($language) ?>"
                          aria-label="Description in <?= activityEscape($label) ?>"
                        ><?= activityCleanHtml((string) ($translation['description'] ?? '')) ?></div>

                        <textarea
                          name="translations[<?= $language ?>][description]"
                          data-description-value="<?= activityEscape($language) ?>"
                          hidden
                        ><?= activityEscape($translation['description'] ?? '') ?></textarea>
                      </div>
                    </div>
                    </div>
                  <?php endforeach; ?>
                </fieldset>
              </div>

              <div class="adm-form__actions is-wide">
                <button type="submit" class="adm-btn adm-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Activity</button>
                <button type="reset" class="adm-btn">Reset</button>
              </div>
            </form>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<div class="adm-toast" id="toast" role="status" aria-live="polite"
     data-message="<?= activityEscape($toastMessage) ?>"
     data-type="<?= activityEscape($toastType) ?>"></div>

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
  min-height: 180px;
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
  const tabs = document.querySelectorAll('[data-language-tab]');
  const panels = document.querySelectorAll('[data-language-panel]');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const language = tab.dataset.languageTab;

      tabs.forEach(item => {
        const active = item === tab;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach(panel => {
        panel.hidden = panel.dataset.languagePanel !== language;
      });
    });
  });

  document.querySelectorAll('[data-editor-command]').forEach(button => {
    button.addEventListener('click', () => {
      const editor = button
        .closest('.adm-editor')
        ?.querySelector('[contenteditable="true"]');

      if (!editor) return;

      editor.focus();
      const command = button.dataset.editorCommand;

      if (command === 'createLink') {
        const url = prompt('Enter a link URL:');
        if (url) {
          document.execCommand('createLink', false, url);
        }
      } else if (command.startsWith('formatBlock:')) {
        document.execCommand(
          'formatBlock',
          false,
          command.split(':')[1]
        );
      } else {
        document.execCommand(command, false, null);
      }
    });
  });

  document.getElementById('form-activities')?.addEventListener('submit', () => {
    document.querySelectorAll('[data-description-editor]').forEach(editor => {
      const language = editor.dataset.descriptionEditor;
      const target = document.querySelector(
        `[data-description-value="${language}"]`
      );

      if (target) {
        target.value = editor.innerHTML.trim();
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

    setTimeout(() => {
      toast.classList.remove('is-show', 'is-error');
    }, 6000);
  }
})();
</script>


<script src="admin.js"></script>
</body>
</html>