<?php
/**
 * Instant duplicate check used by the client/referral forms while typing.
 * GET ?cc=US&phone=2135550101  →  {"exists":bool, ...}
 * Public callers only learn yes/no. Agents also learn whether it is their own referral;
 * admins get the client's name and a link.
 */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = current_user();
// Light rate limit for anonymous visitors (stops bulk "is this number a client?" probing)
if (!$me) {
    $bucket = 'phonecheck:' . client_ip();
    if (too_many_attempts($bucket, 40, 10)) { echo json_encode(['exists' => false, 'limited' => true]); exit; }
    hit_attempt($bucket);
}

$phone = phone_combine((string)($_GET['cc'] ?? ''), (string)($_GET['phone'] ?? ''));
$key = $phone !== '' ? phone_key($phone) : '';
if (strlen($key) < 8) { echo json_encode(['exists' => false]); exit; }

$c = row('SELECT c.id, c.full_name, c.agent_id, u.full_name agent_name FROM clients c LEFT JOIN users u ON u.id = c.agent_id WHERE c.phone_key = ? LIMIT 1', [$key]);
if (!$c) { echo json_encode(['exists' => false]); exit; }

$out = ['exists' => true];
if ($me && $me['role'] === 'agent') {
    $out['mine'] = (int)$c['agent_id'] === (int)$me['id'];
} elseif ($me && $me['role'] === 'admin') {
    $out['name'] = $c['full_name'];
    $out['agent'] = $c['agent_name'];
    $out['url'] = url('admin/client.php?id=' . $c['id']);
}
echo json_encode($out);
