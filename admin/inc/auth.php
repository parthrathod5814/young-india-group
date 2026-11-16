<?php
/* =========================================================
   inc/auth.php  |  Session, login, remember-me and CSRF helpers

   Protect any admin page by adding this as its FIRST line:
     <?php require_once __DIR__ . '/inc/auth.php'; require_login(); ?>
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const LOGIN_URL        = 'login.php';
const DASHBOARD_URL    = 'index.php';          // where an admin lands after logging in
const IDLE_TIMEOUT     = 7200;                 // log out after 2 hours without activity
const REMEMBER_DAYS    = 30;
const MAX_FAILS        = 5;                    // wrong passwords allowed ...
const LOCK_MINUTES     = 15;                   // ... within this many minutes


/* ---------- small helpers ---------- */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

// Only allow redirects to a page inside the admin folder (stops "open redirect" tricks)
function safe_next(?string $next): ?string
{
    if ($next && preg_match('#^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&\-%]*)?$#', $next)) {
        return $next;
    }
    return null;
}

function audit(string $action, ?int $adminId, string $entity = 'admins', ?int $entityId = null, ?array $details = null): void
{
    try {
        db()->prepare('INSERT INTO audit_log (admin_id, action, entity, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$adminId, $action, $entity, $entityId, $details ? json_encode($details) : null, client_ip()]);
    } catch (Throwable $error) {
        // never stop a login because the log failed
    }
}


/* ---------- session ---------- */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('YIGADMINSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    start_secure_session();
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}


/* ---------- login state ---------- */
function login_admin(array $admin): void
{
    start_secure_session();
    session_regenerate_id(true);                       // new session id after login

    $_SESSION['admin'] = [
        'id'    => (int) $admin['id'],
        'name'  => $admin['name'],
        'email' => $admin['email'],
        'role'  => $admin['role'],
    ];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
}

function is_logged_in(): bool
{
    start_secure_session();

    if (!empty($_SESSION['admin'])) {
        if (time() - ($_SESSION['last_activity'] ?? 0) > IDLE_TIMEOUT) {
            $_SESSION = [];
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    return login_from_remember_cookie();
}

function current_admin(): ?array
{
    return is_logged_in() ? $_SESSION['admin'] : null;
}

// Put this at the top of every admin page
function require_login(): void
{
    if (!is_logged_in()) {
        $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
        redirect(LOGIN_URL . ($page && $page !== LOGIN_URL ? '?next=' . urlencode($page) : ''));
    }
}


/* ---------- remember me ---------- */
function set_remember_cookie(int $adminId): void
{
    $selector  = bin2hex(random_bytes(12));            // 24 characters
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + REMEMBER_DAYS * 86400;

    db()->prepare('INSERT INTO admin_remember_tokens (admin_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$adminId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);

    setcookie('yig_remember', $selector . ':' . $validator, [
        'expires' => $expires, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    if (!empty($_COOKIE['yig_remember'])) {
        [$selector] = array_pad(explode(':', $_COOKIE['yig_remember'], 2), 2, '');
        db()->prepare('DELETE FROM admin_remember_tokens WHERE selector = ?')->execute([$selector]);
    }
    setcookie('yig_remember', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

function login_from_remember_cookie(): bool
{
    if (empty($_COOKIE['yig_remember'])) {
        return false;
    }

    [$selector, $validator] = array_pad(explode(':', $_COOKIE['yig_remember'], 2), 2, '');
    $stmt = db()->prepare(
        'SELECT t.id AS token_id, t.token_hash, a.* FROM admin_remember_tokens t
         JOIN admins a ON a.id = t.admin_id
         WHERE t.selector = ? AND t.expires_at > NOW() AND a.status = "Active"'
    );
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $validator))) {
        clear_remember_cookie();
        return false;
    }

    db()->prepare('DELETE FROM admin_remember_tokens WHERE id = ?')->execute([$row['token_id']]);
    login_admin($row);
    set_remember_cookie((int) $row['id']);             // give a fresh token
    audit('login_remembered', (int) $row['id']);
    return true;
}


/* ---------- brute force protection ---------- */
function record_attempt(string $email, bool $success): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)')
        ->execute([$email, client_ip(), $success ? 1 : 0]);
}

function is_locked_out(string $email): bool
{
    $stmt = db()->prepare(
        'SELECT
            SUM(email = ? AND ip_address = ?) AS same_user,
            COUNT(*)                          AS same_ip
         FROM login_attempts
         WHERE success = 0 AND ip_address = ? AND created_at > (NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE)'
    );
    $stmt->execute([$email, client_ip(), client_ip()]);
    $row = $stmt->fetch();

    return (int) $row['same_user'] >= MAX_FAILS || (int) $row['same_ip'] >= MAX_FAILS * 4;
}


// Ensure this token is initialized before the header renders its button.
if (empty($_SESSION['notification_csrf_token'])) {
    $_SESSION['notification_csrf_token'] = bin2hex(random_bytes(32));
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['header_action'] ?? '') === 'delete_all_notifications'
) {
    header('Content-Type: application/json; charset=utf-8');

    $admin = current_admin() ?? [];
    $adminId = (int) ($admin['id'] ?? 0);

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) $_SESSION['notification_csrf_token'];

    if ($adminId < 1) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    if (!hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid security token.']);
        exit;
    }

    try {
        db()->exec('DELETE FROM notifications');

        // Keep notification_reads unchanged; it stores read history.
        echo json_encode([
            'ok' => true,
            'message' => 'All notifications deleted.',
        ]);
    } catch (Throwable $e) {
        error_log('Unable to delete notifications: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Database deletion failed.',
        ]);
    }

    exit;
}