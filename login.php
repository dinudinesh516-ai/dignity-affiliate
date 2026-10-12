<?php
require __DIR__ . '/includes/bootstrap.php';
if ($u = current_user()) redirect(home_for($u));

$error = '';
if (is_post()) {
    csrf_check();
    $id = trim((string)($_POST['login'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $bucket = 'login:' . client_ip();
    if (too_many_attempts($bucket, 8, 15)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes or reset your password.';
    } else {
        $u = row('SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1', [$id, $id]);
        if ($u && $u['password_hash'] && password_verify($pw, $u['password_hash'])) {
            if ($u['status'] !== 'active') {
                $error = 'Your account is disabled. Please contact Dignity Jewels.';
            } else {
                clear_attempts($bucket);
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
                }
                login_user($u);
                $intended = $_SESSION['intended'] ?? '';
                unset($_SESSION['intended']);
                $prefix = $u['role'] === 'admin' ? '/admin/' : '/agent/';
                if ($intended && strpos($intended, $prefix) !== false && strpos($intended, '//') === false) {
                    header('Location: ' . $intended);
                    exit;
                }
                redirect(home_for($u));
            }
        } else {
            hit_attempt($bucket);
            $error = ($u && !$u['password_hash'])
                ? 'You haven\'t set a password yet. Use the link in your welcome email, or tap "Forgot password".'
                : 'Incorrect email/username or password.';
        }
    }
}

$pageTitle = 'Sign in';
$area = 'public';
include __DIR__ . '/includes/header.php';
?>
<a href="<?= url() ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
<div class="text-center mb-4">
  <h1 class="page-title">Welcome back</h1>
  <p class="text-muted mb-0">Sign in to your affiliate or admin account</p>
</div>
<div class="auth-card">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="login">Email or username</label>
      <input class="form-control" id="login" name="login" value="<?= e(old('login')) ?>" autocomplete="username" autocapitalize="none" required autofocus>
    </div>
    <div class="mb-2">
      <label class="form-label" for="password">Password</label>
      <div class="input-group">
        <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
        <button class="btn btn-outline-secondary" type="button" data-toggle-pw="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <div class="text-end mb-3"><a href="<?= url('forgot.php') ?>" class="small">Forgot password?</a></div>
    <button class="btn btn-primary btn-lg w-100">Sign in</button>
  </form>
</div>
<p class="text-center mt-4 mb-0">New here? <a href="<?= url('register.php') ?>" class="fw-semibold">Register as an agent</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
