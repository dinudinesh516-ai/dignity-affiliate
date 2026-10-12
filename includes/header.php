<?php
/**
 * Page header. Set before including:
 *   $pageTitle (string), $area ('admin'|'agent'|'public'), $active (nav key)
 */
$pageTitle = $pageTitle ?? '';
$area = $area ?? 'public';
$active = $active ?? '';
$me = current_user();
$logo = setting('logo_url', '');

$navs = [
    'admin' => [
        ['dashboard', 'admin/', 'speedometer2', 'Dashboard', true],
        ['clients', 'admin/clients.php', 'people', 'Clients', true],
        ['invoices', 'admin/invoices.php', 'receipt', 'Invoices', true],
        ['agents', 'admin/agents.php', 'person-badge', 'Agents', true],
        ['bonuses', 'admin/bonuses.php', 'gift', 'Bonuses', false],
        ['settings', 'admin/settings.php', 'gear', 'Settings', false],
        ['emails', 'admin/email-log.php', 'envelope-check', 'Email log', false],
        ['profile', 'admin/profile.php', 'person-circle', 'My account', false],
    ],
    'agent' => [
        ['dashboard', 'agent/', 'house', 'Home', true],
        ['referrals', 'agent/referrals.php', 'people', 'Referrals', true],
        ['refer', 'agent/refer.php', 'plus-lg', 'Refer', true],
        ['earnings', 'agent/earnings.php', 'wallet2', 'Earnings', true],
        ['profile', 'agent/profile.php', 'person-circle', 'Profile', true],
    ],
];
$nav = $navs[$area] ?? [];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1c1c1c">
<meta name="robots" content="noindex">
<link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
<link rel="icon" type="image/svg+xml" href="<?= asset('icons/favicon.svg') ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= asset('icons/favicon-32.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('icons/apple-touch-icon.png') ?>">
<link rel="manifest" href="<?= url('site.webmanifest') ?>">
<meta name="apple-mobile-web-app-title" content="Dignity">
<title><?= $pageTitle !== '' ? e($pageTitle) . ' - ' : '' ?>Dignity Jewels</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="area-<?= e($area) ?>">

<?php if ($area === 'public'): ?>
<header class="public-top">
  <a href="<?= url() ?>" class="brand">
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Dignity Jewels" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'brand-text',textContent:'DIGNITY JEWELS'}))"><?php else: ?><span class="brand-text">DIGNITY JEWELS</span><?php endif; ?>
  </a>
</header>
<main class="public-main<?= !empty($mainWide) ? ' wide' : '' ?>">
<?php else: ?>
<header class="app-top">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= url($area . '/') ?>" class="brand">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Dignity Jewels" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'brand-text',textContent:'DIGNITY'}))"><?php else: ?><span class="brand-text">DIGNITY</span><?php endif; ?>
    </a>
    <span class="area-pill"><?= $area === 'admin' ? 'Admin' : 'Affiliate' ?></span>
  </div>
  <div class="dropdown">
    <button class="btn avatar-btn" data-bs-toggle="dropdown" aria-label="Account menu">
      <span class="avatar"><?= e(initials($me['full_name'] ?? '')) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
      <li class="dropdown-header"><?= e($me['full_name'] ?? '') ?><br><small class="text-muted"><?= e($me['email'] ?? '') ?></small></li>
      <li><a class="dropdown-item" href="<?= url($area . '/profile.php') ?>"><i class="bi bi-person-circle me-2"></i>My account</a></li>
      <?php if ($area === 'admin'): ?>
      <li><a class="dropdown-item" href="<?= url('admin/settings.php') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
      <?php endif; ?>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
    </ul>
  </div>
</header>

<div class="app-shell">
  <nav class="side-nav d-none d-lg-flex">
    <?php foreach ($nav as [$key, $href, $icon, $label]): ?>
      <a href="<?= url($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
    <a href="<?= url('logout.php') ?>" class="mt-auto"><i class="bi bi-box-arrow-right"></i><span>Sign out</span></a>
  </nav>
  <main class="app-main">
<?php endif; ?>

<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($f['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endforeach; ?>
