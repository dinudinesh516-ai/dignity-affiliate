<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$status = $_GET['status'] ?? 'owed';
$tabs = ['owed' => 'Owed', 'unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'all' => 'All'];
$where = '1=1';
if ($status === 'owed') $where = 'cm.status IN ("unpaid","partial")';
elseif (in_array($status, ['unpaid', 'partial', 'paid'], true)) $where = 'cm.status = "' . $status . '"';

$list = rows("SELECT cm.*, u.full_name agent_name, u.phone agent_phone, c.full_name client_name, i.invoice_no
              FROM commissions cm JOIN users u ON u.id = cm.agent_id JOIN clients c ON c.id = cm.client_id JOIN invoices i ON i.id = cm.invoice_id
              WHERE $where ORDER BY cm.earned_at DESC LIMIT 500");
$t = agent_totals(null);

$pageTitle = 'Bonuses';
$area = 'admin';
$active = 'bonuses';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Referral bonuses</h1><div class="text-muted small">All agent bonuses across the program</div></div></div>
<div class="row g-2 mb-3">
  <div class="col-4"><div class="stat"><div class="label">Earned</div><div class="value"><?= money($t['earned']) ?></div></div></div>
  <div class="col-4"><div class="stat"><div class="label">Paid</div><div class="value text-success"><?= money($t['paid']) ?></div></div></div>
  <div class="col-4"><div class="stat gold"><div class="label">Owed</div><div class="value"><?= money($t['balance']) ?></div></div></div>
</div>
<div class="pill-tabs">
  <?php foreach ($tabs as $k => $l): ?><a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
</div>
<?php if (!$list): ?>
  <div class="empty"><i class="bi bi-gift"></i>Nothing here.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Agent</th><th>Client / invoice</th><th>Bonus</th><th>Paid</th><th>Remaining</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): $rem = (float)$c['amount'] - (float)$c['amount_paid']; ?>
      <tr>
        <td class="card-head"><a href="<?= url('admin/agent.php?id=' . $c['agent_id']) ?>" class="cell-title"><?= e($c['agent_name']) ?></a><div class="cell-sub">Earned <?= fdate($c['earned_at']) ?></div></td>
        <td data-label="Client"><?= e($c['client_name']) ?> <a href="<?= url('admin/invoice.php?id=' . $c['invoice_id']) ?>" class="small"><?= e($c['invoice_no']) ?></a></td>
        <td data-label="Bonus" class="fw-semibold"><?= money($c['amount']) ?></td>
        <td data-label="Paid"><?= money($c['amount_paid']) ?></td>
        <td data-label="Remaining" class="<?= $rem > 0 ? 'text-gold fw-semibold' : '' ?>"><?= money($rem) ?></td>
        <td data-label="Status"><?= status_badge($c['status']) ?></td>
        <td class="card-actions text-end"><?php if ($rem > 0): ?><a class="btn btn-sm btn-gold" href="<?= url('admin/agent.php?id=' . $c['agent_id'] . '#bonuses') ?>">Pay</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
