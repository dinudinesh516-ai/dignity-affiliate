<?php
/** Create or edit an invoice. ?client=ID (new) or ?id=ID (edit) */
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$inv = null;
$items = [];
if (!empty($_GET['id'])) {
    $inv = row('SELECT * FROM invoices WHERE id = ?', [(int)$_GET['id']]);
    if (!$inv) { flash('warning', 'Invoice not found.'); redirect('admin/invoices.php'); }
    if ($inv['status'] === 'cancelled') { flash('warning', 'Cancelled invoices cannot be edited.'); redirect('admin/invoice.php?id=' . $inv['id']); }
    $items = rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$inv['id']]);
    $clientId = (int)$inv['client_id'];
} else {
    $clientId = (int)($_GET['client'] ?? $_POST['client_id'] ?? 0);
}
$client = $clientId ? row('SELECT c.*, u.full_name agent_name FROM clients c LEFT JOIN users u ON u.id = c.agent_id WHERE c.id = ?', [$clientId]) : null;
$clients = $client ? [] : rows('SELECT c.id, c.full_name, c.phone, u.full_name agent_name FROM clients c LEFT JOIN users u ON u.id = c.agent_id WHERE c.status <> "lost" ORDER BY c.created_at DESC LIMIT 500');

$errors = [];
if (is_post()) {
    csrf_check();
    if (!$client) {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $client = row('SELECT * FROM clients WHERE id = ?', [$clientId]);
        if (!$client) $errors[] = 'Please choose a client.';
    }
    $calc = parse_invoice_items($_POST);
    $date = (string)($_POST['invoice_date'] ?? date('Y-m-d'));
    if (!DateTime::createFromFormat('Y-m-d', $date)) $errors[] = 'Invalid invoice date.';
    if (!$calc['items']) $errors[] = 'Add at least one item.';
    if ($calc['total'] <= 0) $errors[] = 'Invoice total must be greater than zero.';
    $notes = trim((string)($_POST['notes'] ?? ''));

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($inv) {
                q('UPDATE invoices SET invoice_date=?, subtotal=?, discount=?, tax_rate=?, tax_amount=?, total=?, notes=? WHERE id=?',
                    [$date, $calc['subtotal'], $calc['discount'], $calc['taxRate'], $calc['tax'], $calc['total'], $notes ?: null, $inv['id']]);
                q('DELETE FROM invoice_items WHERE invoice_id = ?', [$inv['id']]);
                $invoiceId = (int)$inv['id'];
            } else {
                $no = next_invoice_no();
                q('INSERT INTO invoices (invoice_no, client_id, agent_id, invoice_date, subtotal, discount, tax_rate, tax_amount, total, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$no, $client['id'], $client['agent_id'], $date, $calc['subtotal'], $calc['discount'], $calc['taxRate'], $calc['tax'], $calc['total'], $notes ?: null, $me['id']]);
                $invoiceId = (int)$pdo->lastInsertId();
            }
            foreach ($calc['items'] as $n => $it) {
                q('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?)',
                    [$invoiceId, $it['description'], $it['qty'], $it['unit_price'], $it['line_total'], $n]);
            }
            recalc_invoice($invoiceId);
            if (!$inv) {
                if (in_array($client['status'], ['new', 'contacted'], true)) q('UPDATE clients SET status = "negotiating" WHERE id = ?', [$client['id']]);
                log_activity((int)$client['id'], (int)$me['id'], 'invoice', 'Invoice ' . $no . ' created for ' . money($calc['total']) . '.');
            } else {
                log_activity((int)$client['id'], (int)$me['id'], 'invoice', 'Invoice ' . $inv['invoice_no'] . ' updated. New total ' . money($calc['total']) . '.');
            }
            $pdo->commit();
            flash('success', $inv ? 'Invoice updated.' : 'Invoice created. Record payments as the client pays.');
            redirect('admin/invoice.php?id=' . $invoiceId);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        }
    }
    // keep posted rows on error
    $items = array_map(function ($it) { return $it; }, $calc['items']);
}

$defaultTax = $inv ? $inv['tax_rate'] : setting('default_tax_rate', '0');
if (!$items) $items = [['description' => '', 'qty' => 1, 'unit_price' => '']];
$sym = setting('currency_symbol', '$');

$pageTitle = $inv ? 'Edit ' . $inv['invoice_no'] : 'New invoice';
$area = 'admin';
$active = 'invoices';
include dirname(__DIR__) . '/includes/header.php';
?>
<a href="<?= $inv ? url('admin/invoice.php?id=' . $inv['id']) : ($client ? url('admin/client.php?id=' . $client['id']) : url('admin/invoices.php')) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
<div class="page-head">
  <div><h1 class="page-title"><?= e($pageTitle) ?></h1>
  <?php if ($client): ?><div class="text-muted small">For <strong><?= e($client['full_name']) ?></strong><?= !empty($client['agent_name']) ? ' · referred by ' . e($client['agent_name']) : '' ?></div><?php endif; ?></div>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($inv && (float)$inv['amount_paid'] > 0): ?><div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i><?= money($inv['amount_paid']) ?> has already been paid on this invoice. Changing the total will update the balance.</div><?php endif; ?>

<form method="post" id="invoiceForm" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3"><div class="card-body">
        <?php if (!$client): ?>
          <div class="mb-3"><label class="form-label">Client</label>
            <select class="form-select" name="client_id" required data-searchable data-placeholder="Type client name, phone or agent…">
              <option value="">Choose client…</option>
              <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= (int)old('client_id') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?> · <?= e($c['phone']) ?><?= $c['agent_name'] ? ' (' . e($c['agent_name']) . ')' : '' ?></option><?php endforeach; ?>
            </select></div>
        <?php else: ?><input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">Invoice date</label><input class="form-control" type="date" name="invoice_date" value="<?= e(old('invoice_date', $inv['invoice_date'] ?? date('Y-m-d'))) ?>" required></div>
          <div class="col-6"><label class="form-label">Invoice #</label><input class="form-control" value="<?= e($inv['invoice_no'] ?? 'Auto') ?>" disabled></div>
        </div>
      </div></div>

      <div class="section-title mt-0">Items</div>
      <div id="items">
        <?php foreach ($items as $it): ?>
        <div class="item-row">
          <button type="button" class="btn btn-sm btn-link text-danger remove-item" aria-label="Remove item"><i class="bi bi-x-lg"></i></button>
          <div class="mb-2 pe-4"><label class="form-label small">Description</label><input class="form-control" name="item_desc[]" value="<?= e($it['description']) ?>" placeholder="e.g. 18K white gold oval diamond ring, 2.01ct"></div>
          <div class="row g-2">
            <div class="col-4"><label class="form-label small">Qty</label><input class="form-control js-qty" type="number" step="0.01" min="0" inputmode="decimal" name="item_qty[]" value="<?= e(rtrim(rtrim((string)$it['qty'], '0'), '.') ?: '1') ?>"></div>
            <div class="col-8"><label class="form-label small">Unit price (<?= e($sym) ?>)</label><input class="form-control js-price" type="number" step="0.01" min="0" inputmode="decimal" name="item_price[]" value="<?= e($it['unit_price']) ?>"></div>
          </div>
          <div class="text-end small mt-2 text-muted">Line total: <strong class="js-line text-dark">—</strong></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-dark w-100 mb-3" id="addItem"><i class="bi bi-plus-lg me-1"></i>Add item</button>

      <div class="card"><div class="card-body">
        <label class="form-label">Notes <span class="text-muted fw-normal">(shown on invoice)</span></label>
        <textarea class="form-control" name="notes" rows="3" placeholder="Certificate numbers, delivery date, warranty…"><?= e(old('notes', $inv['notes'] ?? '')) ?></textarea>
      </div></div>
    </div>

    <div class="col-lg-4">
      <div class="totals-box" style="position:sticky;top:calc(var(--top-h) + 1rem)">
        <div class="line"><span class="text-muted">Subtotal</span><strong id="tSub">—</strong></div>
        <div class="line align-items-center"><span class="text-muted">Discount (<?= e($sym) ?>)</span><input class="form-control form-control-sm text-end" style="max-width:120px" type="number" step="0.01" min="0" inputmode="decimal" name="discount" id="discount" value="<?= e(old('discount', $inv['discount'] ?? '0')) ?>"></div>
        <div class="line align-items-center"><span class="text-muted">Tax %</span><input class="form-control form-control-sm text-end" style="max-width:120px" type="number" step="0.01" min="0" inputmode="decimal" name="tax_rate" id="taxRate" value="<?= e(old('tax_rate', $defaultTax)) ?>"></div>
        <div class="line"><span class="text-muted">Tax</span><span id="tTax">—</span></div>
        <div class="line grand"><span>Total</span><span id="tTotal">—</span></div>
        <?php if ($client && !empty($client['agent_id'])): ?>
          <div class="line small text-muted mt-2"><span>Agent bonus on full payment</span><span id="tBonus">—</span></div>
        <?php endif; ?>
        <button class="btn btn-gold btn-lg w-100 mt-3"><?= $inv ? 'Save invoice' : 'Create invoice' ?></button>
        <div class="small text-muted mt-2 text-center">New invoices start as <strong>Pending</strong>.</div>
      </div>
    </div>
  </div>
</form>

<template id="itemTpl">
  <div class="item-row">
    <button type="button" class="btn btn-sm btn-link text-danger remove-item" aria-label="Remove item"><i class="bi bi-x-lg"></i></button>
    <div class="mb-2 pe-4"><label class="form-label small">Description</label><input class="form-control" name="item_desc[]" placeholder="Item description"></div>
    <div class="row g-2">
      <div class="col-4"><label class="form-label small">Qty</label><input class="form-control js-qty" type="number" step="0.01" min="0" inputmode="decimal" name="item_qty[]" value="1"></div>
      <div class="col-8"><label class="form-label small">Unit price (<?= e($sym) ?>)</label><input class="form-control js-price" type="number" step="0.01" min="0" inputmode="decimal" name="item_price[]"></div>
    </div>
    <div class="text-end small mt-2 text-muted">Line total: <strong class="js-line text-dark">—</strong></div>
  </div>
</template>
<?php
$bonusRate = 0;
if ($client && !empty($client['agent_id'])) {
    $ag = row('SELECT * FROM users WHERE id = ?', [$client['agent_id']]);
    $bonusRate = $ag ? agent_rate($ag) : 0;
}
$pageScripts = '<script>
(function(){
  var sym = ' . json_encode($sym) . ', rate = ' . json_encode($bonusRate) . ', net = ' . json_encode(setting('commission_basis', 'total') === 'net') . ';
  var box = document.getElementById("items");
  function fmt(n){ return sym + (Math.round(n*100)/100).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function calc(){
    var sub = 0;
    box.querySelectorAll(".item-row").forEach(function(r){
      var q = parseFloat(r.querySelector(".js-qty").value)||0, p = parseFloat(r.querySelector(".js-price").value)||0;
      var l = Math.round(q*p*100)/100; sub += l; r.querySelector(".js-line").textContent = fmt(l);
    });
    var d = Math.min(parseFloat(document.getElementById("discount").value)||0, sub);
    var tr = parseFloat(document.getElementById("taxRate").value)||0;
    var tax = Math.round((sub-d)*tr)/100, total = sub - d + tax;
    document.getElementById("tSub").textContent = fmt(sub);
    document.getElementById("tTax").textContent = fmt(tax);
    document.getElementById("tTotal").textContent = fmt(total);
    var b = document.getElementById("tBonus"); if (b) b.textContent = fmt((net ? sub - d : total) * rate / 100) + " (" + rate + "%)";
  }
  document.getElementById("addItem").addEventListener("click", function(){
    box.appendChild(document.getElementById("itemTpl").content.cloneNode(true));
    var rows = box.querySelectorAll(".item-row"); rows[rows.length-1].querySelector("input").focus(); calc();
  });
  box.addEventListener("click", function(e){
    var b = e.target.closest(".remove-item"); if (!b) return;
    if (box.querySelectorAll(".item-row").length > 1) { b.closest(".item-row").remove(); calc(); }
  });
  document.getElementById("invoiceForm").addEventListener("input", calc);
  calc();
})();
</script>';
include dirname(__DIR__) . '/includes/footer.php';
