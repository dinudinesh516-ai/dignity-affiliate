<?php
/** Serves an agent's referral QR code as PNG (or SVG fallback). ?c=CODE[&dl=1] */
require __DIR__ . '/includes/bootstrap.php';

$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? '')));
$agent = $code ? row('SELECT id FROM users WHERE referral_code = ? AND role = "agent"', [$code]) : null;
if (!$agent) { http_response_code(404); exit('Not found'); }

$link = referral_link($code);
$png = qr_png($link, 12);
if (isset($_GET['dl'])) header('Content-Disposition: attachment; filename="Dignity-QR-' . $code . '.png"');
header('Cache-Control: public, max-age=86400');
if ($png) {
    header('Content-Type: image/png');
    echo $png;
} else {
    header('Content-Type: image/svg+xml');
    echo qr_svg($link);
}
