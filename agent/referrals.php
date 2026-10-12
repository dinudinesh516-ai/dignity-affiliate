<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_agent();

$status = $_GET['status'] ?? '';
$statuses = ['new' => 'New', 'contacted' => 'Contacted', 'negotiating' => 'Negotiating', 'won' => 'Purchased', 'lost' => 'Closed'];
$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM clients WHERE agent_id = ? GROUP BY status', [$me['id']]) as $r) $counts[$r['status']] = (int)$r['n'];

$where = 'c.agent_id = ?';
$params = [$me['id']];
if (isset($statuses[$status])) { $where .= ' AND c.status = ?'; $params[] = $status; }
$total = (int)val("SELECT COUNT(*) FROM clients c WHERE $where", $params);
$p = paginate($total, 20);
$list = rows("SELECT c.*,
        (SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id = c.id AND status <> 'cancelled') inv_total,
        (SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE client_id = c.id AND status <> 'cancelled') inv_paid,
        (SELECT COALESCE(SUM(amount),0) FROM commissions WHERE client_id = c.id) bonus
      FROM clients c WHERE $where ORDER BY c.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'My referrals';
$area = 'agent';
$active = 'referrals';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">My referrals</h1><div class="text-muted small"><?= array_sum($counts) ?> total</div></div>
  <div class="actions"><a href="<?= url('agent/refer.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Refer</a></div>
</div>

<div class="pill-tabs">
  <a href="?" class="<?= $status === '' ? 'active' : '' ?>">All<span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach ($statuses as $k => $l): ?>
    <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?><span class="count"><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</div>

<?php if (!$list): ?>
  <div class="empty"><i class="bi bi-people"></i>No referrals here yet.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Client</th><th>Referred</th><th>Status</th><th>Sale progress</th><th class="text-end">Bonus</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): $pc = pct((float)$c['inv_paid'], (float)$c['inv_total']); ?>
      <tr>
        <td class="card-head">
          <div class="cell-title"><?= e($c['full_name']) ?></div>
          <div class="cell-sub"><?= e($c['interests'] ?: mb_strimwidth((string)$c['looking_for'], 0, 60, '…')) ?></div>
        </td>
        <td data-label="Referred"><?= fdate($c['created_at']) ?></td>
        <td data-label="Status"><?= status_badge($c['status']) ?></td>
        <td data-label="Sale progress" style="min-width:160px">
          <?php if ((float)$c['inv_total'] > 0): ?>
            <div class="w-100" style="max-width:220px;margin-left:auto">
              <div class="d-flex justify-content-between small"><span><?= money($c['inv_paid']) ?></span><span class="text-muted">of <?= money($c['inv_total']) ?></span></div>
              <div class="progress mt-1"><div class="progress-bar <?= $pc >= 100 ? 'bg-success' : '' ?>" style="width:<?= $pc ?>%"></div></div>
            </div>
          <?php else: ?><span class="text-muted small">No invoice yet</span><?php endif; ?>
        </td>
        <td data-label="Bonus" class="text-end fw-semibold"><?= (float)$c['bonus'] > 0 ? money($c['bonus']) : '<span class="text-muted fw-normal">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_links($p) ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
