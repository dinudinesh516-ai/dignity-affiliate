<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$errors = [];
$showForm = !empty($_GET['new']);
if (is_post()) {
    csrf_check();
    $showForm = true;
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $rate = trim((string)($_POST['commission_rate'] ?? ''));
    $sendCreds = !empty($_POST['send_credentials']);

    if ($name === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen(phone_digits($phone)) < 8) $errors[] = 'Enter a valid phone number.';
    if ($username !== '' && !valid_username($username)) $errors[] = 'Username can use 3–60 letters, numbers, dots, dashes or underscores.';
    if ($pw !== '' && ($err = validate_password($pw, $pw))) $errors[] = $err;
    if ($rate !== '' && (!is_numeric($rate) || (float)$rate < 0 || (float)$rate > 100)) $errors[] = 'Bonus rate must be between 0 and 100.';
    if (!$errors && val('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'An account with this email already exists.';
    if (!$errors && $username !== '' && val('SELECT id FROM users WHERE username = ?', [$username])) $errors[] = 'That username is taken.';

    if (!$errors) {
        $username = $username !== '' ? $username : username_from_email($email);
        q('INSERT INTO users (role, full_name, email, phone, username, password_hash, referral_code, commission_rate, source) VALUES ("agent",?,?,?,?,?,?,?,"admin")', [
            $name, $email, $phone, $username, $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : null,
            generate_referral_code(), $rate !== '' ? round((float)$rate, 2) : null,
        ]);
        $agent = row('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()]);
        $token = $pw === '' ? create_password_token((int)$agent['id'], 'setup') : null;
        $sent = email_agent_welcome($agent, $token, ($pw !== '' && $sendCreds) ? $pw : null);
        flash($sent ? 'success' : 'warning', $sent
            ? 'Agent created. A welcome email with the QR code' . ($token ? ' and a set-password link' : '') . ' was sent to ' . $email . '.'
            : 'Agent created, but the welcome email could not be sent. Check Brevo settings / Email log, then use "Resend welcome email".');
        redirect('admin/agent.php?id=' . $agent['id']);
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$where = 'u.role = "agent"';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.referral_code LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$total = (int)val("SELECT COUNT(*) FROM users u WHERE $where", $params);
$p = paginate($total, 25);
$agents = rows("SELECT u.*,
    (SELECT COUNT(*) FROM clients WHERE agent_id = u.id) refs,
    (SELECT COUNT(*) FROM clients WHERE agent_id = u.id AND status = 'won') won,
    (SELECT COALESCE(SUM(sale_amount),0) FROM commissions WHERE agent_id = u.id) sales,
    (SELECT COALESCE(SUM(amount),0) FROM commissions WHERE agent_id = u.id) earned,
    (SELECT COALESCE(SUM(amount),0) FROM payouts WHERE agent_id = u.id) paid
  FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);
$t = agent_totals(null);
$activeCount = (int)val('SELECT COUNT(*) FROM users WHERE role = "agent" AND status = "active"');
$allCount = (int)val('SELECT COUNT(*) FROM users WHERE role = "agent"');

$pageTitle = 'Agents';
$area = 'admin';
$active = 'agents';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Agents</h1><div class="text-muted small"><?= $allCount ?> agents · <?= $activeCount ?> active</div></div>
  <div class="actions"><button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newAgent"><i class="bi bi-person-plus me-1"></i>New agent</button></div>
</div>

<div class="collapse <?= $showForm ? 'show' : '' ?>" id="newAgent">
  <div class="card mb-3">
    <div class="card-header">Create agent profile</div>
    <div class="card-body">
      <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="<?= e(old('full_name')) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" autocapitalize="none" required></div>
          <div class="col-md-4"><label class="form-label">Phone</label><?= phone_input('phone', old('phone'), ['required' => true, 'autocomplete' => 'off', 'id' => 'new_phone']) ?></div>
          <div class="col-md-4"><label class="form-label">Username <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="username" value="<?= e(old('username')) ?>" autocapitalize="none" placeholder="Auto from email"></div>
          <div class="col-md-4"><label class="form-label">Password <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" type="text" name="password" value="<?= e(old('password')) ?>" autocomplete="new-password" placeholder="Leave blank to email a set-password link"></div>
          <div class="col-md-4"><label class="form-label">Bonus rate % <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" type="number" step="0.01" min="0" max="100" name="commission_rate" value="<?= e(old('commission_rate')) ?>" placeholder="Default <?= e(setting('commission_rate', '5')) ?>%"></div>
        </div>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="send_credentials" id="send_credentials" value="1" <?= !is_post() || old('send_credentials') ? 'checked' : '' ?>>
          <label class="form-check-label small" for="send_credentials">If a password is set, include the username &amp; password in the welcome email</label>
        </div>
        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> The agent receives a welcome email with their unique QR code. If you leave the password blank, the email includes a link to set their own password (valid 7 days).</div>
        <button class="btn btn-gold w-100 mt-3 btn-lg">Create agent &amp; send welcome email</button>
      </form>
    </div>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Referrals</div><div class="value"><?= $t['referrals'] ?></div><div class="sub"><?= $t['won'] ?> won</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Sales via agents</div><div class="value"><?= money($t['sales']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Bonus earned</div><div class="value"><?= money($t['earned']) ?></div><div class="sub">Paid <?= money($t['paid']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat gold"><div class="label">Bonus owed</div><div class="value"><?= money($t['balance']) ?></div></div></div>
</div>

<form class="filter-bar" method="get">
  <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone, code…">
  <button class="btn btn-outline-dark"><i class="bi bi-search"></i></button>
</form>

<?php if (!$agents): ?>
  <div class="empty"><i class="bi bi-person-badge"></i>No agents yet.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Agent</th><th>Joined</th><th>Referrals</th><th>Sales</th><th>Bonus earned</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($agents as $a): $bal = (float)$a['earned'] - (float)$a['paid']; ?>
      <tr class="clickable" data-href="<?= url('admin/agent.php?id=' . $a['id']) ?>">
        <td class="card-head">
          <div class="d-flex align-items-center gap-2">
            <span class="avatar gold d-none d-md-inline-flex"><?= e(initials($a['full_name'])) ?></span>
            <div class="min-w-0"><a href="<?= url('admin/agent.php?id=' . $a['id']) ?>" class="cell-title"><?= e($a['full_name']) ?></a>
            <div class="cell-sub"><?= e($a['referral_code']) ?> · <?= e($a['phone']) ?></div></div>
          </div>
        </td>
        <td data-label="Joined"><?= fdate($a['created_at']) ?><?= $a['source'] === 'self' ? ' <span class="text-muted small">(self)</span>' : '' ?></td>
        <td data-label="Referrals"><?= (int)$a['refs'] ?> <span class="text-muted small">(<?= (int)$a['won'] ?> won)</span></td>
        <td data-label="Sales"><?= money($a['sales']) ?></td>
        <td data-label="Bonus earned" class="fw-semibold"><?= money($a['earned']) ?></td>
        <td data-label="Paid" class="text-success"><?= money($a['paid']) ?></td>
        <td data-label="Balance" class="fw-semibold <?= $bal > 0 ? 'text-gold' : '' ?>"><?= money($bal) ?></td>
        <td data-label="Status"><?= status_badge($a['status']) ?><?= !$a['password_hash'] ? ' <span class="badge badge-soft-warning">Invite sent</span>' : '' ?></td>
        <td class="card-actions text-end text-nowrap">
          <?php if ($a['phone']): ?><a class="btn btn-sm btn-whatsapp" href="<?= e(whatsapp_link($a['phone'])) ?>" target="_blank" rel="noopener" title="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/agent.php?id=' . $a['id'] . '#details') ?>" title="Edit agent"><i class="bi bi-pencil"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_links($p) ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
