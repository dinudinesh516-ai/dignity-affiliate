<?php
/**
 * One-time installer: creates tables and the admin account.
 * Open https://your-domain/install/ once, then DELETE the /install folder.
 */
define('INSTALLING', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Admin account details are entered in the form below (never stored in the code).
$ADMIN_NAME = trim((string)($_POST['admin_name'] ?? ''));
$ADMIN_EMAIL = strtolower(trim((string)($_POST['admin_email'] ?? '')));
$ADMIN_PASSWORD = (string)($_POST['admin_password'] ?? '');

$messages = [];
$done = false;
try {
    // Create the database if it does not exist yet (works when the DB user has permission, e.g. XAMPP root)
    try {
        $tmp = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $CONFIG['DB_HOST'], (int)$CONFIG['DB_PORT']), $CONFIG['DB_USER'], $CONFIG['DB_PASS']);
        $tmp->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $CONFIG['DB_NAME']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $tmp = null;
    } catch (Throwable $e) { /* ignore — DB probably exists already */ }

    $hasUsers = (bool)val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
    $adminExists = $hasUsers && val("SELECT id FROM users WHERE role = 'admin' LIMIT 1");

    if ($adminExists) {
        $messages[] = ['warning', 'The application is already installed. For security, delete the /install folder from the server.'];
        $done = true;
    } elseif (is_post() && $ADMIN_NAME === '') {
        $messages[] = ['danger', 'Please enter the admin name.'];
    } elseif (is_post() && !filter_var($ADMIN_EMAIL, FILTER_VALIDATE_EMAIL)) {
        $messages[] = ['danger', 'Please enter a valid admin email.'];
    } elseif (is_post() && ($pwErr = validate_password($ADMIN_PASSWORD, (string)($_POST['admin_password_confirm'] ?? '')))) {
        $messages[] = ['danger', $pwErr];
    } elseif (is_post()) {
        $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/schema.sql'));
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
            if (trim($stmt) !== '') db()->exec($stmt);
        }
        q("INSERT INTO users (role, full_name, email, username, password_hash, status, source) VALUES ('admin', ?, ?, 'admin', ?, 'active', 'system')",
            [$ADMIN_NAME, $ADMIN_EMAIL, password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
        save_setting('notify_emails', $ADMIN_EMAIL);
        $messages[] = ['success', 'Installed successfully. Admin account created for ' . $ADMIN_EMAIL . '.'];
        $messages[] = ['warning', 'IMPORTANT: delete the /install folder from the server now.'];
        $done = true;
    }
} catch (Throwable $e) {
    $messages[] = ['danger', 'Install failed: ' . $e->getMessage()];
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install - Dignity Jewels</title>
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-5" style="max-width:520px">
<h1 class="h3 mb-3">Dignity Jewels — Installer</h1>
<?php foreach ($messages as [$t, $m]): ?><div class="alert alert-<?= $t ?>"><?= e($m) ?></div><?php endforeach; ?>
<?php if (!$done): ?>
<div class="card card-body">
  <p>This will create the database tables in <code><?= e($CONFIG['DB_NAME']) ?></code> and your admin login.</p>
  <form method="post" autocomplete="off">
    <div class="mb-2"><label class="form-label">Admin name</label><input class="form-control" name="admin_name" value="<?= e($ADMIN_NAME) ?>" required></div>
    <div class="mb-2"><label class="form-label">Admin email (used to sign in)</label><input class="form-control" type="email" name="admin_email" value="<?= e($ADMIN_EMAIL) ?>" required></div>
    <div class="mb-2"><label class="form-label">Admin password</label><input class="form-control" type="password" name="admin_password" autocomplete="new-password" required><div class="form-text">At least 8 characters with letters and numbers.</div></div>
    <div class="mb-3"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="admin_password_confirm" autocomplete="new-password" required></div>
    <button class="btn btn-dark w-100 btn-lg">Install now</button>
  </form>
</div>
<?php else: ?>
<a class="btn btn-dark" href="<?= url('login.php') ?>">Go to login</a>
<?php endif; ?>
</div></body></html>
