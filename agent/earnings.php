<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_agent();
$t = agent_totals((int)$me['id']);
$rate = agent_rate($me);
$basisNet = setting('commission_basis', 'total') === 'net';

$commissions = rows('SELECT cm.*, c.full_name client_name, i.invoice_no FROM commissions cm
                     JOIN clients c ON c.id = cm.client_id JOIN invoices i ON i.id = cm.invoice_id
                     WHERE cm.agent_id = ? ORDER BY cm.earned_at DESC', [$me['id']]);
$open = rows('SELECT i.*, c.full_name client_name FROM invoices i JOIN clients c ON c.id = i.client_id
              WHERE i.agent_id = ? AND i.status IN ("pending","partial") ORDER BY i.invoice_date DESC', [$me['id']]);
$payouts = rows('SELECT p.*, c.full_name client_name, i.invoice_no FROM payouts p
                 JOIN commissions cm ON cm.id = p.commission_id JOIN clients c ON c.id = cm.client_id JOIN invoices i ON i.id = cm.invoice_id
                 WHERE p.agent_id = ? ORDER BY p.payout_date DESC, p.id DESC', [$me['id']]);

$pageTitle = 'Earnings';
$area = 'agent';
$active = 'earnings';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Earnings</h1><div class="text-muted small">Your referral bonuses and payments</div></div></div>

<div class="row g-2">
  <div class="col-6 col-lg-3"><div class="stat dark"><div class="label">Balance due</div><div class="value"><?= money($t['balance']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Total earned</div><div class="value"><?= money($t['earned']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">Paid to you</div><div class="value text-success"><?= money($t['paid']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat gold"><div class="label">In progress</div><div class="value"><?= money($t['projected']) ?></div><div class="sub">Expected</div></div></div>
</div>

<div class="section-title">Deals in progress</div>
<?php if (!$open): ?>
  <div class="empty py-4"><i class="bi bi-hourglass-split"></i>No open deals right now.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Client</th><th>Invoice total</th><th>Client paid</th><th class="text-end">Expected bonus</th></tr></thead>
    <tbody>
    <?php foreach ($open as $i):
        $pc = pct((float)$i['amount_paid'], (float)$i['total']);
        $base = $basisNet ? (float)$i['subtotal'] - (float)$i['discount'] : (float)$i['total']; ?>
      <tr>
        <td class="card-head"><div class="cell-title"><?= e($i['client_name']) ?></div><div class="cell-sub">Invoice <?= e($i['invoice_no']) ?> · <?= fdate($i['invoice_date']) ?></div></td>
        <td data-label="Invoice total"><?= money($i['total']) ?></td>
        <td data-label="Client paid" style="min-width:150px">
          <div class="w-100" style="max-width:200px;margin-left:auto">
            <div class="small text-end text-lg-start"><?= money($i['amount_paid']) ?> · <?= $pc ?>%</div>
            <div class="progress mt-1"><div class="progress-bar" style="width:<?= $pc ?>%"></div></div>
          </div>
        </td>
        <td data-label="Expected bonus" class="text-end fw-semibold"><?= money($base * $rate / 100) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Your bonus is credited once the client has paid the invoice in full.</div>
<?php endif; ?>

<div class="section-title">Bonuses earned</div>
<?php if (!$commissions): ?>
  <div class="empty py-4"><i class="bi bi-gift"></i>No bonuses yet — they appear here when a referred client completes payment.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Client</th><th>Sale</th><th>Bonus</th><th>Paid</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($commissions as $c): ?>
      <tr>
        <td class="card-head"><div class="cell-title"><?= e($c['client_name']) ?></div><div class="cell-sub"><?= e($c['invoice_no']) ?> · earned <?= fdate($c['earned_at']) ?></div></td>
        <td data-label="Sale"><?= money($c['sale_amount']) ?></td>
        <td data-label="Bonus" class="fw-semibold"><?= money($c['amount']) ?> <span class="text-muted small fw-normal">(<?= e(rtrim(rtrim($c['rate'], '0'), '.')) ?>%)</span></td>
        <td data-label="Paid"><?= money($c['amount_paid']) ?></td>
        <td data-label="Status"><?= status_badge($c['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="section-title">Payments received</div>
<?php if (!$payouts): ?>
  <div class="empty py-4"><i class="bi bi-cash-stack"></i>No payments yet.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table table-cards">
    <thead><tr><th>Date</th><th>For</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($payouts as $po): ?>
      <tr>
        <td data-label="Date"><?= fdate($po['payout_date']) ?></td>
        <td data-label="For"><?= e($po['client_name']) ?> <span class="text-muted small">(<?= e($po['invoice_no']) ?>)</span></td>
        <td data-label="Method"><?= e($po['method']) ?><?= $po['reference'] ? ' <span class="text-muted small">#' . e($po['reference']) . '</span>' : '' ?></td>
        <td data-label="Amount" class="text-end fw-semibold text-success"><?= money($po['amount']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
