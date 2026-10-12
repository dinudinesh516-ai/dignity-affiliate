<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$newLeads = (int)val('SELECT COUNT(*) FROM clients WHERE status = "new"');
$monthLeads = (int)val('SELECT COUNT(*) FROM clients WHERE created_at >= ?', [date('Y-m-01')]);
$openDeals = (int)val('SELECT COUNT(*) FROM clients WHERE status IN ("contacted","negotiating")');
$outstanding = (float)val('SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status IN ("pending","partial")');
$collectedMonth = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date >= ?', [date('Y-m-01')]);
$salesTotal = (float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE status = "paid"');
$agentsCount = (int)val('SELECT COUNT(*) FROM users WHERE role = "agent"');
$t = agent_totals(null);

$recent = rows('SELECT c.*, u.full_name agent_name FROM clients c LEFT JOIN users u ON u.id = c.agent_id ORDER BY c.created_at DESC LIMIT 6');
$recentPayments = rows('SELECT p.*, i.invoice_no, c.full_name client_name FROM payments p JOIN invoices i ON i.id = p.invoice_id JOIN clients c ON c.id = i.client_id ORDER BY p.created_at DESC LIMIT 5');
$topAgents = rows('SELECT u.id, u.full_name, u.referral_code,
    (SELECT COUNT(*) FROM clients WHERE agent_id = u.id) refs,
    (SELECT COALESCE(SUM(amount),0) FROM commissions WHERE agent_id = u.id) earned
  FROM users u WHERE u.role = "agent" ORDER BY earned DESC, refs DESC LIMIT 5');

$pageTitle = 'Dashboard';
$area = 'admin';
$active = 'dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div><div class="text-muted small"><?= date('l, M j') ?></div><h1 class="page-title">Dashboard</h1></div>
  <div class="actions">
    <a href="<?= url('admin/agents.php?new=1') ?>" class="btn btn-outline-dark"><i class="bi bi-person-plus me-1"></i>Agent</a>
    <a href="<?= url('admin/client-new.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Client</a>
  </div>
</div>

<div class="row g-2">
  <div class="col-6 col-lg-3"><a href="<?= url('admin/clients.php?status=new') ?>" class="d-block"><div class="stat dark"><div class="label">New referrals</div><div class="value"><?= $newLeads ?></div><div class="sub"><?= $monthLeads ?> this month</div></div></a></div>
  <div class="col-6 col-lg-3"><a href="<?= url('admin/clients.php?status=negotiating') ?>" class="d-block"><div class="stat"><div class="label">Open deals</div><div class="value"><?= $openDeals ?></div><div class="sub">Contacted / negotiating</div></div></a></div>
  <div class="col-6 col-lg-3"><a href="<?= url('admin/invoices.php?status=open') ?>" class="d-block"><div class="stat"><div class="label">Awaiting payment</div><div class="value"><?= money($outstanding) ?></div><div class="sub">On open invoices</div></div></a></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Collected</div><div class="value"><?= money($collectedMonth) ?></div><div class="sub">This month</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Completed sales</div><div class="value"><?= money($salesTotal) ?></div><div class="sub">All time</div></div></div>
  <div class="col-6 col-lg-3"><a href="<?= url('admin/agents.php') ?>" class="d-block"><div class="stat"><div class="label">Agents</div><div class="value"><?= $agentsCount ?></div><div class="sub"><?= $t['referrals'] ?> referrals</div></div></a></div>
  <div class="col-6 col-lg-3"><a href="<?= url('admin/bonuses.php') ?>" class="d-block"><div class="stat gold"><div class="label">Bonus owed</div><div class="value"><?= money($t['balance']) ?></div><div class="sub">Unpaid to agents</div></div></a></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Bonus paid</div><div class="value"><?= money($t['paid']) ?></div><div class="sub">of <?= money($t['earned']) ?> earned</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="d-flex justify-content-between align-items-center"><div class="section-title">Latest referrals</div><a href="<?= url('admin/clients.php') ?>" class="small">View all</a></div>
    <?php if (!$recent): ?>
      <div class="empty"><i class="bi bi-inbox"></i>No referrals yet.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table table-cards">
        <thead><tr><th>Client</th><th>Agent</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $c): ?>
          <tr class="clickable" data-href="<?= url('admin/client.php?id=' . $c['id']) ?>">
            <td class="card-head"><div class="cell-title"><?= e($c['full_name']) ?></div><div class="cell-sub text-truncate-2"><?= e($c['interests'] ?: $c['looking_for']) ?> · <?= fdate($c['created_at']) ?></div></td>
            <td data-label="Agent"><?= e($c['agent_name'] ?: 'Direct') ?></td>
            <td data-label="Status"><?= status_badge($c['status']) ?></td>
            <td class="card-actions text-end">
              <a class="btn btn-sm btn-whatsapp" href="<?= e(url('admin/contact.php?id=' . $c['id'] . '&via=whatsapp')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" data-contact><i class="bi bi-whatsapp"></i></a>
              <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/client.php?id=' . $c['id']) ?>">Open</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="section-title">Recent payments</div>
    <div class="card"><div class="card-body py-1">
      <?php if (!$recentPayments): ?><div class="text-muted small py-3 text-center">No payments recorded yet.</div><?php else: ?>
      <ul class="list-mini">
        <?php foreach ($recentPayments as $p): ?>
          <li><div class="min-w-0"><div class="cell-title text-truncate"><?= e($p['client_name']) ?></div><div class="cell-sub"><?= e($p['invoice_no']) ?> · <?= e($p['method']) ?> · <?= fdate($p['payment_date']) ?></div></div><strong class="text-success text-nowrap"><?= money($p['amount']) ?></strong></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div></div>

    <div class="section-title">Top agents</div>
    <div class="card"><div class="card-body py-1">
      <?php if (!$topAgents): ?><div class="text-muted small py-3 text-center">No agents yet.</div><?php else: ?>
      <ul class="list-mini">
        <?php foreach ($topAgents as $a): ?>
          <li><a href="<?= url('admin/agent.php?id=' . $a['id']) ?>" class="d-flex align-items-center gap-2 min-w-0 text-reset">
              <span class="avatar gold"><?= e(initials($a['full_name'])) ?></span>
              <div class="min-w-0"><div class="cell-title text-truncate"><?= e($a['full_name']) ?></div><div class="cell-sub"><?= (int)$a['refs'] ?> referrals</div></div></a>
            <strong class="text-nowrap"><?= money($a['earned']) ?></strong></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
