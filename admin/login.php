<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

const PUBLIC_SITE_URL = '../index.php';
const DUMMY_HASH = '$2y$10$X0P5cdvCYbb0Zufw/MHDSeS/IeV8iXTFKJSve6D8yXjsA7tvVIr2C';

start_secure_session();

$error  = '';
$notice = '';
$email  = '';
$next   = safe_next($_POST['next'] ?? $_GET['next'] ?? null) ?? '';

if (isset($_GET['logged_out'])) {
    $notice = 'You have been logged out safely.';
}

if (isset($_GET['expired'])) {
    $notice = 'Your session expired. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your page was open for too long. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter your official email and password.';
    } elseif (is_locked_out($email)) {
        $error = 'Too many wrong attempts. Please wait ' . LOCK_MINUTES . ' minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        // Verify even when the email is not found to reduce timing differences.
        $hash  = ($admin && !empty($admin['password_hash']))
            ? $admin['password_hash']
            : DUMMY_HASH;
        $valid = password_verify($password, $hash)
            && $admin
            && !empty($admin['password_hash']);

        if (!$valid) {
            record_attempt($email, false);
            audit(
                'login_failed',
                $admin ? (int) $admin['id'] : null,
                'admins',
                $admin ? (int) $admin['id'] : null,
                ['email' => $email]
            );
            $error = 'Invalid email or password.';
        } elseif ($admin['status'] !== 'Active') {
            audit('login_blocked', (int) $admin['id']);
            $error = 'This account is suspended. Please contact the Central Secretariat.';
        } else {
            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([
                        password_hash($password, PASSWORD_DEFAULT),
                        $admin['id'],
                    ]);
            }

            record_attempt($email, true);
            login_admin($admin);

            if ($remember) {
                set_remember_cookie((int) $admin['id']);
            }

            audit('login', (int) $admin['id']);
            redirect(safe_next($next) ?? DASHBOARD_URL);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | Young India Group – Deven Rabari Foundation</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="css/login.css">
</head>
<body class="login-body">

  <main class="login-layout">
    <aside class="login-brand">
      <div class="login-brand__top">
        <span class="login-pill"><i class="login-dot"></i> MANAGEMENT GATEWAY</span>
        <span class="login-secure"><i class="fa-solid fa-lock"></i> Secure Session</span>
      </div>

      <div class="login-brand__main">
        <div class="login-logo">
          <img src="../assets/logo.png" alt="Young India Group logo">
          <span class="login-logo__badge">ADMIN</span>
        </div>

        <h1>Young India Group</h1>
        <p class="login-brand__sub">Deven Rabari Foundation</p>
        <p class="login-motto">SPEAK <i></i> ACT <i></i> CHANGE</p>

        <hr class="login-rule">

        <h2><i class="fa-solid fa-user-shield"></i> Administrative Portal &amp; Management Gateway</h2>
      </div>

      <div class="login-brand__foot">
        <p><i class="fa-solid fa-location-dot"></i> Morbi, Gujarat • Reg. Public Charitable Trust E/1428/Morbi</p>
        <p><i class="fa-solid fa-shield-halved"></i> Authorized Access Only • 256-Bit SSL Secured</p>
      </div>
    </aside>

    <section class="login-panel">
      <div class="login-panel__top">
        <a class="login-back" href="<?= e(PUBLIC_SITE_URL) ?>">
          <i class="fa-solid fa-arrow-left"></i> Back to Public Website
        </a>
        <span class="login-version">Young India Group Portal v2.4</span>
      </div>

      <div class="login-card">
        <span class="login-bar"></span>
        <h2 class="login-title">Log In</h2>

        <?php if ($error): ?>
          <div class="login-alert login-alert--error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?>
          </div>
        <?php elseif ($notice): ?>
          <div class="login-alert login-alert--info" role="status">
            <i class="fa-solid fa-circle-check"></i> <?= e($notice) ?>
          </div>
        <?php endif; ?>

        <form class="login-form" id="loginForm" method="post" action="login.php" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="next" value="<?= e($next) ?>">

          <div class="login-field">
            <div class="login-field__label">
              <label for="loginEmail">Official Email</label>
            </div>
            <div class="login-input">
              <i class="fa-regular fa-envelope"></i>
              <input
                type="email"
                id="loginEmail"
                name="email"
                value="<?= e($email) ?>"
                placeholder="admin@youngindiagroup.org"
                autocomplete="username"
                required
                autofocus
              >
            </div>
            <small class="login-error"></small>
          </div>

          <div class="login-field">
            <div class="login-field__label">
              <label for="loginPassword">Password</label>
            </div>
            <div class="login-input">
              <i class="fa-solid fa-lock"></i>
              <input
                type="password"
                id="loginPassword"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
              >
              <button
                type="button"
                class="login-eye"
                id="togglePassword"
                aria-label="Show password"
              >
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
            <small class="login-caps" id="capsWarning" hidden>
              <i class="fa-solid fa-triangle-exclamation"></i> Caps Lock is on
            </small>
            <small class="login-error"></small>
          </div>

          <button type="submit" class="login-submit" id="loginSubmit">
            <i class="fa-solid fa-lock"></i>
            <span>Login to Console</span>
          </button>
        </form>

        <div class="login-foot">
          <span><i class="fa-solid fa-shield-halved"></i> Encrypted Gateway</span>
        </div>
      </div>
    </section>
  </main>

  <script src="js/login.js"></script>
</body>
</html>