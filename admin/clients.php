<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$status = $_GET['status'] ?? '';
$search = trim((string)($_GET['q'] ?? ''));
$agentId = (int)($_GET['agent'] ?? 0);
$statuses = ['new' => 'New', 'contacted' => 'Contacted', 'negotiating' => 'Negotiating', 'won' => 'Won', 'lost' => 'Lost'];

$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM clients GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];

$where = ['1=1'];
$params = [];
if (isset($statuses[$status])) { $where[] = 'c.status = ?'; $params[] = $status; }
if ($agentId) { $where[] = 'c.agent_id = ?'; $params[] = $agentId; }
if ($search !== '') {
    $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.phone_key LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, '%' . phone_digits($search) . '%', $like);
}
$w = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM clients c LEFT JOIN users u ON u.id = c.agent_id WHERE $w", $params);
$p = paginate($total, 25);
$list = rows("SELECT c.*, u.full_name agent_name, u.phone agent_phone,
      (SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id = c.id AND status <> 'cancelled') inv_total,
      (SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE client_id = c.id AND status <> 'cancelled') inv_paid
    FROM clients c LEFT JOIN users u ON u.id = c.agent_id WHERE $w ORDER BY c.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);
$agents = rows('SELECT id, full_name FROM users WHERE role = "agent" ORDER BY full_name');

$pageTitle = 'Clients';
$area = 'admin';
$active = 'clients';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Client referrals</h1><div class="text-muted small"><?= $total ?> shown</div></div>
  <div class="actions"><a href="<?= url('admin/client-new.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add client</a></div>
</div>

<div class="pill-tabs">
  <a href="?<?= e(http_build_query(['q' => $search, 'agent' => $agentId ?: null])) ?>" class="<?= $status === '' ? 'active' : '' ?>">All<span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach ($statuses as $k => $l): ?>
    <a href="?<?= e(http_build_query(['status' => $k, 'q' => $search, 'agent' => $agentId ?: null])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?><span class="count"><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name, phone, email, agent…">
  <select class="form-select" name="agent" onchange="this.form.submit()">
    <option value="">All agents</option>
    <?php foreach ($agents as $a): ?><option value="<?= $a['id'] ?>" <?= $agentId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-outline-dark"><i class="bi bi-search"></i></button>
</form>

<?php if (!$list): ?>
  <div class="empty"><i class="bi bi-inbox"></i>No clients found.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Client</th><th>Referred by</th><th>Date</th><th>Status</th><th>Sales</th><th class="text-end">Contact</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): ?>
      <tr class="clickable" data-href="<?= url('admin/client.php?id=' . $c['id']) ?>">
        <td class="card-head">
          <div class="cell-title"><?= e($c['full_name']) ?></div>
          <div class="cell-sub"><?= e($c['phone']) ?><?= $c['email'] ? ' · ' . e($c['email']) : '' ?></div>
          <?php if ($c['interests'] || $c['looking_for']): ?><div class="cell-sub text-truncate-2 mt-1"><i class="bi bi-gem text-gold"></i> <?= e(trim(($c['interests'] ? $c['interests'] . ' — ' : '') . $c['looking_for'], ' —')) ?></div><?php endif; ?>
        </td>
        <td data-label="Referred by"><?= $c['agent_name'] ? e($c['agent_name']) : '<span class="text-muted">Direct</span>' ?></td>
        <td data-label="Date"><?= fdate($c['created_at']) ?></td>
        <td data-label="Status"><?= status_badge($c['status']) ?></td>
        <td data-label="Sales"><?= (float)$c['inv_total'] > 0 ? money($c['inv_paid']) . ' <span class="text-muted small">/ ' . money($c['inv_total']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
        <td class="card-actions text-end text-nowrap">
          <a class="btn btn-sm btn-whatsapp" href="<?= e(url('admin/contact.php?id=' . $c['id'] . '&via=whatsapp')) ?>" target="_blank" rel="noopener" title="WhatsApp" data-contact><i class="bi bi-whatsapp"></i></a>
          <a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/contact.php?id=' . $c['id'] . '&via=call')) ?>" title="Call" data-contact><i class="bi bi-telephone"></i></a>
          <?php if ($c['email']): ?><a class="btn btn-sm btn-outline-dark" href="<?= url('admin/client.php?id=' . $c['id'] . '#email') ?>" title="Email"><i class="bi bi-envelope"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/client.php?id=' . $c['id'] . '&edit=1#editBox') ?>" title="Edit client"><i class="bi bi-pencil"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_links($p) ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
