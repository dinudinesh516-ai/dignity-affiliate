<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_agent();

$error = '';
if (is_post()) {
    csrf_check();
    $res = create_referral($_POST, $me, 'agent');
    if ($res['ok']) {
        flash('success', 'Referral sent! Dignity Jewels has been notified and will contact ' . $res['client']['full_name'] . '.');
        redirect('agent/referrals.php');
    }
    if (isset($res['duplicate']) && !empty($res['duplicate']) && (int)$res['duplicate']['agent_id'] === (int)$me['id']) {
        $error = 'You have already referred this client — they are in your referrals list.';
    } elseif (isset($res['duplicate'])) {
        $error = 'This client has already been referred by another agent, so they cannot be referred again.';
    } else {
        $error = $res['error'];
    }
}

$pageTitle = 'Refer a client';
$area = 'agent';
$active = 'refer';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Refer a client</h1>
    <div class="text-muted small">We'll notify the Dignity team immediately.</div>
  </div>
</div>
<div class="row"><div class="col-lg-7">
  <div class="auth-card">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <?php $formMode = 'agent'; include dirname(__DIR__) . '/includes/referral_form.php'; ?>
      <div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Please make sure the client has agreed to be contacted. Each client can only be referred once — first come, first credited.</div>
      <button class="btn btn-primary btn-lg w-100"><i class="bi bi-send me-2"></i>Send referral</button>
    </form>
  </div>
</div>
<div class="col-lg-5 d-none d-lg-block">
  <div class="qr-card">
    <div class="small mb-2" style="color:#bdb5a8;position:relative;z-index:1">Or let the client scan your code</div>
    <div class="qr-box"><img src="<?= e(qr_image_url($me['referral_code'])) ?>" alt="QR"></div>
    <div class="code"><?= e($me['referral_code']) ?></div>
  </div>
</div></div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
