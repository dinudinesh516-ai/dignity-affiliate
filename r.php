<?php
/** Public referral landing page reached by scanning an agent's QR code: /r/CODE */
require __DIR__ . '/includes/bootstrap.php';

$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? $_POST['c'] ?? '')));
$agent = $code ? row('SELECT * FROM users WHERE referral_code = ? AND role = "agent"', [$code]) : null;

$error = '';
$done = $_GET['done'] ?? null;

if ($agent && $agent['status'] === 'active' && is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) { redirect('r.php?c=' . $code . '&done=1'); } // honeypot
    $bucket = 'ref:' . client_ip();
    if (too_many_attempts($bucket, 10, 60)) {
        $error = 'Too many submissions. Please try again later or call us directly.';
    } elseif (empty($_POST['consent'])) {
        $error = 'Please agree to be contacted by Dignity Jewels.';
    } else {
        hit_attempt($bucket);
        $res = create_referral($_POST, $agent, 'qr');
        if ($res['ok']) {
            redirect('r.php?c=' . $code . '&done=1');
        } elseif (isset($res['duplicate'])) {
            // Don't reveal who referred them — just reassure the client. Keep an audit note for admin.
            if (!empty($res['duplicate']['id']) && (int)$res['duplicate']['agent_id'] !== (int)$agent['id']) {
                log_activity((int)$res['duplicate']['id'], null, 'note', 'Client submitted the form again via the QR code of ' . $agent['full_name'] . ' (' . $agent['referral_code'] . '). Not credited — client already referred.');
            }
            redirect('r.php?c=' . $code . '&done=2');
        } else {
            $error = $res['error'];
        }
    }
}

$pageTitle = 'Tell us what you are looking for';
$area = 'public';
include __DIR__ . '/includes/header.php';
?>
<?php if (!$agent || $agent['status'] !== 'active'): ?>
  <div class="auth-card text-center">
    <i class="bi bi-qr-code-scan text-gold" style="font-size:2.5rem"></i>
    <h1 class="page-title mt-2">Link not active</h1>
    <p class="text-muted">This referral code isn't valid. You can still reach Dignity Jewels directly.</p>
    <a class="btn btn-primary w-100" href="<?= e(setting('company_website', 'https://dignityjewelry.com')) ?>">Visit dignityjewelry.com</a>
  </div>
<?php elseif ($done): ?>
  <div class="auth-card text-center">
    <i class="bi bi-gem text-gold" style="font-size:2.8rem"></i>
    <h1 class="page-title mt-2">Thank you!</h1>
    <?php if ($done === '2'): ?>
      <p class="text-muted">Looks like we already have your details on file. Our team will be in touch with you shortly.</p>
    <?php else: ?>
      <p class="text-muted">We've received your request. A Dignity Jewels specialist will contact you shortly.</p>
    <?php endif; ?>
    <p class="serif fs-5 fst-italic mb-4">You dream it, We make it.</p>
    <a class="btn btn-gold w-100 btn-lg" href="<?= e(setting('company_website', 'https://dignityjewelry.com')) ?>">Explore our collections</a>
    <div class="mt-3 small text-muted"><i class="bi bi-telephone"></i> <?= e(setting('company_phone', '')) ?></div>
  </div>
  <?php $bcProgram = 'hide'; $bcFlyerLink = false; include __DIR__ . '/includes/brand_content.php'; ?>
<?php else: ?>
  <div class="text-center mb-4">
    <div class="hero pb-0 pt-0"><div class="eyebrow mb-2">Welcome to Dignity Jewels</div></div>
    <h1 class="page-title">Let's create something beautiful</h1>
    <p class="text-muted mb-0 mt-2">Referred by <strong><?= e($agent['full_name']) ?></strong>. Share a few details and our specialist will reach out personally.</p>
  </div>
  <div class="auth-card">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('r.php?c=' . $code)) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="c" value="<?= e($code) ?>">
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <?php $formMode = 'client'; include __DIR__ . '/includes/referral_form.php'; ?>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" <?= old('consent') ? 'checked' : '' ?>>
        <label class="form-check-label small" for="consent">I agree to be contacted by Dignity Jewels by phone, WhatsApp or email about my request.</label>
      </div>
      <button class="btn btn-gold btn-lg w-100">Send my request</button>
    </form>
  </div>
  <?php $bcProgram = 'hide'; $bcFlyerLink = false; include __DIR__ . '/includes/brand_content.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
