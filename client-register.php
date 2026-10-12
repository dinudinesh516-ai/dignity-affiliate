<?php
/** Public page: clients register themselves directly (no agent / no referral). */
require __DIR__ . '/includes/bootstrap.php';

$error = '';
$done = $_GET['done'] ?? null;

if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) { redirect('client-register.php?done=1'); } // honeypot
    $bucket = 'selfreg:' . client_ip();
    if (too_many_attempts($bucket, 10, 60)) {
        $error = 'Too many submissions. Please try again later or call us directly.';
    } elseif (empty($_POST['consent'])) {
        $error = 'Please agree to be contacted by Dignity Jewels.';
    } else {
        hit_attempt($bucket);
        $res = create_referral($_POST, null, 'self');
        if ($res['ok']) {
            redirect('client-register.php?done=1');
        } elseif (isset($res['duplicate'])) {
            if (!empty($res['duplicate']['id'])) {
                log_activity((int)$res['duplicate']['id'], null, 'note', 'Client submitted the "Register as a Client" form again on the website.');
            }
            redirect('client-register.php?done=2');
        } else {
            $error = $res['error'];
        }
    }
}

$pageTitle = 'Register as a Client';
$area = 'public';
include __DIR__ . '/includes/header.php';
?>
<?php if ($done): ?>
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
<?php else: ?>
  <a href="<?= url() ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
  <div class="text-center mb-4">
    <div class="hero pb-0 pt-0"><div class="eyebrow mb-2">Welcome to Dignity Jewels</div></div>
    <h1 class="page-title">Let's create something beautiful</h1>
    <p class="text-muted mb-0 mt-2">Share a few details and our specialist will reach out personally. Free consultation, no obligation.</p>
  </div>
  <div class="auth-card">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <?php $formMode = 'client'; include __DIR__ . '/includes/referral_form.php'; ?>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" <?= old('consent') ? 'checked' : '' ?>>
        <label class="form-check-label small" for="consent">I agree to be contacted by Dignity Jewels by phone, WhatsApp or email about my request.</label>
      </div>
      <button class="btn btn-gold btn-lg w-100">Send my request</button>
    </form>
  </div>
<?php endif; ?>
<?php $bcProgram = 'hide'; $bcFlyerLink = false; include __DIR__ . '/includes/brand_content.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
