<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$id = (int)($_GET['id'] ?? 0);
$inv = row('SELECT * FROM invoices WHERE id = ?', [$id]);
if (!$inv) { flash('warning', 'Invoice not found.'); redirect('admin/invoices.php'); }
$client = row('SELECT * FROM clients WHERE id = ?', [$inv['client_id']]);

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $pdo = db();

    if ($action === 'payment' && $inv['status'] !== 'cancelled') {
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $date = (string)($_POST['payment_date'] ?? '');
        $method = in_array($_POST['method'] ?? '', PAYMENT_METHODS, true) ? $_POST['method'] : 'Other';
        $balance = round((float)$inv['total'] - (float)$inv['amount_paid'], 2);
        if ($amount <= 0) {
            flash('danger', 'Enter a payment amount greater than zero.');
        } elseif ($amount > $balance + 0.004) {
            flash('danger', 'Payment is more than the balance due (' . money($balance) . ').');
        } elseif (!DateTime::createFromFormat('Y-m-d', $date)) {
            flash('danger', 'Enter a valid payment date.');
        } else {
            $pdo->beginTransaction();
            try {
                q('INSERT INTO payments (invoice_id, amount, payment_date, method, reference, notes, created_by) VALUES (?,?,?,?,?,?,?)',
                    [$id, $amount, $date, $method, trim((string)($_POST['reference'] ?? '')) ?: null, trim((string)($_POST['notes'] ?? '')) ?: null, $me['id']]);
                log_activity((int)$inv['client_id'], (int)$me['id'], 'payment', 'Payment of ' . money($amount) . ' (' . $method . ') received for ' . $inv['invoice_no'] . '.');
                $new = recalc_invoice($id);
                $pdo->commit();
                flash('success', $new['status'] === 'paid'
                    ? 'Payment recorded. Invoice is fully paid' . ($inv['agent_id'] ? ' and the agent\'s referral bonus has been credited.' : '.')
                    : 'Payment recorded. Balance due: ' . money($new['total'] - $new['amount_paid']) . '.');
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                flash('danger', $ex->getMessage());
            }
        }
        redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'delete_payment') {
        $pay = row('SELECT * FROM payments WHERE id = ? AND invoice_id = ?', [(int)($_POST['payment_id'] ?? 0), $id]);
        if ($pay) {
            $pdo->beginTransaction();
            try {
                q('DELETE FROM payments WHERE id = ?', [$pay['id']]);
                recalc_invoice($id);
                log_activity((int)$inv['client_id'], (int)$me['id'], 'payment', 'Payment of ' . money($pay['amount']) . ' on ' . fdate($pay['payment_date']) . ' was removed from ' . $inv['invoice_no'] . '.');
                $pdo->commit();
                flash('success', 'Payment removed.');
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                flash('danger', $ex->getMessage());
            }
        }
        redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'cancel') {
        if ((float)$inv['amount_paid'] > 0) {
            flash('danger', 'Remove all payments before cancelling this invoice.');
        } else {
            q('UPDATE invoices SET status = "cancelled" WHERE id = ?', [$id]);
            log_activity((int)$inv['client_id'], (int)$me['id'], 'invoice', 'Invoice ' . $inv['invoice_no'] . ' cancelled.');
            flash('success', 'Invoice cancelled.');
        }
        redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'reopen' && $inv['status'] === 'cancelled') {
        q('UPDATE invoices SET status = "pending" WHERE id = ?', [$id]);
        recalc_invoice($id);
        flash('success', 'Invoice re-opened.');
        redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'email') {
        if (!$client['email']) {
            flash('danger', 'This client has no email address. Add one on the client page.');
        } else {
            $items = rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
            $ok = brevo_send($client['email'], $client['full_name'], 'Your Dignity Jewels invoice ' . $inv['invoice_no'],
                email_layout('Invoice ' . $inv['invoice_no'], invoice_email_html($inv, $items, $client)), [], $me['email']);
            if ($ok) {
                log_activity((int)$inv['client_id'], (int)$me['id'], 'email', 'Invoice ' . $inv['invoice_no'] . ' emailed to client.');
                flash('success', 'Invoice emailed to ' . $client['email'] . '.');
            } else {
                flash('danger', 'Email could not be sent. Check the Email log.');
            }
        }
        redirect('admin/invoice.php?id=' . $id);
    }
}

$items = rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
$payments = rows('SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date, id', [$id]);
$agent = $inv['agent_id'] ? row('SELECT * FROM users WHERE id = ?', [$inv['agent_id']]) : null;
$commission = row('SELECT * FROM commissions WHERE invoice_id = ?', [$id]);
$balance = round((float)$inv['total'] - (float)$inv['amount_paid'], 2);
$pc = pct((float)$inv['amount_paid'], (float)$inv['total']);
$logo = setting('logo_url', '');

$pageTitle = 'Invoice ' . $inv['invoice_no'];
$area = 'admin';
$active = 'invoices';
include dirname(__DIR__) . '/includes/header.php';
?>
<a href="<?= url('admin/client.php?id=' . $client['id']) ?>" class="back-link no-print"><i class="bi bi-arrow-left"></i> <?= e($client['full_name']) ?></a>
<div class="page-head no-print">
  <div><h1 class="page-title"><?= e($inv['invoice_no']) ?></h1><div class="mt-1"><?= status_badge($inv['status']) ?></div></div>
  <div class="actions">
    <?php if ($inv['status'] !== 'cancelled'): ?><a href="<?= url('admin/invoice-edit.php?id=' . $id) ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
    <button class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="email"><button class="btn btn-outline-dark btn-sm" <?= $client['email'] ? '' : 'disabled' ?>><i class="bi bi-envelope"></i> Email</button></form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <?php if ($inv['status'] !== 'cancelled'): ?>
    <div class="card mb-3 no-print">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-end mb-2">
          <div><div class="text-muted small">Balance due</div><div class="fs-3 fw-bold <?= $balance <= 0 ? 'text-success' : '' ?>"><?= money($balance) ?></div></div>
          <div class="text-end small text-muted">Paid <?= money($inv['amount_paid']) ?><br>of <?= money($inv['total']) ?></div>
        </div>
        <div class="progress"><div class="progress-bar <?= $pc >= 100 ? 'bg-success' : '' ?>" style="width:<?= $pc ?>%"></div></div>
        <?php if ($balance > 0): ?>
          <button class="btn btn-gold w-100 mt-3" data-bs-toggle="collapse" data-bs-target="#payBox"><i class="bi bi-plus-lg me-1"></i>Record payment</button>
          <div class="collapse" id="payBox">
            <form method="post" class="mt-3">
              <?= csrf_field() ?><input type="hidden" name="action" value="payment">
              <div class="row g-2">
                <div class="col-6"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" max="<?= e($balance) ?>" inputmode="decimal" name="amount" value="<?= e($balance) ?>" required></div>
                <div class="col-6"><label class="form-label">Date</label><input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-12"><label class="form-label">Payment type</label>
                  <select class="form-select" name="method"><?php foreach (PAYMENT_METHODS as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Reference <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="reference" placeholder="Transaction / check no."></div>
                <div class="col-12"><label class="form-label">Note <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="notes" maxlength="255"></div>
              </div>
              <button class="btn btn-primary w-100 mt-3">Save payment</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="invoice-paper">
      <div class="inv-head">
        <div>
          <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Dignity Jewels" onerror="this.outerHTML='<span class=brand-text>DIGNITY JEWELS</span>'"><?php else: ?><span class="brand-text">DIGNITY JEWELS</span><?php endif; ?>
          <div class="small text-muted mt-2"><?= e(setting('company_name')) ?><br><?= e(setting('company_address')) ?><br><?= e(setting('company_phone')) ?></div>
        </div>
        <div class="text-end">
          <div class="serif fs-3">Invoice</div>
          <div class="small"><strong><?= e($inv['invoice_no']) ?></strong><br><?= fdate($inv['invoice_date']) ?></div>
        </div>
      </div>
      <div class="mb-3">
        <div class="text-muted small text-uppercase" style="letter-spacing:1px">Bill to</div>
        <div class="fw-semibold"><?= e($client['full_name']) ?></div>
        <div class="small"><?= e($client['phone']) ?><?= $client['email'] ? '<br>' . e($client['email']) : '' ?></div>
      </div>
      <div class="table-wrap">
        <table class="table table-cards">
          <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td class="card-head"><?= e($it['description']) ?></td>
              <td data-label="Qty" class="text-end"><?= e(rtrim(rtrim($it['qty'], '0'), '.')) ?></td>
              <td data-label="Price" class="text-end"><?= money($it['unit_price']) ?></td>
              <td data-label="Amount" class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="ms-auto mt-3" style="max-width:320px">
        <div class="d-flex justify-content-between py-1"><span class="text-muted">Subtotal</span><span><?= money($inv['subtotal']) ?></span></div>
        <?php if ((float)$inv['discount'] > 0): ?><div class="d-flex justify-content-between py-1"><span class="text-muted">Discount</span><span>−<?= money($inv['discount']) ?></span></div><?php endif; ?>
        <?php if ((float)$inv['tax_amount'] > 0): ?><div class="d-flex justify-content-between py-1"><span class="text-muted">Tax (<?= e(rtrim(rtrim($inv['tax_rate'], '0'), '.')) ?>%)</span><span><?= money($inv['tax_amount']) ?></span></div><?php endif; ?>
        <div class="d-flex justify-content-between py-2 border-top fw-bold fs-5"><span>Total</span><span><?= money($inv['total']) ?></span></div>
        <div class="d-flex justify-content-between py-1"><span class="text-muted">Paid</span><span><?= money($inv['amount_paid']) ?></span></div>
        <div class="d-flex justify-content-between py-1 fw-semibold text-gold"><span>Balance due</span><span><?= money(max(0, $balance)) ?></span></div>
      </div>
      <?php if ($inv['notes']): ?><div class="small text-muted mt-3" style="white-space:pre-line"><?= e($inv['notes']) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5 no-print">
    <div class="section-title mt-0">Payments</div>
    <?php if (!$payments): ?>
      <div class="empty py-4"><i class="bi bi-cash"></i>No payments yet.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table table-cards">
        <thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td data-label="Date"><?= fdate($p['payment_date']) ?></td>
            <td data-label="Type"><?= e($p['method']) ?><?php if ($p['reference']): ?><div class="cell-sub">#<?= e($p['reference']) ?></div><?php endif; ?><?php if ($p['notes']): ?><div class="cell-sub"><?= e($p['notes']) ?></div><?php endif; ?></td>
            <td data-label="Amount" class="text-end fw-semibold text-success"><?= money($p['amount']) ?></td>
            <td class="card-actions text-end">
              <form method="post" data-confirm="Remove this payment of <?= e(money($p['amount'])) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                <button class="btn btn-sm btn-link text-danger p-0" aria-label="Remove payment"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="section-title">Referral bonus</div>
    <div class="card"><div class="card-body">
      <?php if (!$agent): ?>
        <span class="text-muted small">Direct client — no agent bonus.</span>
      <?php elseif ($commission): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <a href="<?= url('admin/agent.php?id=' . $agent['id']) ?>" class="fw-semibold"><?= e($agent['full_name']) ?></a><?= status_badge($commission['status']) ?>
        </div>
        <dl class="kv mb-0">
          <dt>Bonus (<?= e(rtrim(rtrim($commission['rate'], '0'), '.')) ?>%)</dt><dd class="fw-semibold"><?= money($commission['amount']) ?></dd>
          <dt>Paid to agent</dt><dd><?= money($commission['amount_paid']) ?></dd>
          <dt>Earned on</dt><dd><?= fdate($commission['earned_at']) ?></dd>
        </dl>
        <a href="<?= url('admin/agent.php?id=' . $agent['id'] . '#bonuses') ?>" class="btn btn-outline-dark btn-sm w-100 mt-3">Manage payout</a>
      <?php else: ?>
        <div class="small"><a href="<?= url('admin/agent.php?id=' . $agent['id']) ?>" class="fw-semibold"><?= e($agent['full_name']) ?></a> will earn
          <strong><?= money((setting('commission_basis', 'total') === 'net' ? $inv['subtotal'] - $inv['discount'] : $inv['total']) * agent_rate($agent) / 100) ?></strong>
          (<?= e(rtrim(rtrim(number_format(agent_rate($agent), 2), '0'), '.')) ?>%) once this invoice is fully paid.</div>
      <?php endif; ?>
    </div></div>

    <div class="mt-3 text-center">
      <?php if ($inv['status'] === 'cancelled'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><button class="btn btn-link btn-sm">Re-open invoice</button></form>
      <?php elseif ((float)$inv['amount_paid'] == 0): ?>
        <form method="post" data-confirm="Cancel this invoice?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-link btn-sm text-danger">Cancel invoice</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
