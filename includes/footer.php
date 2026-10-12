<?php
$area = $area ?? 'public';
?>
<?php if ($area === 'public'): ?>
</main>
<footer class="public-foot">
  <div><?= e(setting('company_name', 'Dignity Jewels')) ?> · <a href="<?= e(setting('company_website', 'https://dignityjewelry.com')) ?>" target="_blank" rel="noopener">dignityjewelry.com</a></div>
  <div class="small">You dream it, We make it.</div>
</footer>
<?php else: ?>
  </main>
</div>

<nav class="bottom-nav d-lg-none">
  <?php
  $primary = array_filter($nav, function ($n) { return $n[4]; });
  $more = array_filter($nav, function ($n) { return !$n[4]; });
  foreach ($primary as [$key, $href, $icon, $label]): ?>
    <a href="<?= url($href) ?>" class="<?= $active === $key ? 'active' : '' ?> <?= $key === 'refer' ? 'fab' : '' ?>">
      <i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if ($more): ?>
    <a href="#" data-bs-toggle="offcanvas" data-bs-target="#moreNav" class="<?= in_array($active, array_column($more, 0), true) ? 'active' : '' ?>">
      <i class="bi bi-grid"></i><span>More</span>
    </a>
  <?php endif; ?>
</nav>

<?php if ($more): ?>
<div class="offcanvas offcanvas-bottom more-sheet" tabindex="-1" id="moreNav">
  <div class="offcanvas-header"><h5 class="offcanvas-title">More</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body pt-0">
    <div class="list-group list-group-flush">
      <?php foreach ($more as [$key, $href, $icon, $label]): ?>
        <a class="list-group-item list-group-item-action py-3" href="<?= url($href) ?>"><i class="bi bi-<?= $icon ?> me-3"></i><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="list-group-item list-group-item-action py-3 text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-3"></i>Sign out</a>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
