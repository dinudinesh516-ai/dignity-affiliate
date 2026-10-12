<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_agent();
$t = agent_totals((int)$me['id']);
$link = referral_link($me['referral_code']);
$recent = rows('SELECT * FROM clients WHERE agent_id = ? ORDER BY created_at DESC LIMIT 5', [$me['id']]);
$rate = rtrim(rtrim(number_format(agent_rate($me), 2), '0'), '.');
$first = explode(' ', trim($me['full_name']))[0];

$pageTitle = 'Home';
$area = 'agent';
$active = 'dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <div class="text-muted small">Welcome back,</div>
    <h1 class="page-title"><?= e($first) ?></h1>
  </div>
  <div class="actions d-none d-lg-flex"><a href="<?= url('agent/refer.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Refer a client</a></div>
</div>

<div class="row g-3">
  <div class="col-lg-5 order-lg-2">
    <div class="qr-card">
      <div class="small text-uppercase mb-2" style="letter-spacing:2px;color:#bdb5a8;position:relative;z-index:1">My referral QR</div>
      <div class="qr-box"><img src="<?= e(qr_image_url($me['referral_code'])) ?>" alt="My referral QR code"></div>
      <div class="code"><?= e($me['referral_code']) ?></div>
      <div class="small mt-1 mb-3" style="color:#bdb5a8;position:relative;z-index:1">Clients scan this to send their request to Dignity — credited to you.</div>
      <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr">
        <a class="btn btn-gold" href="<?= e('https://wa.me/?text=' . rawurlencode('Looking for fine jewelry? Tell Dignity Jewels what you have in mind here: ' . $link)) ?>" target="_blank" rel="noopener" data-share="<?= e($link) ?>" data-share-text="Looking for fine jewelry? Tell Dignity Jewels what you have in mind:"><i class="bi bi-share me-1"></i>Share</a>
        <a class="btn btn-outline-light" href="<?= e(qr_image_url($me['referral_code'], true)) ?>"><i class="bi bi-download me-1"></i>Save QR</a>
      </div>
      <button class="btn btn-link btn-sm text-white-50 mt-2" data-copy="<?= e($link) ?>"><i class="bi bi-link-45deg"></i> Copy link</button>
    </div>
  </div>

  <div class="col-lg-7 order-lg-1">
    <div class="row g-2">
      <div class="col-12">
        <div class="stat dark">
          <div class="label">Bonus balance (unpaid)</div>
          <div class="value" style="font-size:1.9rem"><?= money($t['balance']) ?></div>
          <div class="sub">Earned <?= money($t['earned']) ?> · Paid <?= money($t['paid']) ?></div>
        </div>
      </div>
      <div class="col-6"><div class="stat"><div class="label">Referrals</div><div class="value"><?= (int)$t['referrals'] ?></div><div class="sub"><?= (int)$t['won'] ?> purchased</div></div></div>
      <div class="col-6"><div class="stat gold"><div class="label">In progress</div><div class="value"><?= money($t['projected']) ?></div><div class="sub">Expected bonus</div></div></div>
      <div class="col-6"><div class="stat"><div class="label">Total sales</div><div class="value"><?= money($t['sales']) ?></div><div class="sub">From your clients</div></div></div>
      <div class="col-6"><div class="stat"><div class="label">Your rate</div><div class="value"><?= e($rate) ?>%</div><div class="sub">Per completed sale</div></div></div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <div class="section-title">Recent referrals</div>
      <a href="<?= url('agent/referrals.php') ?>" class="small">View all</a>
    </div>
    <?php if (!$recent): ?>
      <div class="empty"><i class="bi bi-people"></i>No referrals yet. Share your QR code or <a href="<?= url('agent/refer.php') ?>">add a client</a>.</div>
    <?php else: ?>
      <div class="card"><div class="card-body py-1">
        <ul class="list-mini">
          <?php foreach ($recent as $c): ?>
            <li>
              <div class="d-flex align-items-center gap-2 min-w-0">
                <span class="avatar gold"><?= e(initials($c['full_name'])) ?></span>
                <div class="min-w-0"><div class="cell-title text-truncate"><?= e($c['full_name']) ?></div><div class="cell-sub"><?= fdate($c['created_at']) ?></div></div>
              </div>
              <?= status_badge($c['status']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div></div>
    <?php endif; ?>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
