<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
if (is_post()) {
    csrf_check();
    $id = trim((string)($_POST['login'] ?? ''));
    $bucket = 'forgot:' . client_ip();
    if (!too_many_attempts($bucket, 6, 30) && $id !== '') {
        hit_attempt($bucket);
        $u = row('SELECT * FROM users WHERE (email = ? OR username = ?) AND status = "active" LIMIT 1', [$id, $id]);
        if ($u) {
            $token = create_password_token((int)$u['id'], 'reset');
            email_password_reset($u, $token);
        }
    }
    $sent = true; // same response whether or not the account exists
}

$pageTitle = 'Forgot password';
$area = 'public';
include __DIR__ . '/includes/header.php';
?>
<a href="<?= url('login.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to sign in</a>
<div class="text-center mb-4">
  <h1 class="page-title">Forgot password?</h1>
  <p class="text-muted mb-0">We'll email you a link to reset it.</p>
</div>
<div class="auth-card">
  <?php if ($sent): ?>
    <div class="text-center py-2">
      <i class="bi bi-envelope-check text-gold" style="font-size:2.5rem"></i>
      <p class="mt-3 mb-1 fw-semibold">Check your inbox</p>
      <p class="text-muted small mb-0">If an account matches what you entered, a reset link is on its way. It expires in 1 hour. Don't forget to check your spam folder.</p>
    </div>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="login">Email or username</label>
      <input class="form-control" id="login" name="login" autocapitalize="none" autocomplete="username" required autofocus>
    </div>
    <button class="btn btn-primary btn-lg w-100">Send reset link</button>
  </form>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
