<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$id = (int)($_GET['id'] ?? 0);
$client = row('SELECT * FROM clients WHERE id = ?', [$id]);
if (!$client) { flash('warning', 'Client not found.'); redirect('admin/clients.php'); }
$agent = $client['agent_id'] ? row('SELECT * FROM users WHERE id = ?', [$client['agent_id']]) : null;
$statuses = ['new' => 'New', 'contacted' => 'Contacted', 'negotiating' => 'Negotiating', 'won' => 'Won', 'lost' => 'Lost'];
$errors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'status' && isset($statuses[$_POST['status'] ?? ''])) {
        $new = $_POST['status'];
        if ($new !== $client['status']) {
            q('UPDATE clients SET status = ? WHERE id = ?', [$new, $id]);
            log_activity($id, (int)$me['id'], 'status', 'Status changed from ' . $statuses[$client['status']] . ' to ' . $statuses[$new] . '.');
            flash('success', 'Status updated.');
        }
        redirect('admin/client.php?id=' . $id);
    }

    if ($action === 'note') {
        $body = trim((string)($_POST['body'] ?? ''));
        $type = in_array($_POST['type'] ?? '', ['note', 'call', 'whatsapp'], true) ? $_POST['type'] : 'note';
        if ($body !== '') {
            log_activity($id, (int)$me['id'], $type, $body);
            if ($client['status'] === 'new' && $type !== 'note') q('UPDATE clients SET status = "contacted" WHERE id = ?', [$id]);
            flash('success', 'Note added.');
        }
        redirect('admin/client.php?id=' . $id);
    }

    if ($action === 'email') {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if (!$client['email']) $errors[] = 'This client has no email address.';
        if ($subject === '' || $message === '') $errors[] = 'Subject and message are required.';
        if (!$errors) {
            $html = email_layout($subject, nl2br(e($message)));
            $ok = brevo_send($client['email'], $client['full_name'], $subject, $html, [], $me['email']);
            if ($ok) {
                log_activity($id, (int)$me['id'], 'email', 'Email sent: "' . $subject . "\"\n" . $message);
                if ($client['status'] === 'new') q('UPDATE clients SET status = "contacted" WHERE id = ?', [$id]);
                flash('success', 'Email sent to ' . $client['email'] . '.');
            } else {
                flash('danger', 'Email could not be sent. Check Settings → Email log for details.');
            }
            redirect('admin/client.php?id=' . $id);
        }
    }

    if ($action === 'edit') {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $newAgent = (int)($_POST['agent_id'] ?? 0) ?: null;
        if ($name === '') $errors[] = 'Name is required.';
        if (strlen(phone_key($phone)) < 8) $errors[] = 'Enter a valid phone number.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        $pk = phone_key($phone);
        $ek = $email !== '' ? strtolower($email) : null;
        if (!$errors) {
            $dup = row('SELECT id, full_name FROM clients WHERE id <> ? AND (phone_key = ? OR (email_key IS NOT NULL AND email_key = ?))', [$id, $pk, $ek ?? '__none__']);
            if ($dup) $errors[] = 'Another client (' . $dup['full_name'] . ') already has this phone or email.';
        }
        if (!$errors && $newAgent !== ($client['agent_id'] ? (int)$client['agent_id'] : null)) {
            if (val('SELECT COUNT(*) FROM commissions WHERE client_id = ?', [$id])) {
                $errors[] = 'The referring agent can\'t be changed after a bonus has been credited.';
            }
        }
        $interestsIn = $_POST['interests'] ?? [];
        $interestsStr = is_array($interestsIn) ? implode(', ', array_values(array_intersect(INTEREST_OPTIONS, $interestsIn))) : trim((string)$interestsIn);
        if (!$errors) {
            q('UPDATE clients SET full_name=?, phone=?, phone_key=?, email=?, email_key=?, interests=?, looking_for=?, budget=?, preferred_contact=?, agent_id=? WHERE id=?', [
                $name, $phone, $pk, $email ?: null, $ek,
                $interestsStr ?: null, trim((string)($_POST['looking_for'] ?? '')) ?: null,
                trim((string)($_POST['budget'] ?? '')) ?: null,
                in_array($_POST['preferred_contact'] ?? '', ['whatsapp', 'call', 'email'], true) ? $_POST['preferred_contact'] : 'whatsapp',
                $newAgent, $id,
            ]);
            if ($newAgent !== ($client['agent_id'] ? (int)$client['agent_id'] : null)) {
                q('UPDATE invoices SET agent_id = ? WHERE client_id = ?', [$newAgent, $id]);
                $nm = $newAgent ? val('SELECT full_name FROM users WHERE id = ?', [$newAgent]) : 'no agent';
                log_activity($id, (int)$me['id'], 'note', 'Referring agent changed to ' . $nm . '.');
            }
            log_activity($id, (int)$me['id'], 'note', 'Client details edited by ' . $me['full_name'] . '.');
            flash('success', 'Client details saved.');
            redirect('admin/client.php?id=' . $id);
        }
    }

    if ($action === 'delete') {
        if (val('SELECT COUNT(*) FROM invoices WHERE client_id = ?', [$id])) {
            flash('danger', 'Clients with invoices can\'t be deleted. Mark them as Lost instead.');
            redirect('admin/client.php?id=' . $id);
        }
        q('DELETE FROM clients WHERE id = ?', [$id]);
        flash('success', 'Client deleted.');
        redirect('admin/clients.php');
    }
}

$invoices = rows('SELECT * FROM invoices WHERE client_id = ? ORDER BY invoice_date DESC, id DESC', [$id]);
$activity = rows('SELECT a.*, u.full_name user_name, u.role user_role FROM client_activity a LEFT JOIN users u ON u.id = a.user_id WHERE a.client_id = ? ORDER BY a.created_at DESC, a.id DESC', [$id]);
$agents = rows('SELECT id, full_name, referral_code FROM users WHERE role = "agent" ORDER BY full_name');

$interest = $client['interests'] ?: 'jewelry';
$typeIcons = ['note' => 'journal-text', 'email' => 'envelope', 'whatsapp' => 'whatsapp', 'call' => 'telephone', 'status' => 'flag', 'invoice' => 'receipt', 'payment' => 'cash'];

$pageTitle = $client['full_name'];
$area = 'admin';
$active = 'clients';
include dirname(__DIR__) . '/includes/header.php';
?>
<a href="<?= url('admin/clients.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Clients</a>
<div class="page-head">
  <div class="min-w-0">
    <h1 class="page-title"><?= e($client['full_name']) ?></h1>
    <div class="mt-1"><?= status_badge($client['status']) ?> <span class="text-muted small ms-1">Referred <?= fdate($client['created_at']) ?></span></div>
  </div>
  <form method="post" class="d-flex gap-2 align-items-center">
    <a href="#editBox" class="btn btn-sm btn-outline-dark text-nowrap" data-bs-toggle="collapse" data-bs-target="#editBox" data-scroll-to="#editBox"><i class="bi bi-pencil me-1"></i>Edit</a>
    <?= csrf_field() ?><input type="hidden" name="action" value="status">
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()" aria-label="Change status">
      <?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $client['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="d-grid gap-2 mb-3" style="grid-template-columns:repeat(3,1fr)">
  <a class="btn btn-whatsapp" href="<?= e(url('admin/contact.php?id=' . $id . '&via=whatsapp')) ?>" target="_blank" rel="noopener" data-contact><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
  <a class="btn btn-outline-dark" href="<?= e(url('admin/contact.php?id=' . $id . '&via=call')) ?>" data-contact><i class="bi bi-telephone me-1"></i>Call</a>
  <a class="btn btn-outline-dark <?= $client['email'] ? '' : 'disabled' ?>" href="#email" data-bs-toggle="collapse" data-bs-target="#emailBox"><i class="bi bi-envelope me-1"></i>Email</a>
</div>

<div class="collapse <?= (!empty($errors) && ($_POST['action'] ?? '') === 'email') ? 'show' : '' ?>" id="emailBox">
  <div class="card mb-3" id="email">
    <div class="card-header">Email <?= e($client['email']) ?></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="email">
        <div class="mb-2"><label class="form-label">Subject</label><input class="form-control" name="subject" value="<?= e(old('subject', 'Your jewelry request with Dignity Jewels')) ?>" required></div>
        <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="6" required><?= e(old('message', "Hi " . explode(' ', $client['full_name'])[0] . ",\n\nThank you for your interest in Dignity Jewels" . ($agent ? ' — ' . $agent['full_name'] . ' told us you are looking for ' . mb_strtolower($interest) : '') . ".\n\nI'd love to help you find the perfect piece. When would be a good time to talk?\n\nWarm regards,\n" . $me['full_name'] . "\nDignity Jewels")) ?></textarea></div>
        <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Send email</button>
        <div class="form-text">Replies will go to <?= e($me['email']) ?>.</div>
      </form>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">Request<a href="#" data-bs-toggle="collapse" data-bs-target="#editBox" class="small fw-normal">Edit</a></div>
      <div class="card-body">
        <dl class="kv mb-0">
          <dt>Phone</dt><dd><a href="<?= e(url('admin/contact.php?id=' . $id . '&via=call')) ?>" data-contact><?= e($client['phone']) ?></a></dd>
          <dt>Email</dt><dd><?= $client['email'] ? e($client['email']) : '—' ?></dd>
          <dt>Prefers</dt><dd><?= e(ucfirst($client['preferred_contact'])) ?></dd>
          <dt>Interested in</dt><dd><?= e($client['interests'] ?: '—') ?></dd>
          <dt>Budget</dt><dd><?= e($client['budget'] ?: '—') ?></dd>
          <dt>Source</dt><dd><?= ['qr' => 'QR code', 'agent' => 'Agent', 'admin' => 'Admin', 'self' => 'Self-registered (website)'][$client['source']] ?? e($client['source']) ?></dd>
        </dl>
        <?php if ($client['looking_for']): ?>
          <div class="mt-3 p-3 bg-gold-soft rounded-3 small"><i class="bi bi-chat-quote text-gold me-1"></i><?= nl2br(e($client['looking_for'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="collapse <?= ((!empty($errors) && ($_POST['action'] ?? '') === 'edit') || !empty($_GET['edit'])) ? 'show' : '' ?>" id="editBox">
      <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">Edit client<button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#editBox" aria-label="Close"></button></div><div class="card-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="edit">
          <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="<?= e($client['full_name']) ?>" required></div>
          <div class="mb-2"><label class="form-label">Phone</label><?= phone_input('phone', $client['phone'], ['required' => true, 'autocomplete' => 'off']) ?></div>
          <div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($client['email']) ?>"></div>
          <div class="mb-2"><label class="form-label d-block">Interested in</label>
            <?php $cur = array_map('trim', explode(',', (string)$client['interests'])); foreach (INTEREST_OPTIONS as $opt): ?>
              <label class="chip-check"><input type="checkbox" name="interests[]" value="<?= e($opt) ?>" <?= in_array($opt, $cur, true) ? 'checked' : '' ?>><span><?= e($opt) ?></span></label>
            <?php endforeach; ?></div>
          <div class="mb-2"><label class="form-label">Note</label><textarea class="form-control" name="looking_for" rows="3"><?= e($client['looking_for']) ?></textarea></div>
          <div class="mb-2"><label class="form-label">Budget</label><input class="form-control" name="budget" value="<?= e($client['budget']) ?>"></div>
          <div class="mb-2"><label class="form-label">Preferred contact</label>
            <select class="form-select" name="preferred_contact"><?php foreach (['whatsapp' => 'WhatsApp', 'call' => 'Call', 'email' => 'Email'] as $k => $l): ?><option value="<?= $k ?>" <?= $client['preferred_contact'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="mb-3"><label class="form-label">Referring agent</label>
            <select class="form-select" name="agent_id" data-searchable data-placeholder="Type agent name or code…"><option value="">— Direct (no agent) —</option><?php foreach ($agents as $a): ?><option value="<?= $a['id'] ?>" <?= (int)$client['agent_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?> (<?= e($a['referral_code']) ?>)</option><?php endforeach; ?></select>
            <div class="form-text">Only change this to correct a mistake.</div></div>
          <button class="btn btn-primary w-100">Save changes</button>
        </form>
        <?php if (!$invoices): ?>
        <form method="post" class="mt-2" data-confirm="Delete this client permanently?">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <button class="btn btn-link text-danger w-100 btn-sm">Delete client</button>
        </form>
        <?php endif; ?>
      </div></div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Referred by</div>
      <div class="card-body">
        <?php if ($agent): ?>
          <div class="d-flex align-items-center gap-3">
            <span class="avatar gold"><?= e(initials($agent['full_name'])) ?></span>
            <div class="min-w-0 flex-grow-1">
              <a href="<?= url('admin/agent.php?id=' . $agent['id']) ?>" class="cell-title d-block text-truncate"><?= e($agent['full_name']) ?></a>
              <div class="cell-sub"><?= e($agent['referral_code']) ?> · <?= e($agent['phone'] ?: $agent['email']) ?></div>
            </div>
            <?php if ($agent['phone']): ?><a class="btn btn-sm btn-whatsapp" href="<?= e(whatsapp_link($agent['phone'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
          </div>
        <?php else: ?><span class="text-muted">Direct client (no agent)</span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="d-flex justify-content-between align-items-center">
      <div class="section-title mt-0 mt-lg-0">Invoices</div>
      <a href="<?= url('admin/invoice-edit.php?client=' . $id) ?>" class="btn btn-sm btn-gold"><i class="bi bi-plus-lg me-1"></i>New invoice</a>
    </div>
    <?php if (!$invoices): ?>
      <div class="empty py-4 mt-2"><i class="bi bi-receipt"></i>No invoices yet. Create one when the client confirms the sale.</div>
    <?php else: ?>
    <div class="table-wrap mt-2">
      <table class="table table-cards">
        <thead><tr><th>Invoice</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): $pc = pct((float)$i['amount_paid'], (float)$i['total']); ?>
          <tr class="clickable" data-href="<?= url('admin/invoice.php?id=' . $i['id']) ?>">
            <td class="card-head"><a href="<?= url('admin/invoice.php?id=' . $i['id']) ?>" class="cell-title"><?= e($i['invoice_no']) ?></a><div class="cell-sub"><?= fdate($i['invoice_date']) ?></div></td>
            <td data-label="Total"><?= money($i['total']) ?></td>
            <td data-label="Paid"><?= money($i['amount_paid']) ?> <span class="text-muted small">(<?= $pc ?>%)</span></td>
            <td data-label="Status"><?= status_badge($i['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="section-title">Activity</div>
    <div class="card mb-3"><div class="card-body">
      <form method="post" class="mb-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea class="form-control mb-2" name="body" rows="2" placeholder="Add a note (call summary, next steps…)" required></textarea>
        <div class="d-flex gap-2">
          <select name="type" class="form-select form-select-sm" style="max-width:170px"><option value="note">Note</option><option value="call">Call log</option><option value="whatsapp">WhatsApp log</option></select>
          <button class="btn btn-sm btn-primary ms-auto">Add</button>
        </div>
      </form>
      <?php if (!$activity): ?><div class="text-muted small">No activity yet.</div><?php else: ?>
      <ul class="timeline">
        <?php foreach ($activity as $a): ?>
          <li>
            <div class="meta"><i class="bi bi-<?= $typeIcons[$a['type']] ?? 'dot' ?>"></i> <?= fdatetime($a['created_at']) ?><?= $a['user_name'] ? ' · ' . e($a['user_name']) : '' ?></div>
            <div class="small" style="white-space:pre-line"><?= e($a['body']) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
