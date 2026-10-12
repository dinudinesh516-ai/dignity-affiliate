<?php
/**
 * Business logic: QR, referrals, invoices, commissions.
 */

require_once APP_ROOT . '/lib/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\Common\EccLevel;

/** PNG bytes of a QR code, or null if GD is unavailable. */
function qr_png(string $data, int $scale = 10): ?string
{
    if (!extension_loaded('gd')) return null;
    $opts = new QROptions([
        'outputType'   => QROutputInterface::GDIMAGE_PNG,
        'outputBase64' => false,
        'eccLevel'     => EccLevel::M,
        'scale'        => $scale,
        'quietzoneSize'=> 2,
    ]);
    return (new QRCode($opts))->render($data);
}

/** SVG markup of a QR code (no GD needed). */
function qr_svg(string $data): string
{
    $opts = new QROptions([
        'outputType'   => QROutputInterface::MARKUP_SVG,
        'outputBase64' => false,
        'eccLevel'     => EccLevel::M,
        'quietzoneSize'=> 2,
        'svgAddXmlHeader' => false,
    ]);
    return (new QRCode($opts))->render($data);
}

// ---------------- Referrals ----------------

const INTEREST_OPTIONS = ['Engagement Ring', 'Wedding Band', 'Rings', 'Necklace', 'Earrings', 'Bracelet', 'Watch', 'High End Jewelry', 'Gemstone', 'Custom Design', 'Other'];

/**
 * Validate + insert a referred client.
 * Returns ['ok'=>true,'client'=>array] or ['ok'=>false,'error'=>string,'duplicate'=>?array].
 */
function create_referral(array $in, ?array $agent, string $source, bool $notifyAdmin = true): array
{
    $name  = trim((string)($in['full_name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $interests = array_values(array_intersect((array)($in['interests'] ?? []), INTEREST_OPTIONS));
    $looking = trim((string)($in['looking_for'] ?? ''));
    $budget  = trim((string)($in['budget'] ?? ''));
    $pref    = in_array($in['preferred_contact'] ?? '', ['whatsapp', 'call', 'email'], true) ? $in['preferred_contact'] : 'whatsapp';

    if ($name === '' || mb_strlen($name) > 120) return ['ok' => false, 'error' => 'Please enter the full name.'];
    $pk = phone_key($phone);
    if (strlen($pk) < 8) return ['ok' => false, 'error' => 'Please enter a valid phone number.'];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    if ($pref === 'email' && $email === '') return ['ok' => false, 'error' => 'Please add an email address if email is the preferred contact.'];
    if (!$interests && $looking === '') return ['ok' => false, 'error' => 'Please tell us what the client is looking for.'];
    if (in_array('Other', $interests, true) && $looking === '') return ['ok' => false, 'error' => 'You selected "Other" — please describe what is needed in the note.'];

    $ek = $email !== '' ? strtolower($email) : null;
    $dup = row('SELECT * FROM clients WHERE phone_key = ? OR (email_key IS NOT NULL AND email_key = ?) LIMIT 1', [$pk, $ek ?? '__none__']);
    if ($dup) {
        return ['ok' => false, 'duplicate' => $dup, 'error' => 'This client has already been referred to Dignity Jewels.'];
    }

    try {
        q('INSERT INTO clients (agent_id, full_name, email, phone, phone_key, email_key, interests, looking_for, budget, preferred_contact, source)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
            $agent['id'] ?? null, $name, $email ?: null, $phone, $pk, $ek,
            $interests ? implode(', ', $interests) : null, $looking ?: null, $budget ?: null, $pref, $source,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'duplicate' => [], 'error' => 'This client has already been referred to Dignity Jewels.'];
        }
        throw $e;
    }
    $id = (int)db()->lastInsertId();
    $client = row('SELECT * FROM clients WHERE id = ?', [$id]);
    log_activity($id, $agent['id'] ?? null, 'note', ($source === 'self' ? 'Client registered directly on the website (no referral)' : 'Referral received via ' . ($source === 'qr' ? 'QR code / link' : ($notifyAdmin ? 'agent dashboard' : 'admin panel')))
        . ($agent ? ' — agent ' . $agent['full_name'] : ''));

    // Notifications (failures don't block the referral)
    if ($notifyAdmin) { try { email_admin_new_referral($client, $agent); } catch (Throwable $e) {} }
    try { email_client_thanks($client); } catch (Throwable $e) {}

    return ['ok' => true, 'client' => $client];
}

function log_activity(int $clientId, ?int $userId, string $type, string $body): void
{
    q('INSERT INTO client_activity (client_id, user_id, type, body) VALUES (?,?,?,?)', [$clientId, $userId, $type, $body]);
}

// ---------------- Invoices ----------------

function next_invoice_no(): string
{
    $prefix = (string)setting('invoice_prefix', 'DJ-');
    $year = date('Y');
    $last = val('SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . $year . '-%']);
    $n = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
    do {
        $no = $prefix . $year . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        $n++;
    } while (val('SELECT id FROM invoices WHERE invoice_no = ?', [$no]));
    return $no;
}

/**
 * Parse invoice item rows from POST and compute totals.
 */
function parse_invoice_items(array $post): array
{
    $items = [];
    $desc = (array)($post['item_desc'] ?? []);
    $qty = (array)($post['item_qty'] ?? []);
    $price = (array)($post['item_price'] ?? []);
    foreach ($desc as $i => $d) {
        $d = trim((string)$d);
        $qn = round((float)($qty[$i] ?? 0), 2);
        $pr = round((float)($price[$i] ?? 0), 2);
        if ($d === '' && $pr == 0) continue;
        if ($d === '') $d = 'Item';
        if ($qn <= 0) $qn = 1;
        $items[] = ['description' => mb_substr($d, 0, 255), 'qty' => $qn, 'unit_price' => $pr, 'line_total' => round($qn * $pr, 2)];
    }
    $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
    $discount = max(0, round((float)($post['discount'] ?? 0), 2));
    $discount = min($discount, $subtotal);
    $taxRate = max(0, round((float)($post['tax_rate'] ?? 0), 2));
    $tax = round(($subtotal - $discount) * $taxRate / 100, 2);
    $total = round($subtotal - $discount + $tax, 2);
    return compact('items', 'subtotal', 'discount', 'taxRate', 'tax', 'total');
}

/**
 * Recompute amount paid & status; create/remove commission as needed.
 * Throws RuntimeException when a change would invalidate a bonus that already has payouts.
 */
function recalc_invoice(int $invoiceId): array
{
    $inv = row('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    if (!$inv) throw new RuntimeException('Invoice not found.');
    $paid = round((float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ?', [$invoiceId]), 2);
    $total = (float)$inv['total'];

    if ($inv['status'] === 'cancelled') {
        $status = 'cancelled';
    } elseif ($total > 0 && $paid >= $total - 0.005) {
        $status = 'paid';
    } elseif ($paid > 0) {
        $status = 'partial';
    } else {
        $status = 'pending';
    }

    $commission = row('SELECT * FROM commissions WHERE invoice_id = ?', [$invoiceId]);

    if ($status === 'paid') {
        $paidAt = $inv['paid_at'] ?: date('Y-m-d H:i:s');
        q('UPDATE invoices SET amount_paid = ?, status = ?, paid_at = ? WHERE id = ?', [$paid, $status, $paidAt, $invoiceId]);
        if ($inv['agent_id']) {
            $agent = row('SELECT * FROM users WHERE id = ?', [$inv['agent_id']]);
            $rate = $agent ? agent_rate($agent) : (float)setting('commission_rate', '5');
            $base = setting('commission_basis', 'total') === 'net'
                ? (float)$inv['subtotal'] - (float)$inv['discount']
                : $total;
            $amount = round($base * $rate / 100, 2);
            if (!$commission) {
                q('INSERT INTO commissions (agent_id, invoice_id, client_id, sale_amount, rate, amount) VALUES (?,?,?,?,?,?)',
                    [$inv['agent_id'], $invoiceId, $inv['client_id'], $base, $rate, $amount]);
                $commission = row('SELECT * FROM commissions WHERE invoice_id = ?', [$invoiceId]);
                $client = row('SELECT * FROM clients WHERE id = ?', [$inv['client_id']]);
                q('UPDATE clients SET status = "won" WHERE id = ?', [$inv['client_id']]);
                log_activity((int)$inv['client_id'], null, 'invoice', 'Invoice ' . $inv['invoice_no'] . ' fully paid. Referral bonus of ' . money($amount) . ' credited to ' . ($agent['full_name'] ?? 'agent') . '.');
                if ($agent) { try { email_agent_bonus_earned($agent, $commission, $client); } catch (Throwable $e) {} }
            } elseif ((float)$commission['amount_paid'] == 0 && (abs((float)$commission['amount'] - $amount) > 0.004)) {
                // Invoice total changed before any payout — keep bonus in sync
                q('UPDATE commissions SET sale_amount = ?, rate = ?, amount = ? WHERE id = ?', [$base, $rate, $amount, $commission['id']]);
            }
        }
    } else {
        if ($commission) {
            if ((float)$commission['amount_paid'] > 0) {
                throw new RuntimeException('This change would make the invoice unpaid, but the agent\'s referral bonus has already been (partly) paid out. Remove the bonus payouts first.');
            }
            q('DELETE FROM commissions WHERE id = ?', [$commission['id']]);
        }
        q('UPDATE invoices SET amount_paid = ?, status = ?, paid_at = NULL WHERE id = ?', [$paid, $status, $invoiceId]);
    }
    return row('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
}

function recalc_commission(int $commissionId): void
{
    $c = row('SELECT * FROM commissions WHERE id = ?', [$commissionId]);
    if (!$c) return;
    $paid = round((float)val('SELECT COALESCE(SUM(amount),0) FROM payouts WHERE commission_id = ?', [$commissionId]), 2);
    $status = $paid <= 0 ? 'unpaid' : ($paid >= (float)$c['amount'] - 0.005 ? 'paid' : 'partial');
    q('UPDATE commissions SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $status, $commissionId]);
}

/** Aggregate numbers for an agent (or all agents when $agentId is null). */
function agent_totals(?int $agentId = null): array
{
    $w = $agentId ? ' WHERE agent_id = ' . (int)$agentId : '';
    $a = $agentId ? ' AND i.agent_id = ' . (int)$agentId : ' AND i.agent_id IS NOT NULL';
    $earned = (float)val('SELECT COALESCE(SUM(amount),0) FROM commissions' . $w);
    $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payouts' . $w);
    // Projected bonus on open invoices (not yet fully paid)
    $rate = (float)setting('commission_rate', '5');
    $basisNet = setting('commission_basis', 'total') === 'net';
    $pipelineRows = rows('SELECT i.*, u.commission_rate FROM invoices i LEFT JOIN users u ON u.id = i.agent_id
                          WHERE i.status IN ("pending","partial")' . $a);
    $projected = 0.0;
    foreach ($pipelineRows as $r) {
        $rr = $r['commission_rate'] !== null ? (float)$r['commission_rate'] : $rate;
        $base = $basisNet ? (float)$r['subtotal'] - (float)$r['discount'] : (float)$r['total'];
        $projected += $base * $rr / 100;
    }
    return [
        'referrals' => (int)val('SELECT COUNT(*) FROM clients' . ($agentId ? ' WHERE agent_id = ' . (int)$agentId : ' WHERE agent_id IS NOT NULL')),
        'won'       => (int)val('SELECT COUNT(*) FROM clients WHERE status = "won"' . ($agentId ? ' AND agent_id = ' . (int)$agentId : ' AND agent_id IS NOT NULL')),
        'sales'     => (float)val('SELECT COALESCE(SUM(sale_amount),0) FROM commissions' . $w),
        'earned'    => $earned,
        'paid'      => $paid,
        'balance'   => round($earned - $paid, 2),
        'projected' => round($projected, 2),
    ];
}

const PAYMENT_METHODS = ['Cash', 'Credit/Debit Card', 'Bank Transfer / Wire', 'Zelle', 'Venmo', 'Check', 'PayPal', 'Financing', 'Other'];
const PAYOUT_METHODS = ['Bank Transfer', 'Zelle', 'Venmo', 'Check', 'Cash', 'PayPal', 'Other'];

/** One-time database upgrades for existing installs. */
function app_schema_upgrade(): void
{
    if ((int)setting('schema_version', '1') >= 2) return;
    try {
        db()->exec("ALTER TABLE clients MODIFY source ENUM('qr','agent','admin','self') NOT NULL DEFAULT 'qr'");
        save_setting('schema_version', '2');
    } catch (Throwable $e) { /* not installed yet */ }
}
