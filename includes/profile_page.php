<?php
/** Shared "My account" page for admin & agent. Expects $me and $area. */
$errors = [];
if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'details') {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        if (!valid_username($username)) $errors[] = 'Username can use 3–60 letters, numbers, dots, dashes or underscores.';
        if (!$errors && val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $me['id']])) $errors[] = 'That email is used by another account.';
        if (!$errors && val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $me['id']])) $errors[] = 'That username is taken.';
        if (!$errors) {
            q('UPDATE users SET full_name = ?, email = ?, phone = ?, username = ? WHERE id = ?', [$name, $email, $phone ?: null, $username, $me['id']]);
            flash('success', 'Your details were saved.');
            redirect($area . '/profile.php');
        }
    } elseif ($action === 'password') {
        $cur = (string)($_POST['current_password'] ?? '');
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password_confirm'] ?? '');
        if (!$me['password_hash'] || !password_verify($cur, $me['password_hash'])) $errors[] = 'Your current password is incorrect.';
        elseif ($err = validate_password($pw, $pw2)) $errors[] = $err;
        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.');
            redirect($area . '/profile.php');
        }
    }
}

$pageTitle = 'My account';
$active = 'profile';
include __DIR__ . '/header.php';
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <span class="avatar lg"><?= e(initials($me['full_name'])) ?></span>
    <div><h1 class="page-title"><?= e($me['full_name']) ?></h1><div class="text-muted small">Member since <?= fdate($me['created_at']) ?></div></div>
  </div>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Profile details</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="details">
          <div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="<?= e($me['full_name']) ?>" required></div>
          <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($me['email']) ?>" required></div>
          <div class="mb-3"><label class="form-label">Phone</label><?= phone_input('phone', $me['phone']) ?></div>
          <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($me['username']) ?>" autocapitalize="none" required></div>
          <?php if ($me['role'] === 'agent'): ?>
            <div class="mb-3"><label class="form-label">Referral code</label><input class="form-control" value="<?= e($me['referral_code']) ?>" disabled></div>
          <?php endif; ?>
          <button class="btn btn-primary w-100">Save details</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Change password</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <div class="mb-3"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div>
          <div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password" name="password" autocomplete="new-password" required><div class="form-text">At least 8 characters with letters and numbers.</div></div>
          <div class="mb-3"><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="password_confirm" autocomplete="new-password" required></div>
          <button class="btn btn-outline-dark w-100">Update password</button>
        </form>
        <div class="text-center mt-3 small"><a href="<?= url('logout.php') ?>" class="text-danger"><i class="bi bi-box-arrow-right"></i> Sign out</a></div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
