<?php
require_once __DIR__ . '/inc/auth.php';
require_login();
require_once __DIR__ . '/inc/config.php';

function restoreEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function restoreDeny(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

function setRestoreToast(string $message, string $type = 'success'): void
{
    $_SESSION['backup_restore_toast_message'] = $message;
    $_SESSION['backup_restore_toast_type'] = $type;
}

function readRestoreMetadata(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    $metadata = [];

    for ($i = 0; $i < 10 && ($line = fgets($handle)) !== false; $i++) {
        if (preg_match('/^-- Metadata-Base64:\s*(\S+)/', $line, $matches)) {
            $json = base64_decode($matches[1], true);
            $decoded = $json !== false ? json_decode($json, true) : null;
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
            break;
        }
    }

    fclose($handle);
    return $metadata;
}

/* Split SQL on semicolons outside quoted strings and comments. */
function splitRestoreSql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote !== null) {
            $buffer .= $char;

            if ($char === '\\' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }

            if ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $sql[++$i];
                } else {
                    $quote = null;
                }
            }

            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if (
            $char === '#'
            || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))
        ) {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }

        if ($char === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            $buffer .= ' ';
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if ($quote !== null) {
        throw new RuntimeException('Backup has an unterminated quoted value.');
    }

    $remaining = trim($buffer);
    if ($remaining !== '') {
        $statements[] = $remaining;
    }

    return $statements;
}

function isAllowedRestoreStatement(string $statement, array $allowedTables): bool
{
    if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i', $statement)) {
        return true;
    }

    $patterns = [
        '/^DROP\s+TABLE\s+IF\s+EXISTS\s+`([^`]+)`$/i',
        '/^CREATE\s+TABLE\s+`([^`]+)`\s*\(/i',
        '/^INSERT\s+INTO\s+`([^`]+)`\s*\(/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $statement, $matches)) {
            return in_array($matches[1], $allowedTables, true);
        }
    }

    return false;
}

/* Verify active Super Admin before showing the restore page. */
$currentAdmin = current_admin();
$currentAdminId = filter_var(
    $currentAdmin['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($currentAdminId === false || $currentAdminId === null) {
    restoreDeny(403, 'Access denied');
}

$pdo = db();

try {
    $authStmt = $pdo->prepare(
        'SELECT id, role, status FROM admins WHERE id = :id LIMIT 1'
    );
    $authStmt->execute([':id' => $currentAdminId]);
    $admin = $authStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Restore authorization check failed: ' . $e->getMessage());
    restoreDeny(500, 'Unable to verify access');
}

if (!$admin || $admin['role'] !== 'Super Admin' || $admin['status'] !== 'Active') {
    restoreDeny(403, 'Access denied');
}

if (empty($_SESSION['backup_restore_csrf_token'])) {
    $_SESSION['backup_restore_csrf_token'] = bin2hex(random_bytes(32));
}

/* Must match backup-create.php. */
$privateDirectory = getenv('APP_PRIVATE_DIR');
$backupDirectory = $privateDirectory
    ? rtrim($privateDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backups'
    : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private_backups';

if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    restoreDeny(500, 'Backup storage is unavailable.');
}

$realBackupDirectory = realpath($backupDirectory);

if ($realBackupDirectory === false) {
    restoreDeny(500, 'Backup storage is unavailable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['backup_restore_csrf_token'], $postedToken)) {
        setRestoreToast('Your session expired. Reload and try again.', 'error');
        header('Location: backup-restore.php');
        exit;
    }

    if (($_POST['action'] ?? '') !== 'restore_upload') {
        setRestoreToast('Unknown restore action.', 'error');
        header('Location: backup-restore.php');
        exit;
    }

    if (($_POST['confirm_restore'] ?? '') !== 'RESTORE') {
        setRestoreToast('Type RESTORE exactly to confirm.', 'error');
        header('Location: backup-restore.php');
        exit;
    }

    $upload = $_FILES['backup_file'] ?? null;
    $maxUploadBytes = 256 * 1024 * 1024;

    if (
        !is_array($upload)
        || !isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])
        || $upload['error'] !== UPLOAD_ERR_OK
        || !is_uploaded_file($upload['tmp_name'])
        || strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'sql'
        || (int) $upload['size'] <= 0
        || (int) $upload['size'] > $maxUploadBytes
    ) {
        setRestoreToast('Choose a valid .sql backup smaller than 256 MB.', 'error');
        header('Location: backup-restore.php');
        exit;
    }

    try {
        $sql = file_get_contents($upload['tmp_name']);

        if ($sql === false || !str_starts_with($sql, '-- Young India Group database backup')) {
            throw new RuntimeException('This is not a supported application backup.');
        }

        $metadata = readRestoreMetadata($upload['tmp_name']);
        $tables = $metadata['tables'] ?? null;

        if (!is_array($tables) || !$tables || !isset($metadata['created_at'])) {
            throw new RuntimeException('Backup metadata is missing or invalid. Create a fresh backup.');
        }

        $allowedTables = [];

        foreach ($tables as $table) {
            if (!is_string($table) || !preg_match('/^[A-Za-z0-9_$]+$/', $table)) {
                throw new RuntimeException('Backup contains invalid table metadata.');
            }
            $allowedTables[] = $table;
        }

        $statements = splitRestoreSql($sql);

        if (!$statements) {
            throw new RuntimeException('The backup contains no SQL statements.');
        }

        /* Validate every statement before executing any restore query. */
        foreach ($statements as $statement) {
            if (!isAllowedRestoreStatement($statement, $allowedTables)) {
                throw new RuntimeException(
                    'Backup format is unsupported or invalid. Create a fresh backup using backup-create.php.'
                );
            }
        }

        /*
         * MySQL DROP/CREATE TABLE statements cannot be rolled back.
         * A database error during restore can leave a partial restore.
         */
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        setRestoreToast('Backup restored successfully. Verify the application data.');
    } catch (Throwable $e) {
        error_log('Uploaded database restore failed: ' . $e->getMessage());
        setRestoreToast(
            'Restore failed: ' . $e->getMessage(),
            'error'
        );
    }

    header('Location: backup-restore.php');
    exit;
}

$paths = glob($realBackupDirectory . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [];
usort($paths, static fn($a, $b) => filemtime($b) <=> filemtime($a));

$backups = [];

foreach ($paths as $path) {
    $realPath = realpath($path);

    if (
        $realPath === false
        || dirname($realPath) !== $realBackupDirectory
        || !is_file($realPath)
    ) {
        continue;
    }

    $metadata = readRestoreMetadata($realPath);
    $backups[] = [
        'file' => basename($realPath),
        'created_at' => $metadata['created_at'] ?? date('Y-m-d H:i:s', filemtime($realPath)),
        'type' => $metadata['type'] ?? 'manual',
        'note' => $metadata['note'] ?? '',
        'size' => filesize($realPath) ?: 0,
    ];
}

$toastMessage = $_SESSION['backup_restore_toast_message'] ?? '';
$toastType = $_SESSION['backup_restore_toast_type'] ?? 'success';
unset($_SESSION['backup_restore_toast_message'], $_SESSION['backup_restore_toast_type']);
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
      <section class="adm-page" id="page-backup-restore">
        <div class="adm-page-head">
          <h2><i class="fa-solid fa-database"></i> Restore Data
            <span class="adm-chip">Backup &amp; Recovery</span>
          </h2>
        </div>

        <div class="adm-panel">
          <div class="adm-panel__head"><h3>Restore from a backup file</h3></div>
          <div class="adm-panel__body">
            <p class="adm-note">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Restoring replaces database tables from the selected backup.
              Create a fresh backup first. This cannot be undone.
            </p>

            <form method="post" action="backup-restore.php"
                  enctype="multipart/form-data" id="restoreForm">
              <input type="hidden" name="csrf_token"
                     value="<?= restoreEscape($_SESSION['backup_restore_csrf_token']) ?>">
              <input type="hidden" name="action" value="restore_upload">
              <input type="hidden" name="MAX_FILE_SIZE" value="268435456">

              <label class="adm-drop" for="restoreFile">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span id="selectedFileName">Click to choose a backup file (.sql)</span>
                <input type="file" id="restoreFile" name="backup_file"
                       accept=".sql,application/sql" required>
              </label>

              <div class="adm-field" style="margin-top:16px">
                <label for="confirmRestore">Type RESTORE to confirm *</label>
                <input type="text" id="confirmRestore" name="confirm_restore"
                       autocomplete="off" required>
              </div>

              <button type="submit" class="adm-btn adm-btn--danger">
                <i class="fa-solid fa-rotate-left"></i> Restore Backup
              </button>
              <p><small>Upload a trusted .sql file created by this application.</small></p>
            </form>
          </div>
        </div>

        <div class="adm-panel">
          <div class="adm-panel__head"><h3>Backup history <small>Backups on this server</small></h3></div>
          <div class="adm-table-wrap">
            <table class="adm-table">
              <thead><tr><th>Name</th><th>Created</th><th>Type</th><th>Size</th><th>Action</th></tr></thead>
              <tbody>
              <?php if (!$backups): ?>
                <tr><td colspan="5">No backups have been created yet.</td></tr>
              <?php else: ?>
                <?php foreach ($backups as $backup): ?>
                  <tr>
                    <td><strong><?= restoreEscape($backup['file']) ?></strong>
                      <?php if ($backup['note'] !== ''): ?><br><small><?= restoreEscape($backup['note']) ?></small><?php endif; ?>
                    </td>
                    <td><?= restoreEscape($backup['created_at']) ?></td>
                    <td><?= restoreEscape(ucfirst((string) $backup['type'])) ?></td>
                    <td><?= number_format($backup['size'] / 1024, 1) ?> KB</td>
                    <td>
                      <a class="adm-btn adm-btn--sm"
                         href="backup-create.php?download=<?= rawurlencode($backup['file']) ?>">
                        <i class="fa-solid fa-download"></i> Download
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<div class="adm-toast" id="toast" role="status" aria-live="polite"
     data-message="<?= restoreEscape($toastMessage) ?>"
     data-type="<?= restoreEscape($toastType) ?>"></div>

<script>
(() => {
  const fileInput = document.getElementById('restoreFile');
  const fileLabel = document.getElementById('selectedFileName');

  fileInput?.addEventListener('change', () => {
    fileLabel.textContent = fileInput.files?.[0]?.name
      || 'Click to choose a backup file (.sql)';
  });

  document.getElementById('restoreForm')?.addEventListener('submit', event => {
    const file = fileInput?.files?.[0];
    const confirmation = document.getElementById('confirmRestore');

    if (!file || !file.name.toLowerCase().endsWith('.sql')) {
      event.preventDefault();
      alert('Choose a .sql backup file.');
      return;
    }

    if (confirmation?.value !== 'RESTORE') {
      event.preventDefault();
      alert('Type RESTORE exactly to confirm.');
      confirmation?.focus();
      return;
    }

    if (!confirm('This replaces database tables. Have you created a fresh backup? Continue?')) {
      event.preventDefault();
    }
  });

  const toast = document.getElementById('toast');
  if (toast?.dataset.message) {
    toast.textContent = toast.dataset.message;
    toast.classList.add('is-show');
    if (toast.dataset.type === 'error') toast.classList.add('is-error');
    setTimeout(() => toast.classList.remove('is-show', 'is-error'), 7000);
  }

})();
</script>

<script src="admin.js"></script>
</body>
</html>