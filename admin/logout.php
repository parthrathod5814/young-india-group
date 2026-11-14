<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(LOGIN_URL);
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request. Reload the page and try again.');
}

// Revoke the remember-me token, if one exists.
clear_remember_cookie();

// Clear the YIGADMINSESS session.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

redirect(LOGIN_URL . '?logged_out=1');