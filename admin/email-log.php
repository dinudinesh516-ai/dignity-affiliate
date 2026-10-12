<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$total = (int)val('SELECT COUNT(*) FROM email_log');
$p = paginate($total, 30);
$list = rows("SELECT * FROM email_log ORDER BY id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}");

$pageTitle = 'Email log';
$area = 'admin';
$active = 'emails';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Email log</h1><div class="text-muted small">Every email sent through Brevo</div></div></div>
<?php if (!$list): ?>
  <div class="empty"><i class="bi bi-envelope"></i>No emails sent yet.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Email</th><th>To</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($list as $m): ?>
      <tr>
        <td class="card-head"><div class="cell-title"><?= e($m['subject']) ?></div><div class="cell-sub"><?= fdatetime($m['created_at']) ?></div></td>
        <td data-label="To"><?= e($m['to_email']) ?></td>
        <td data-label="Result"><?= $m['ok'] ? '<span class="badge badge-soft-success">Sent</span>' : '<span class="badge badge-soft-danger" title="' . e($m['response']) . '">Failed</span><div class="cell-sub text-break" style="max-width:320px">' . e(mb_strimwidth((string)$m['response'], 0, 160, '…')) . '</div>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_links($p) ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
