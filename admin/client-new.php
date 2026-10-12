<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$agents = rows('SELECT * FROM users WHERE role = "agent" ORDER BY full_name');
$error = '';
$dupLink = null;
if (is_post()) {
    csrf_check();
    $agent = null;
    if (!empty($_POST['agent_id'])) {
        $agent = row('SELECT * FROM users WHERE id = ? AND role = "agent"', [(int)$_POST['agent_id']]);
    }
    $res = create_referral($_POST, $agent, $agent ? 'agent' : 'admin', false);
    if ($res['ok']) {
        flash('success', 'Client added.');
        redirect('admin/client.php?id=' . $res['client']['id']);
    }
    $error = $res['error'];
    if (!empty($res['duplicate']['id'])) {
        $dupLink = url('admin/client.php?id=' . $res['duplicate']['id']);
        $by = $res['duplicate']['agent_id'] ? val('SELECT full_name FROM users WHERE id = ?', [$res['duplicate']['agent_id']]) : null;
        $error = 'This client already exists' . ($by ? ' and was referred by ' . $by : '') . '.';
    }
}

$pageTitle = 'Add client';
$area = 'admin';
$active = 'clients';
include dirname(__DIR__) . '/includes/header.php';
?>
<a href="<?= url('admin/clients.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Clients</a>
<div class="page-head"><h1 class="page-title">Add client</h1></div>
<div class="row"><div class="col-lg-7">
  <div class="auth-card">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?><?= $dupLink ? ' <a href="' . e($dupLink) . '" class="alert-link">Open existing client</a>' : '' ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="agent_id">Referred by agent</label>
        <select class="form-select" id="agent_id" name="agent_id" data-searchable data-placeholder="Type agent name or code…">
          <option value="">— No agent (direct client) —</option>
          <?php foreach ($agents as $a): ?><option value="<?= $a['id'] ?>" <?= (int)old('agent_id') === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?> (<?= e($a['referral_code']) ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <?php $formMode = 'admin'; include dirname(__DIR__) . '/includes/referral_form.php'; ?>
      <button class="btn btn-primary btn-lg w-100">Save client</button>
    </form>
  </div>
</div></div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
