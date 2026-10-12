<?php
require __DIR__ . '/includes/bootstrap.php';
if ($u = current_user()) redirect(home_for($u));

$errors = [];
if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) redirect('index.php'); // honeypot

    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password_confirm'] ?? '');

    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen(phone_digits($phone)) < 8) $errors[] = 'Please enter a valid phone number.';
    if ($username !== '' && !valid_username($username)) $errors[] = 'Username can use 3–60 letters, numbers, dots, dashes or underscores.';
    if ($err = validate_password($pw, $pw2)) $errors[] = $err;
    if (empty($_POST['agree'])) $errors[] = 'Please accept the program terms.';
    if (too_many_attempts('register:' . client_ip(), 5, 60)) $errors[] = 'Too many registrations from this network. Please try again later.';

    if (!$errors && val('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'An account with this email already exists. Try signing in or resetting your password.';
    if (!$errors && $username !== '' && val('SELECT id FROM users WHERE username = ?', [$username])) $errors[] = 'That username is taken.';

    if (!$errors) {
        hit_attempt('register:' . client_ip());
        $username = $username !== '' ? $username : username_from_email($email);
        $code = generate_referral_code();
        q('INSERT INTO users (role, full_name, email, phone, username, password_hash, referral_code, source) VALUES ("agent",?,?,?,?,?,?,"self")',
            [$name, $email, $phone, $username, password_hash($pw, PASSWORD_DEFAULT), $code]);
        $agent = row('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()]);
        try { email_agent_welcome($agent); } catch (Throwable $e) {}
        try { email_admin_new_agent($agent); } catch (Throwable $e) {}
        login_user($agent);
        flash('success', 'Welcome to Dignity Jewels! Your QR code has been emailed to you.');
        redirect('agent/');
    }
}

$rate = rtrim(rtrim(number_format((float)setting('commission_rate', '5'), 2), '0'), '.');
$pageTitle = 'Register as an agent';
$area = 'public';
include __DIR__ . '/includes/header.php';
?>
<div class="text-center mb-4">
  <h1 class="page-title">Become an Agent</h1>
  <p class="text-muted mb-0">Earn <?= e($rate) ?>% on every client you refer</p>
</div>
<div class="auth-card">
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
    <div class="mb-3">
      <label class="form-label" for="full_name">Full name</label>
      <input class="form-control" id="full_name" name="full_name" value="<?= e(old('full_name')) ?>" autocomplete="name" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" autocapitalize="none" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="phone">Phone (WhatsApp)</label>
      <?= phone_input('phone', old('phone'), ['required' => true]) ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="username">Username <span class="text-muted fw-normal">(optional)</span></label>
      <input class="form-control" id="username" name="username" value="<?= e(old('username')) ?>" autocapitalize="none" autocomplete="off">
      <div class="form-text">Leave blank to use the first part of your email.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <div class="input-group">
        <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required>
        <button class="btn btn-outline-secondary" type="button" data-toggle-pw="password"><i class="bi bi-eye"></i></button>
      </div>
      <div class="form-text">At least 8 characters with letters and numbers.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password_confirm">Confirm password</label>
      <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="agree" name="agree" value="1" <?= old('agree') ? 'checked' : '' ?>>
      <label class="form-check-label small" for="agree">I understand the referral bonus is paid on sales from clients I refer, after the client has paid in full, and that each client can only be referred by one agent.</label>
    </div>
    <button class="btn btn-gold btn-lg w-100">Create my account</button>
  </form>
</div>
<p class="text-center mt-4 mb-0">Already registered? <a href="<?= url('login.php') ?>" class="fw-semibold">Sign in</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
