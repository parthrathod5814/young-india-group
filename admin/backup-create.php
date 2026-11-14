<?php
require_once __DIR__ . '/inc/auth.php';
require_login();
require_once __DIR__ . '/inc/config.php';

function backupEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function backupDeny(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

function setBackupToast(string $message, string $type = 'success'): void
{
    $_SESSION['backup_toast_message'] = $message;
    $_SESSION['backup_toast_type'] = $type;
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function getBaseTables(PDO $pdo): array
{
    $tables = [];
    $stmt = $pdo->query('SHOW FULL TABLES');

    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if (isset($row[1]) && strtoupper((string) $row[1]) === 'BASE TABLE') {
            $tables[] = (string) $row[0];
        }
    }

    return $tables;
}

function writeBackupBytes($handle, string $contents): void
{
    $length = strlen($contents);
    $written = 0;

    while ($written < $length) {
        $result = fwrite($handle, substr($contents, $written));

        if ($result === false || $result === 0) {
            throw new RuntimeException('Could not write the backup file.');
        }

        $written += $result;
    }
}

function readBackupSettings(string $path): array
{
    if (!is_file($path)) {
        return ['auto_enabled' => false, 'last_auto_backup' => null];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded)
        ? [
            'auto_enabled' => !empty($decoded['auto_enabled']),
            'last_auto_backup' => $decoded['last_auto_backup'] ?? null,
        ]
        : ['auto_enabled' => false, 'last_auto_backup' => null];
}

function writeBackupSettings(string $path, array $settings): bool
{
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $temporaryPath = $path . '.tmp';

    if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
        return false;
    }

    return rename($temporaryPath, $path);
}

function createSqlBackup(PDO $pdo, string $directory, string $type, string $note): array
{
    $tables = getBaseTables($pdo);
    $rowCounts = [];

    foreach ($tables as $table) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM ' . quoteIdentifier($table));
        $rowCounts[$table] = (int) $stmt->fetchColumn();
    }

    $createdAt = date('Y-m-d H:i:s');
    $fileName = 'backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
    $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

    $metadata = [
        'type' => $type,
        'created_at' => $createdAt,
        'note' => $note,
        'row_counts' => $rowCounts,
        'tables' => $tables,
    ];

    $metadataJson = json_encode(
        $metadata,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($metadataJson === false) {
        throw new RuntimeException('Could not encode backup metadata.');
    }

    $handle = fopen($filePath, 'xb');

    if ($handle === false) {
        throw new RuntimeException('Could not create the backup file.');
    }

    @chmod($filePath, 0600);

    try {
        $header = implode("\n", [
            '-- Young India Group database backup',
            '-- Generated: ' . $createdAt,
            '-- Type: ' . strtoupper($type),
            '-- Metadata-Base64: ' . base64_encode($metadataJson),
            '--',
            '-- Restore warning: this dump replaces matching tables and their data.',
            '',
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ]);

        writeBackupBytes($handle, $header);

        foreach ($tables as $table) {
            $quotedTable = quoteIdentifier($table);

            writeBackupBytes(
                $handle,
                "\n-- --------------------------------------------------------\n"
                . '-- Table: ' . str_replace(["\r", "\n"], '', $table) . "\n"
                . 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n"
            );

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);

            if (!isset($createRow[1])) {
                throw new RuntimeException('Could not read a table definition.');
            }

            writeBackupBytes($handle, $createRow[1] . ";\n\n");

            $dataStmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            $columns = [];

            for ($i = 0; $i < $dataStmt->columnCount(); $i++) {
                $columnMeta = $dataStmt->getColumnMeta($i);
                if (isset($columnMeta['name'])) {
                    $columns[] = (string) $columnMeta['name'];
                }
            }

            if (!$columns) {
                continue;
            }

            $quotedColumns = implode(
                ', ',
                array_map('quoteIdentifier', $columns)
            );

            while ($record = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $values = [];

                foreach ($columns as $column) {
                    $value = $record[$column] ?? null;

                    if ($value === null) {
                        $values[] = 'NULL';
                        continue;
                    }

                    $quotedValue = $pdo->quote((string) $value);
                    if ($quotedValue === false) {
                        throw new RuntimeException('Could not encode a database value.');
                    }

                    $values[] = $quotedValue;
                }

                writeBackupBytes(
                    $handle,
                    'INSERT INTO ' . $quotedTable
                    . ' (' . $quotedColumns . ') VALUES ('
                    . implode(', ', $values) . ");\n"
                );
            }

            writeBackupBytes($handle, "\n");
        }

        writeBackupBytes($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");

        if (!fflush($handle)) {
            throw new RuntimeException('Could not finish writing the backup file.');
        }
    } catch (Throwable $e) {
        fclose($handle);
        @unlink($filePath);
        throw $e;
    }

    fclose($handle);

    return [
        'file' => $fileName,
        'path' => $filePath,
        'metadata' => $metadata,
        'size' => filesize($filePath) ?: 0,
    ];
}

function readBackupMetadata(string $path): array
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

function listBackupFiles(string $directory): array
{
    $paths = glob($directory . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [];
    usort($paths, static fn($a, $b) => filemtime($b) <=> filemtime($a));

    $backups = [];

    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }

        $metadata = readBackupMetadata($path);
        $backups[] = [
            'file' => basename($path),
            'created_at' => $metadata['created_at'] ?? date('Y-m-d H:i:s', filemtime($path)),
            'type' => $metadata['type'] ?? 'manual',
            'note' => $metadata['note'] ?? '',
            'size' => filesize($path) ?: 0,
        ];
    }

    return $backups;
}

function pruneOldBackups(string $directory, int $keep = 10): void
{
    $paths = glob($directory . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [];
    usort($paths, static fn($a, $b) => filemtime($b) <=> filemtime($a));

    foreach (array_slice($paths, $keep) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/* Verify active Super Admin before displaying backup information. */
$currentAdmin = current_admin();
$currentAdminId = filter_var(
    $currentAdmin['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($currentAdminId === false || $currentAdminId === null) {
    backupDeny(403, 'Access denied');
}

$pdo = db();

try {
    $stmt = $pdo->prepare(
        'SELECT id, role, status FROM admins WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $currentAdminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Backup authorization check failed: ' . $e->getMessage());
    backupDeny(500, 'Unable to verify access');
}

if (!$admin || $admin['role'] !== 'Super Admin' || $admin['status'] !== 'Active') {
    backupDeny(403, 'Access denied');
}

if (empty($_SESSION['backup_csrf_token'])) {
    $_SESSION['backup_csrf_token'] = bin2hex(random_bytes(32));
}

/* Keep this directory the same in backup-restore.php. */
$privateDirectory = getenv('APP_PRIVATE_DIR');
$backupDirectory = $privateDirectory
    ? rtrim($privateDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backups'
    : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private_backups';

if (
    !is_dir($backupDirectory)
    && !mkdir($backupDirectory, 0700, true)
    && !is_dir($backupDirectory)
) {
    error_log('Could not create backup directory: ' . $backupDirectory);
    http_response_code(500);
    exit('Backup storage is unavailable or not writable.');
}

@chmod($backupDirectory, 0700);
$settingsPath = $backupDirectory . DIRECTORY_SEPARATOR . 'backup_settings.json';
$settings = readBackupSettings($settingsPath);

/* Authenticated backup download. */
if (isset($_GET['download'])) {
    $file = (string) $_GET['download'];

    if (!preg_match('/^backup_[A-Za-z0-9_]+\.sql$/', $file)) {
        http_response_code(400);
        exit('Invalid backup filename.');
    }

    $realDirectory = realpath($backupDirectory);
    $realFile = realpath($backupDirectory . DIRECTORY_SEPARATOR . $file);

    if (
        $realDirectory === false
        || $realFile === false
        || dirname($realFile) !== $realDirectory
        || !is_file($realFile)
    ) {
        http_response_code(404);
        exit('Backup not found.');
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
    header('Content-Length: ' . filesize($realFile));
    header('X-Content-Type-Options: nosniff');
    readfile($realFile);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['backup_csrf_token'], $postedToken)) {
        setBackupToast('Your session expired. Reload the page and try again.', 'error');
        header('Location: backup-create.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $note = trim((string) ($_POST['note'] ?? ''));
        $note = substr($note, 0, 250);

        try {
            $created = createSqlBackup($pdo, $backupDirectory, 'manual', $note);
            pruneOldBackups($backupDirectory, 10);
            setBackupToast('Backup created successfully: ' . $created['file']);

            // Avoid an automatic attempt replacing the manual success message.
            $_SESSION['skip_auto_backup_once'] = true;
        } catch (Throwable $e) {
            error_log('Manual backup failed: ' . $e->getMessage());
            setBackupToast('Could not create the backup. Check the PHP error log.', 'error');
        }

        header('Location: backup-create.php');
        exit;
    }

    if ($action === 'set_auto') {
        $settings['auto_enabled'] = ($_POST['auto_enabled'] ?? '') === '1';

        if (!$settings['auto_enabled']) {
            $settings['last_auto_backup'] = null;
        }

        setBackupToast(
            writeBackupSettings($settingsPath, $settings)
                ? ($settings['auto_enabled']
                    ? 'Automatic daily backup enabled.'
                    : 'Automatic daily backup disabled.')
                : 'Could not save the backup setting.',
            writeBackupSettings($settingsPath, $settings) ? 'success' : 'error'
        );

        header('Location: backup-create.php');
        exit;
    }

    if ($action === 'delete') {
        $file = (string) ($_POST['file'] ?? '');

        if (!preg_match('/^backup_[A-Za-z0-9_]+\.sql$/', $file)) {
            setBackupToast('Invalid backup filename.', 'error');
        } else {
            $realDirectory = realpath($backupDirectory);
            $realFile = realpath($backupDirectory . DIRECTORY_SEPARATOR . $file);

            if (
                $realDirectory !== false
                && $realFile !== false
                && dirname($realFile) === $realDirectory
                && is_file($realFile)
                && unlink($realFile)
            ) {
                setBackupToast('Backup deleted.');
            } else {
                setBackupToast('Could not delete that backup.', 'error');
            }
        }

        header('Location: backup-create.php');
        exit;
    }

    setBackupToast('Unknown action.', 'error');
    header('Location: backup-create.php');
    exit;
}

/* Automatic backup runs on a page visit, at most once per 24 hours. */
$skipAuto = !empty($_SESSION['skip_auto_backup_once']);
unset($_SESSION['skip_auto_backup_once']);

$settings = readBackupSettings($settingsPath);
$lastAutoTimestamp = !empty($settings['last_auto_backup'])
    ? strtotime((string) $settings['last_auto_backup'])
    : false;

if (
    !$skipAuto
    && $settings['auto_enabled']
    && ($lastAutoTimestamp === false || $lastAutoTimestamp <= time() - 86400)
) {
    try {
        createSqlBackup($pdo, $backupDirectory, 'automatic', 'Automatic daily backup');
        $settings['last_auto_backup'] = date('Y-m-d H:i:s');
        writeBackupSettings($settingsPath, $settings);
        pruneOldBackups($backupDirectory, 10);
    } catch (Throwable $e) {
        error_log('Automatic backup failed: ' . $e->getMessage());
        if (empty($_SESSION['backup_toast_message'])) {
            setBackupToast('Automatic backup failed. Check the PHP error log.', 'error');
        }
    }
}

$toastMessage = $_SESSION['backup_toast_message'] ?? '';
$toastType = $_SESSION['backup_toast_type'] ?? 'success';
unset($_SESSION['backup_toast_message'], $_SESSION['backup_toast_type']);

$summary = [
    'blood' => 0,
    'donors' => 0,
    'volunteers' => 0,
    'activities' => 0,
    'inquiries' => 0,
    'admins' => 0,
];

$candidates = [
    'blood' => ['blood_requests', 'blood_inquiries', 'blood_inq'],
    'donors' => ['donors'],
    'volunteers' => ['volunteers'],
    'activities' => ['activities'],
    'inquiries' => ['inquiries'],
    'admins' => ['admins'],
];

try {
    $tables = getBaseTables($pdo);
    foreach ($candidates as $key => $names) {
        foreach ($names as $name) {
            if (in_array($name, $tables, true)) {
                $summary[$key] = (int) $pdo
                    ->query('SELECT COUNT(*) FROM ' . quoteIdentifier($name))
                    ->fetchColumn();
                break;
            }
        }
    }
} catch (PDOException $e) {
    error_log('Could not load database summary: ' . $e->getMessage());
}

$backups = listBackupFiles($backupDirectory);
$lastBackup = $backups[0]['created_at'] ?? 'never';
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
      <section class="adm-page">
        <div class="adm-page-head">
          <h2><i class="fa-solid fa-database"></i> Create Backup
            <span class="adm-chip">Backup &amp; Recovery</span>
          </h2>
        </div>

        <div class="adm-two">
          <div class="adm-panel">
            <div class="adm-panel__head"><h3>Create backup now</h3></div>
            <div class="adm-panel__body">
              <form method="post" action="backup-create.php">
                <input type="hidden" name="csrf_token" value="<?= backupEscape($_SESSION['backup_csrf_token']) ?>">
                <input type="hidden" name="action" value="create">
                <div class="adm-field">
                  <label for="bkNote">Note (optional)</label>
                  <input id="bkNote" name="note" maxlength="250" placeholder="e.g. Before monthly update">
                </div>
                <button type="submit" class="adm-btn adm-btn--primary" style="margin-top:10px">
                  <i class="fa-solid fa-database"></i> Create Backup Now
                </button>
              </form>

              <form method="post" action="backup-create.php" class="backup-auto-form">
                <input type="hidden" name="csrf_token" value="<?= backupEscape($_SESSION['backup_csrf_token']) ?>">
                <input type="hidden" name="action" value="set_auto">
                <input type="hidden" name="auto_enabled" id="autoBackupValue"
                       value="<?= $settings['auto_enabled'] ? '1' : '0' ?>">
                <label class="adm-switch" for="autoBackup">
                  <input type="checkbox" id="autoBackup" <?= $settings['auto_enabled'] ? 'checked' : '' ?>>
                  Automatic daily backup
                </label>
                <small>Runs when this page is visited, at most once every 24 hours.</small>
              </form>
            </div>
          </div>

          <div class="adm-panel">
            <div class="adm-panel__head"><h3>Database summary</h3></div>
            <div class="adm-panel__body">
              <p>
                Blood Requests: <b><?= $summary['blood'] ?></b><br>
                Donors: <b><?= $summary['donors'] ?></b><br>
                Volunteers: <b><?= $summary['volunteers'] ?></b><br>
                Activities: <b><?= $summary['activities'] ?></b><br>
                Inquiries: <b><?= $summary['inquiries'] ?></b><br>
                Admins: <b><?= $summary['admins'] ?></b>
              </p>
              <p style="margin-top:10px"><small>Last backup: <?= backupEscape($lastBackup) ?></small></p>
            </div>
          </div>
        </div>

        <div class="adm-panel">
          <div class="adm-panel__head"><h3>Backup history <small>Latest 10 backups are kept</small></h3></div>
          <div class="adm-table-wrap">
            <table class="adm-table">
              <thead><tr><th>Name</th><th>Created</th><th>Type</th><th>Size</th><th>Action</th></tr></thead>
              <tbody>
              <?php if (!$backups): ?>
                <tr><td colspan="5">No backups have been created yet.</td></tr>
              <?php else: ?>
                <?php foreach ($backups as $backup): ?>
                  <tr>
                    <td><strong><?= backupEscape($backup['file']) ?></strong>
                      <?php if ($backup['note'] !== ''): ?><br><small><?= backupEscape($backup['note']) ?></small><?php endif; ?>
                    </td>
                    <td><?= backupEscape($backup['created_at']) ?></td>
                    <td><?= backupEscape(ucfirst((string) $backup['type'])) ?></td>
                    <td><?= number_format($backup['size'] / 1024, 1) ?> KB</td>
                    <td class="is-actions">
                      <a class="adm-btn adm-btn--sm"
                         href="backup-create.php?download=<?= rawurlencode($backup['file']) ?>">
                        <i class="fa-solid fa-download"></i> Download
                      </a>
                      <form method="post" class="delete-backup-form">
                        <input type="hidden" name="csrf_token" value="<?= backupEscape($_SESSION['backup_csrf_token']) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="file" value="<?= backupEscape($backup['file']) ?>">
                        <button type="submit" class="adm-btn adm-btn--danger adm-btn--sm">
                          <i class="fa-solid fa-trash"></i> Delete
                        </button>
                      </form>
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
     data-message="<?= backupEscape($toastMessage) ?>"
     data-type="<?= backupEscape($toastType) ?>"></div>

<style>
.backup-auto-form { display:grid; gap:8px; margin-top:18px; }
.delete-backup-form { display:inline; }
</style>
<script>
(() => {
  const checkbox = document.getElementById('autoBackup');
  const value = document.getElementById('autoBackupValue');
  checkbox?.addEventListener('change', () => {
    value.value = checkbox.checked ? '1' : '0';
    checkbox.form?.requestSubmit();
  });

  document.querySelectorAll('.delete-backup-form').forEach(form => {
    form.addEventListener('submit', event => {
      if (!confirm('Delete this backup permanently?')) event.preventDefault();
    });
  });

  const toast = document.getElementById('toast');
  if (toast?.dataset.message) {
    toast.textContent = toast.dataset.message;
    toast.classList.add('is-show');
    if (toast.dataset.type === 'error') toast.classList.add('is-error');
    setTimeout(() => toast.classList.remove('is-show', 'is-error'), 5000);
  }

})();
</script>

<script src="admin.js"></script>
</body>
</html>