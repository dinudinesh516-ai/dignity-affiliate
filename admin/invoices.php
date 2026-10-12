<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$status = $_GET['status'] ?? '';
$search = trim((string)($_GET['q'] ?? ''));
$tabs = ['open' => 'Open', 'pending' => 'Pending', 'partial' => 'Partially paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM invoices GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
$counts['open'] = ($counts['pending'] ?? 0) + ($counts['partial'] ?? 0);

$where = ['1=1'];
$params = [];
if ($status === 'open') $where[] = 'i.status IN ("pending","partial")';
elseif (isset($tabs[$status])) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(i.invoice_no LIKE ? OR c.full_name LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$w = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM invoices i JOIN clients c ON c.id = i.client_id LEFT JOIN users u ON u.id = i.agent_id WHERE $w", $params);
$sum = row("SELECT COALESCE(SUM(i.total),0) t, COALESCE(SUM(i.amount_paid),0) p FROM invoices i JOIN clients c ON c.id = i.client_id LEFT JOIN users u ON u.id = i.agent_id WHERE $w AND i.status <> 'cancelled'", $params);
$p = paginate($total, 25);
$list = rows("SELECT i.*, c.full_name client_name, u.full_name agent_name FROM invoices i JOIN clients c ON c.id = i.client_id LEFT JOIN users u ON u.id = i.agent_id
              WHERE $w ORDER BY i.invoice_date DESC, i.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'Invoices';
$area = 'admin';
$active = 'invoices';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Invoices</h1><div class="text-muted small"><?= money($sum['p']) ?> collected of <?= money($sum['t']) ?></div></div>
  <div class="actions"><a href="<?= url('admin/invoice-edit.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New invoice</a></div>
</div>
<div class="pill-tabs">
  <a href="?" class="<?= $status === '' ? 'active' : '' ?>">All<span class="count"><?= array_sum(array_diff_key($counts, ['open' => 1])) ?></span></a>
  <?php foreach ($tabs as $k => $l): ?><a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?><span class="count"><?= $counts[$k] ?? 0 ?></span></a><?php endforeach; ?>
</div>
<form class="filter-bar" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search invoice #, client, agent…">
  <button class="btn btn-outline-dark"><i class="bi bi-search"></i></button>
</form>

<?php if (!$list): ?>
  <div class="empty"><i class="bi bi-receipt"></i>No invoices found. Create invoices from a client's page.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Invoice</th><th>Client</th><th>Agent</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($list as $i): $pc = pct((float)$i['amount_paid'], (float)$i['total']); ?>
      <tr class="clickable" data-href="<?= url('admin/invoice.php?id=' . $i['id']) ?>">
        <td class="card-head"><a href="<?= url('admin/invoice.php?id=' . $i['id']) ?>" class="cell-title"><?= e($i['invoice_no']) ?></a><div class="cell-sub"><?= fdate($i['invoice_date']) ?></div></td>
        <td data-label="Client"><?= e($i['client_name']) ?></td>
        <td data-label="Agent"><?= $i['agent_name'] ? e($i['agent_name']) : '<span class="text-muted">Direct</span>' ?></td>
        <td data-label="Total" class="fw-semibold"><?= money($i['total']) ?></td>
        <td data-label="Paid"><?= money($i['amount_paid']) ?> <span class="text-muted small"><?= $pc ?>%</span></td>
        <td data-label="Status"><?= status_badge($i['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_links($p) ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
