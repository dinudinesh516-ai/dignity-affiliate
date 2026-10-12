<?php
// Referral links /r/CODE — handled here as well, in case the server sends unknown
// paths to index.php instead of applying the .htaccess rewrite (e.g. Nginx try_files).
$__path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (preg_match('#/r/([A-Za-z0-9]+)/?$#', $__path, $__m)) {
    $_GET['c'] = $__m[1];
    require __DIR__ . '/r.php';
    exit;
}
require __DIR__ . '/includes/bootstrap.php';
if ($u = current_user()) redirect(home_for($u));

$rate = rtrim(rtrim(number_format((float)setting('commission_rate', '5'), 2), '0'), '.');
$pageTitle = 'Affiliate Program';
$area = 'public';
$mainWide = true;
include __DIR__ . '/includes/header.php';
?>
<div class="narrow">
<section class="hero">
  <div class="eyebrow mb-2">Affiliate Program</div>
  <h1>Refer clients.<br>Earn <?= e($rate) ?>% on every sale.</h1>
  <p class="text-muted mt-3 mb-4">Join the Dignity Jewels affiliate network. Share your personal QR code, and earn a referral bonus when your clients purchases from us.</p>
  <div class="d-grid gap-2">
    <a href="<?= url('register.php') ?>" class="btn btn-gold btn-lg"><i class="bi bi-person-plus me-2"></i>Register to become an Agent</a>
    <a href="<?= url('client-register.php') ?>" class="btn btn-dark btn-lg"><i class="bi bi-gem me-2"></i>Register as a Client</a>
    <a href="<?= url('login.php') ?>" class="btn btn-outline-dark btn-lg">Sign in</a>
    <a href="https://dealersclubs.com/flyer/" target="_blank" rel="noopener" class="btn btn-link text-gold"><i class="bi bi-file-earmark-richtext me-1"></i>View our flyer</a>
  </div>
</section>

<div class="auth-card mt-2">
  <h2 class="h4 mb-2">How it works</h2>
  <div class="feature"><i class="bi bi-qr-code"></i><div><strong>Get your unique QR code</strong><div class="text-muted small">Sent to your email the moment you register, and always available in your dashboard.</div></div></div>
  <div class="feature"><i class="bi bi-people"></i><div><strong>Refer clients</strong><div class="text-muted small">Clients scan your code and tell us what they're looking for — or add them yourself from your dashboard.</div></div></div>
  <div class="feature"><i class="bi bi-gem"></i><div><strong>We handle the sale</strong><div class="text-muted small">Our team contacts your client personally and creates their dream piece.</div></div></div>
  <div class="feature"><i class="bi bi-wallet2"></i><div><strong>Earn <?= e($rate) ?>%</strong><div class="text-muted small">Once your client has paid in full, <?= e($rate) ?>% of the sale is credited to you. Track everything live.</div></div></div>
</div>
</div>

<?php $bcProgram = 'open'; $bcFlyerLink = true; include __DIR__ . '/includes/brand_content.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
