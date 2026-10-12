<?php
/** Handles both "reset password" and "set your password" (new agent) links. */
require __DIR__ . '/includes/bootstrap.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$t = find_password_token($token);
$error = '';

if ($t && is_post()) {
    csrf_check();
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password_confirm'] ?? '');
    if ($err = validate_password($pw, $pw2)) {
        $error = $err;
    } else {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $t['user_id']]);
        q('UPDATE password_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$t['user_id']]);
        clear_attempts('login:' . client_ip());
        $u = row('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
        logout_user();
        session_start();
        login_user($u);
        flash('success', $t['purpose'] === 'setup' ? 'Your password is set. Welcome to Dignity Jewels!' : 'Your password has been updated.');
        redirect(home_for($u));
    }
}

$isSetup = $t && $t['purpose'] === 'setup';
$pageTitle = $isSetup ? 'Set your password' : 'Reset password';
$area = 'public';
include __DIR__ . '/includes/header.php';
?>
<div class="text-center mb-4">
  <h1 class="page-title"><?= e($pageTitle) ?></h1>
  <?php if ($t): ?><p class="text-muted mb-0">for <?= e($t['email']) ?></p><?php endif; ?>
</div>
<div class="auth-card">
<?php if (!$t): ?>
  <div class="text-center py-2">
    <i class="bi bi-link-45deg text-danger" style="font-size:2.5rem"></i>
    <p class="mt-2 fw-semibold mb-1">This link is invalid or has expired</p>
    <p class="text-muted small">Links can only be used once. Request a new one below.</p>
    <a href="<?= url('forgot.php') ?>" class="btn btn-primary w-100">Request a new link</a>
  </div>
<?php else: ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <?php if ($isSetup && $t['username']): ?>
    <div class="alert alert-light border small mb-3">Your username is <strong><?= e($t['username']) ?></strong>. You can also sign in with your email.</div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
      <label class="form-label" for="password">New password</label>
      <div class="input-group">
        <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required autofocus>
        <button class="btn btn-outline-secondary" type="button" data-toggle-pw="password"><i class="bi bi-eye"></i></button>
      </div>
      <div class="form-text">At least 8 characters with letters and numbers.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password_confirm">Confirm new password</label>
      <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary btn-lg w-100"><?= $isSetup ? 'Set password & continue' : 'Update password' ?></button>
  </form>
<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
