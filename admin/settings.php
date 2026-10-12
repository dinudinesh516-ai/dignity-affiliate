<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$fields = [
    'commission_rate'          => ['Default referral bonus %', 'number', 'Applies to agents without their own rate.'],
    'commission_basis'         => ['Bonus calculated on', 'select', '', ['total' => 'Invoice total (incl. tax)', 'net' => 'Subtotal after discount (excl. tax)']],
    'currency_symbol'          => ['Currency symbol', 'text', ''],
    'default_country_code'     => ['Default phone country code', 'text', 'Used for WhatsApp links when a number has no + prefix (1 = USA).'],
    'notify_emails'            => ['Admin notification emails', 'text', 'Comma-separated. New referrals & agent sign-ups are sent here.'],
    'invoice_prefix'           => ['Invoice number prefix', 'text', 'e.g. DJ- → DJ-2026-0001'],
    'default_tax_rate'         => ['Default tax %', 'number', ''],
    'client_confirmation_email'=> ['Send "thank you" email to referred clients', 'select', '', ['1' => 'Yes', '0' => 'No']],
    'whatsapp_template'        => ['WhatsApp opening message', 'textarea', 'Placeholders: {client}, {agent}, {interest}'],
    'company_name'             => ['Company name', 'text', ''],
    'company_address'          => ['Company address', 'text', ''],
    'company_phone'            => ['Company phone', 'text', ''],
    'company_website'          => ['Website', 'text', ''],
    'logo_url'                 => ['Logo image URL', 'text', 'Shown in the app header and emails.'],
];

if (is_post()) {
    csrf_check();
    if (($_POST['action'] ?? '') === 'test_email') {
        $ok = brevo_send($me['email'], $me['full_name'], 'Dignity Jewels — test email', email_layout('It works!', '<p>Your Brevo email integration is set up correctly.</p>'));
        flash($ok ? 'success' : 'danger', $ok ? 'Test email sent to ' . $me['email'] . '.' : 'Test email failed. See the Email log for the Brevo response.');
        redirect('admin/settings.php');
    }
    foreach ($fields as $k => $f) {
        if (!array_key_exists($k, $_POST)) continue;
        $v = trim((string)$_POST[$k]);
        if ($f[1] === 'number') $v = (string)max(0, min(100, round((float)$v, 2)));
        if ($f[1] === 'select' && !array_key_exists($v, $f[3])) continue;
        save_setting($k, $v);
    }
    flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

global $CONFIG;
$brevoOk = !empty($CONFIG['BREVO_API_KEY']) && strpos($CONFIG['BREVO_API_KEY'], 'CHANGE_ME') !== 0;

$pageTitle = 'Settings';
$area = 'admin';
$active = 'settings';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head"><h1 class="page-title">Settings</h1></div>
<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" class="card">
      <div class="card-body">
        <?= csrf_field() ?>
        <?php foreach ($fields as $k => $f): $v = setting($k, ''); ?>
          <div class="mb-3">
            <label class="form-label" for="f_<?= $k ?>"><?= e($f[0]) ?></label>
            <?php if ($f[1] === 'select'): ?>
              <select class="form-select" id="f_<?= $k ?>" name="<?= $k ?>"><?php foreach ($f[3] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string)$v === (string)$ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
            <?php elseif ($f[1] === 'textarea'): ?>
              <textarea class="form-control" id="f_<?= $k ?>" name="<?= $k ?>" rows="3"><?= e($v) ?></textarea>
            <?php else: ?>
              <input class="form-control" id="f_<?= $k ?>" name="<?= $k ?>" type="<?= $f[1] === 'number' ? 'number' : 'text' ?>" <?= $f[1] === 'number' ? 'step="0.01" min="0" max="100"' : '' ?> value="<?= e($v) ?>">
            <?php endif; ?>
            <?php if ($f[2]): ?><div class="form-text"><?= e($f[2]) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-primary btn-lg w-100">Save settings</button>
      </div>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Email (Brevo)</div>
      <div class="card-body">
        <p class="small mb-2">Status: <?= $brevoOk ? '<span class="badge badge-soft-success">API key set</span>' : '<span class="badge badge-soft-danger">API key missing</span>' ?></p>
        <p class="small text-muted">Sender: <?= e($CONFIG['BREVO_SENDER_NAME']) ?> &lt;<?= e($CONFIG['BREVO_SENDER_EMAIL']) ?>&gt;<br>The API key and sender are set in <code>config.php</code>. The sender must be verified in Brevo.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_email"><button class="btn btn-outline-dark w-100">Send test email to me</button></form>
        <a href="<?= url('admin/email-log.php') ?>" class="btn btn-link w-100 mt-1">View email log</a>
      </div>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
