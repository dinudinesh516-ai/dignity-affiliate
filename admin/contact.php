<?php
/** Logs a WhatsApp / call contact attempt and redirects to the app. */
require dirname(__DIR__) . '/includes/bootstrap.php';
$me = require_admin();

$client = row('SELECT * FROM clients WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$client) redirect('admin/clients.php');
$agent = $client['agent_id'] ? row('SELECT full_name FROM users WHERE id = ?', [$client['agent_id']]) : null;
$via = ($_GET['via'] ?? '') === 'call' ? 'call' : 'whatsapp';

log_activity((int)$client['id'], (int)$me['id'], $via, $via === 'call' ? 'Called the client.' : 'Opened WhatsApp chat with the client.');
if ($client['status'] === 'new') q('UPDATE clients SET status = "contacted" WHERE id = ?', [$client['id']]);

if ($via === 'call') {
    header('Location: tel:+' . whatsapp_number($client['phone']));
    exit;
}
$tpl = trim((string)setting('whatsapp_template', ''));
if ($tpl === '') $tpl = 'Hi {client}, this is Dignity Jewels. {agent} let us know you are looking for {interest}. When is a good time to chat?';
$text = strtr($tpl, [
    '{client}' => explode(' ', $client['full_name'])[0],
    '{agent}' => $agent ? $agent['full_name'] : 'A friend',
    '{interest}' => mb_strtolower($client['interests'] ?: 'jewelry'),
]);
header('Location: ' . whatsapp_link($client['phone'], $text));
exit;
