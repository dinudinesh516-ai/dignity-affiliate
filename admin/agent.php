<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$id = (int)($_GET['id'] ?? 0);
$agent = row('SELECT * FROM users WHERE id = ? AND role = "agent"', [$id]);
if (!$agent) { flash('warning', 'Agent not found.'); redirect('admin/agents.php'); }
$errors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $back = 'admin/agent.php?id=' . $id;

    if ($action === 'edit') {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $rate = trim((string)($_POST['commission_rate'] ?? ''));
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        if (!valid_username($username)) $errors[] = 'Invalid username.';
        if ($rate !== '' && (!is_numeric($rate) || (float)$rate < 0 || (float)$rate > 100)) $errors[] = 'Bonus rate must be between 0 and 100.';
        if (!$errors && val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) $errors[] = 'Email already used by another account.';
        if (!$errors && val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])) $errors[] = 'Username taken.';
        if (!$errors) {
            q('UPDATE users SET full_name=?, email=?, phone=?, username=?, commission_rate=? WHERE id=?',
                [$name, $email, $phone ?: null, $username, $rate !== '' ? round((float)$rate, 2) : null, $id]);
            flash('success', 'Agent updated. Rate changes apply to future bonuses.');
            redirect($back);
        }
    }

    if ($action === 'toggle') {
        $new = $agent['status'] === 'active' ? 'disabled' : 'active';
        q('UPDATE users SET status = ? WHERE id = ?', [$new, $id]);
        flash('success', $new === 'active' ? 'Agent re-activated.' : 'Agent disabled. They can no longer sign in and their QR code stops accepting referrals.');
        redirect($back);
    }

    if ($action === 'welcome') {
        $token = $agent['password_hash'] ? null : create_password_token($id, 'setup');
        $ok = email_agent_welcome($agent, $token);
        flash($ok ? 'success' : 'danger', $ok ? 'Welcome email with QR code re-sent.' : 'Email failed — check the Email log.');
        redirect($back);
    }

    if ($action === 'reset_link') {
        $token = create_password_token($id, 'reset');
        $ok = email_password_reset($agent, $token);
        flash($ok ? 'success' : 'danger', $ok ? 'Password reset link emailed to ' . $agent['email'] . '.' : 'Email failed — check the Email log.');
        redirect($back);
    }

    if ($action === 'set_password') {
        $pw = (string)($_POST['password'] ?? '');
        if ($err = validate_password($pw, $pw)) { flash('danger', $err); redirect($back); }
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        if (!empty($_POST['email_it'])) {
            brevo_send($agent['email'], $agent['full_name'], 'Your Dignity Jewels login details', email_layout('Your login details',
                '<p>Hi ' . e($agent['full_name']) . ',</p><p>Your password has been set by the Dignity Jewels team.</p>'
                . email_kv(['Login page' => url('login.php'), 'Username' => $agent['username'], 'Password' => $pw])
                . '<p style="font-size:13px;color:#8a8580">Please change your password after signing in.</p>'));
        }
        flash('success', 'Password updated.');
        redirect($back);
    }

    if ($action === 'payout') {
        $cm = row('SELECT * FROM commissions WHERE id = ? AND agent_id = ?', [(int)($_POST['commission_id'] ?? 0), $id]);
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $date = (string)($_POST['payout_date'] ?? '');
        $method = in_array($_POST['method'] ?? '', PAYOUT_METHODS, true) ? $_POST['method'] : 'Other';
        if (!$cm) { flash('danger', 'Bonus not found.'); redirect($back . '#bonuses'); }
        $remaining = round((float)$cm['amount'] - (float)$cm['amount_paid'], 2);
        if ($amount <= 0 || $amount > $remaining + 0.004) {
            flash('danger', 'Payout must be between ' . money(0.01) . ' and ' . money($remaining) . '.');
        } elseif (!DateTime::createFromFormat('Y-m-d', $date)) {
            flash('danger', 'Enter a valid date.');
        } else {
            q('INSERT INTO payouts (commission_id, agent_id, amount, payout_date, method, reference, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
                [$cm['id'], $id, $amount, $date, $method, trim((string)($_POST['reference'] ?? '')) ?: null, trim((string)($_POST['notes'] ?? '')) ?: null, $me['id']]);
            $payoutId = (int)db()->lastInsertId();
            recalc_commission((int)$cm['id']);
            if (!empty($_POST['notify'])) {
                try { email_agent_payout($agent, row('SELECT * FROM payouts WHERE id = ?', [$payoutId]), row('SELECT * FROM commissions WHERE id = ?', [$cm['id']])); } catch (Throwable $e) {}
            }
            flash('success', 'Bonus payout of ' . money($amount) . ' recorded' . ($amount >= $remaining - 0.004 ? ' — bonus fully paid.' : ' (partial).'));
        }
        redirect($back . '#bonuses');
    }

    if ($action === 'delete_payout') {
        $po = row('SELECT * FROM payouts WHERE id = ? AND agent_id = ?', [(int)($_POST['payout_id'] ?? 0), $id]);
        if ($po) {
            q('DELETE FROM payouts WHERE id = ?', [$po['id']]);
            recalc_commission((int)$po['commission_id']);
            flash('success', 'Payout removed.');
        }
        redirect($back . '#bonuses');
    }
}

$t = agent_totals($id);
$clients = rows("SELECT c.*,
    (SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id = c.id AND status <> 'cancelled') inv_total,
    (SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE client_id = c.id AND status <> 'cancelled') inv_paid
  FROM clients c WHERE c.agent_id = ? ORDER BY c.created_at DESC", [$id]);
$commissions = rows('SELECT cm.*, c.full_name client_name, i.invoice_no FROM commissions cm JOIN clients c ON c.id = cm.client_id JOIN invoices i ON i.id = cm.invoice_id WHERE cm.agent_id = ? ORDER BY cm.status = "paid", cm.earned_at DESC', [$id]);
$payouts = rows('SELECT p.*, c.full_name client_name, i.invoice_no FROM payouts p JOIN commissions cm ON cm.id = p.commission_id JOIN clients c ON c.id = cm.client_id JOIN invoices i ON i.id = cm.invoice_id WHERE p.agent_id = ? ORDER BY p.payout_date DESC, p.id DESC', [$id]);
$rate = agent_rate($agent);

$pageTitle = $agent['full_name'];
$area = 'admin';
$active = 'agents';
include dirname(__DIR__) . '/includes/header.php';
?>
<a href="<?= url('admin/agents.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Agents</a>
<div class="page-head">
  <div class="d-flex align-items-center gap-3 min-w-0">
    <span class="avatar lg gold"><?= e(initials($agent['full_name'])) ?></span>
    <div class="min-w-0">
      <h1 class="page-title text-truncate"><?= e($agent['full_name']) ?></h1>
      <div class="small text-muted"><?= status_badge($agent['status']) ?> <?= !$agent['password_hash'] ? '<span class="badge badge-soft-warning">Password not set</span>' : '' ?> Joined <?= fdate($agent['created_at']) ?></div>
    </div>
  </div>
  <div class="actions"><a href="#details" class="btn btn-sm btn-outline-dark" data-scroll-to="#details"><i class="bi bi-pencil me-1"></i>Edit details</a></div>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="d-grid gap-2 mb-3" style="grid-template-columns:repeat(3,1fr)">
  <a class="btn btn-whatsapp <?= $agent['phone'] ? '' : 'disabled' ?>" href="<?= e(whatsapp_link((string)$agent['phone'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
  <a class="btn btn-outline-dark" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$agent['phone'])) ?>"><i class="bi bi-telephone me-1"></i>Call</a>
  <a class="btn btn-outline-dark" href="mailto:<?= e($agent['email']) ?>"><i class="bi bi-envelope me-1"></i>Email</a>
</div>

<div class="row g-2 mb-1">
  <div class="col-6 col-lg-3"><div class="stat dark"><div class="label">Balance owed</div><div class="value"><?= money($t['balance']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Bonus earned</div><div class="value"><?= money($t['earned']) ?></div><div class="sub">Paid <?= money($t['paid']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Referrals</div><div class="value"><?= $t['referrals'] ?></div><div class="sub"><?= $t['won'] ?> won · sales <?= money($t['sales']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat gold"><div class="label">In progress</div><div class="value"><?= money($t['projected']) ?></div><div class="sub">Expected bonus</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="section-title" id="bonuses">Referral bonuses</div>
    <?php if (!$commissions): ?>
      <div class="empty py-4"><i class="bi bi-gift"></i>No bonuses yet. A bonus is credited when a referred client's invoice is fully paid.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table table-cards">
        <thead><tr><th>Client / invoice</th><th>Sale</th><th>Bonus</th><th>Paid</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($commissions as $c): $rem = round((float)$c['amount'] - (float)$c['amount_paid'], 2); ?>
          <tr>
            <td class="card-head"><div class="cell-title"><?= e($c['client_name']) ?></div><div class="cell-sub"><a href="<?= url('admin/invoice.php?id=' . $c['invoice_id']) ?>"><?= e($c['invoice_no']) ?></a> · <?= fdate($c['earned_at']) ?></div></td>
            <td data-label="Sale"><?= money($c['sale_amount']) ?></td>
            <td data-label="Bonus" class="fw-semibold"><?= money($c['amount']) ?> <span class="text-muted small fw-normal"><?= e(rtrim(rtrim($c['rate'], '0'), '.')) ?>%</span></td>
            <td data-label="Paid"><?= money($c['amount_paid']) ?></td>
            <td data-label="Status"><?= status_badge($c['status']) ?></td>
            <td class="card-actions text-end">
              <?php if ($rem > 0): ?>
                <button class="btn btn-sm btn-gold text-nowrap" data-bs-toggle="modal" data-bs-target="#payoutModal"
                  data-cid="<?= $c['id'] ?>" data-rem="<?= e($rem) ?>" data-label="<?= e($c['client_name'] . ' · ' . $c['invoice_no']) ?>">Mark paid</button>
              <?php else: ?><i class="bi bi-check-circle-fill text-success"></i><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($payouts): ?>
    <div class="section-title">Payout history</div>
    <div class="table-wrap">
      <table class="table table-cards">
        <thead><tr><th>Date</th><th>For</th><th>Method</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payouts as $po): ?>
          <tr>
            <td data-label="Date"><?= fdate($po['payout_date']) ?></td>
            <td data-label="For"><?= e($po['client_name']) ?> <span class="text-muted small"><?= e($po['invoice_no']) ?></span></td>
            <td data-label="Method"><?= e($po['method']) ?><?= $po['reference'] ? '<div class="cell-sub">#' . e($po['reference']) . '</div>' : '' ?></td>
            <td data-label="Amount" class="text-end fw-semibold text-success"><?= money($po['amount']) ?></td>
            <td class="card-actions text-end">
              <form method="post" data-confirm="Remove this payout?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payout"><input type="hidden" name="payout_id" value="<?= $po['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" aria-label="Remove payout"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="section-title">Referred clients (<?= count($clients) ?>)</div>
    <?php if (!$clients): ?>
      <div class="empty py-4"><i class="bi bi-people"></i>No referrals yet.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table table-cards">
        <thead><tr><th>Client</th><th>Date</th><th>Status</th><th>Sales</th></tr></thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
          <tr class="clickable" data-href="<?= url('admin/client.php?id=' . $c['id']) ?>">
            <td class="card-head"><a href="<?= url('admin/client.php?id=' . $c['id']) ?>" class="cell-title"><?= e($c['full_name']) ?></a><div class="cell-sub"><?= e($c['phone']) ?></div></td>
            <td data-label="Date"><?= fdate($c['created_at']) ?></td>
            <td data-label="Status"><?= status_badge($c['status']) ?></td>
            <td data-label="Sales"><?= (float)$c['inv_total'] > 0 ? money($c['inv_paid']) . ' <span class="text-muted small">/ ' . money($c['inv_total']) . '</span>' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="qr-card mt-lg-4">
      <div class="qr-box"><img src="<?= e(qr_image_url($agent['referral_code'])) ?>" alt="QR"></div>
      <div class="code"><?= e($agent['referral_code']) ?></div>
      <div class="d-flex gap-2 justify-content-center mt-2">
        <a class="btn btn-sm btn-outline-light" href="<?= e(qr_image_url($agent['referral_code'], true)) ?>"><i class="bi bi-download"></i> QR</a>
        <button class="btn btn-sm btn-outline-light" data-copy="<?= e(referral_link($agent['referral_code'])) ?>"><i class="bi bi-link-45deg"></i> Link</button>
      </div>
    </div>

    <div class="card mt-3" id="details">
      <div class="card-header">Edit details</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="edit">
          <div class="mb-2"><label class="form-label small">Full name</label><input class="form-control" name="full_name" value="<?= e($agent['full_name']) ?>" required></div>
          <div class="mb-2"><label class="form-label small">Email</label><input class="form-control" type="email" name="email" value="<?= e($agent['email']) ?>" required></div>
          <div class="mb-2"><label class="form-label small">Phone</label><?= phone_input('phone', $agent['phone'], ['autocomplete' => 'off']) ?></div>
          <div class="mb-2"><label class="form-label small">Username</label><input class="form-control" name="username" value="<?= e($agent['username']) ?>" required></div>
          <div class="mb-3"><label class="form-label small">Bonus rate %</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="commission_rate" value="<?= e($agent['commission_rate']) ?>" placeholder="Default <?= e(setting('commission_rate', '5')) ?>%">
            <div class="form-text">Current: <?= e(rtrim(rtrim(number_format($rate, 2), '0'), '.')) ?>%</div></div>
          <button class="btn btn-primary w-100">Save details</button>
        </form>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">Account</div>
      <div class="card-body d-grid gap-2">
        <div class="small text-muted">Last sign-in: <?= fdatetime($agent['last_login_at']) ?></div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="welcome"><button class="btn btn-outline-dark w-100"><i class="bi bi-envelope me-1"></i>Resend welcome email + QR</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset_link"><button class="btn btn-outline-dark w-100"><i class="bi bi-key me-1"></i>Email password reset link</button></form>
        <button class="btn btn-outline-dark w-100" data-bs-toggle="collapse" data-bs-target="#setPw"><i class="bi bi-lock me-1"></i>Set password manually</button>
        <div class="collapse" id="setPw">
          <form method="post" class="border rounded-3 p-2">
            <?= csrf_field() ?><input type="hidden" name="action" value="set_password">
            <input class="form-control mb-2" type="text" name="password" placeholder="New password" autocomplete="new-password" required>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="email_it" value="1" id="email_it" checked><label class="form-check-label small" for="email_it">Email login details to agent</label></div>
            <button class="btn btn-primary btn-sm w-100">Set password</button>
          </form>
        </div>
        <form method="post" data-confirm="<?= $agent['status'] === 'active' ? 'Disable this agent? They will not be able to sign in or receive new referrals.' : 'Re-activate this agent?' ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
          <button class="btn w-100 <?= $agent['status'] === 'active' ? 'btn-outline-danger' : 'btn-success' ?>"><?= $agent['status'] === 'active' ? 'Disable agent' : 'Activate agent' ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="payoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="payout"><input type="hidden" name="commission_id" id="poCid">
      <div class="modal-header"><h5 class="modal-title">Record bonus payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="small text-muted mb-3" id="poLabel"></div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" inputmode="decimal" name="amount" id="poAmount" required>
            <div class="form-text">Remaining: <span id="poRem"></span></div></div>
          <div class="col-6"><label class="form-label">Date</label><input class="form-control" type="date" name="payout_date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-12"><label class="form-label">Method</label><select class="form-select" name="method"><?php foreach (PAYOUT_METHODS as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">Reference <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="reference"></div>
          <div class="col-12"><label class="form-label">Note <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="notes" maxlength="255"></div>
        </div>
        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="notify" value="1" id="poNotify" checked><label class="form-check-label small" for="poNotify">Email the agent a payment confirmation</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gold">Save payout</button></div>
    </form>
  </div>
</div>
<?php
$pageScripts = '<script>
document.getElementById("payoutModal").addEventListener("show.bs.modal", function (e) {
  var b = e.relatedTarget;
  document.getElementById("poCid").value = b.getAttribute("data-cid");
  document.getElementById("poAmount").value = b.getAttribute("data-rem");
  document.getElementById("poAmount").max = b.getAttribute("data-rem");
  document.getElementById("poRem").textContent = ' . json_encode(setting('currency_symbol', '$')) . ' + parseFloat(b.getAttribute("data-rem")).toFixed(2);
  document.getElementById("poLabel").textContent = b.getAttribute("data-label");
});
</script>';
include dirname(__DIR__) . '/includes/footer.php';
