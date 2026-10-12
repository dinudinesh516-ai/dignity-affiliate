<?php
/**
 * Brevo transactional email + branded templates.
 */

function brevo_send(string $toEmail, string $toName, string $subject, string $html, array $attachments = [], ?string $replyTo = null): bool
{
    global $CONFIG;
    $payload = [
        'sender'      => ['name' => $CONFIG['BREVO_SENDER_NAME'], 'email' => $CONFIG['BREVO_SENDER_EMAIL']],
        'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $html,
    ];
    if ($replyTo) $payload['replyTo'] = ['email' => $replyTo];
    if ($attachments) {
        $payload['attachment'] = array_map(function ($a) {
            return ['name' => $a['name'], 'content' => base64_encode($a['content'])];
        }, $attachments);
    }

    $ok = false;
    $response = '';
    $key = $CONFIG['BREVO_API_KEY'] ?? '';
    if (!$key || strpos($key, 'CHANGE_ME') === 0) {
        $response = 'Brevo API key not configured — email not sent.';
        // Local development: save a preview of the email instead
        if (!empty($CONFIG['DEBUG'])) {
            $dir = APP_ROOT . '/storage/mail-preview';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            $file = date('Ymd-His') . '-' . substr(md5($toEmail . $subject . microtime()), 0, 6) . '.html';
            @file_put_contents($dir . '/' . $file, '<!-- To: ' . htmlspecialchars($toEmail) . ' | Subject: ' . htmlspecialchars($subject) . " -->\n" . $html);
            $response .= ' Preview saved to storage/mail-preview/' . $file;
        }
    } elseif (!function_exists('curl_init')) {
        $response = 'PHP cURL extension missing.';
    } else {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $key,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $ok = $code >= 200 && $code < 300;
        $response = $err ?: ('HTTP ' . $code . ' ' . $body);
    }

    try {
        q('INSERT INTO email_log (to_email, subject, ok, response) VALUES (?, ?, ?, ?)',
            [$toEmail, mb_substr($subject, 0, 250), $ok ? 1 : 0, mb_substr($response, 0, 2000)]);
    } catch (Throwable $e) { /* ignore */ }

    return $ok;
}

/** Send to every admin notification address. */
function notify_admins(string $subject, string $html): void
{
    $list = array_filter(array_map('trim', explode(',', (string)setting('notify_emails', ''))));
    if (!$list) {
        $list = array_column(rows('SELECT email FROM users WHERE role = "admin" AND status = "active"'), 'email');
    }
    foreach (array_unique($list) as $to) {
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) brevo_send($to, 'Dignity Admin', $subject, $html);
    }
}

function email_button(string $href, string $label): string
{
    return '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td style="border-radius:6px;background:#1c1c1c">'
        . '<a href="' . e($href) . '" style="display:inline-block;padding:13px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;letter-spacing:.3px">'
        . e($label) . '</a></td></tr></table>';
}

function email_layout(string $heading, string $bodyHtml): string
{
    $company = e(setting('company_name', 'Dignity Jewels'));
    $addr = e(setting('company_address', ''));
    $phone = e(setting('company_phone', ''));
    $site = e(setting('company_website', 'https://dignityjewelry.com'));
    $logo = setting('logo_url', '');
    $brand = $logo
        ? '<img src="' . e($logo) . '" alt="' . $company . '" style="max-height:48px;max-width:220px">'
        : '<span style="font-family:Georgia,serif;font-size:24px;letter-spacing:4px;color:#1c1c1c">DIGNITY JEWELS</span>';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f4f1ec;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#2b2b2b">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1ec;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden">'
        . '<tr><td style="padding:26px 28px 18px;text-align:center;border-bottom:3px solid #b8975a">' . $brand . '</td></tr>'
        . '<tr><td style="padding:28px">'
        . '<h1 style="margin:0 0 16px;font-family:Georgia,serif;font-weight:normal;font-size:24px;color:#1c1c1c">' . e($heading) . '</h1>'
        . '<div style="font-size:15px;line-height:1.6">' . $bodyHtml . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:18px 28px;background:#faf8f5;font-size:12px;color:#8a8580;text-align:center;line-height:1.6">'
        . $company . '<br>' . $addr . ($phone ? '<br>' . $phone : '') . '<br><a href="' . $site . '" style="color:#b8975a">' . $site . '</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function email_kv(array $pairs): string
{
    $h = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eee;border-radius:8px;margin:12px 0">';
    foreach ($pairs as $k => $v) {
        if ($v === null || $v === '') continue;
        $h .= '<tr><td style="padding:10px 14px;border-bottom:1px solid #f1f1f1;color:#8a8580;font-size:13px;width:38%;vertical-align:top">' . e($k)
            . '</td><td style="padding:10px 14px;border-bottom:1px solid #f1f1f1;font-size:14px">' . nl2br(e($v)) . '</td></tr>';
    }
    return $h . '</table>';
}

// ---------------- Specific emails ----------------

function email_agent_welcome(array $agent, ?string $setupToken = null, ?string $plainPassword = null): bool
{
    $code = $agent['referral_code'];
    $link = referral_link($code);
    $body = '<p>Hi ' . e($agent['full_name']) . ',</p>'
        . '<p>Welcome to the <strong>Dignity Jewels</strong> affiliate program! You earn a <strong>'
        . e(rtrim(rtrim(number_format(agent_rate($agent), 2), '0'), '.')) . '% referral bonus</strong> on every sale from clients you refer, once the client has paid in full.</p>';

    if ($setupToken) {
        $body .= '<p>Please set your password to activate your account:</p>'
            . email_button(url('reset.php?token=' . $setupToken), 'Set my password')
            . '<p style="font-size:13px;color:#8a8580">This link is valid for 7 days.</p>';
    }
    if ($plainPassword) {
        $body .= '<p>Your login details:</p>' . email_kv([
            'Login page' => url('login.php'),
            'Username'   => $agent['username'],
            'Password'   => $plainPassword,
        ]) . '<p style="font-size:13px;color:#8a8580">Please change your password after your first login.</p>';
    } elseif (!$setupToken) {
        $body .= email_button(url('login.php'), 'Open my dashboard');
    }
    if (!$plainPassword) {
        $body .= '<p style="font-size:13px">Username: <strong>' . e($agent['username']) . '</strong> (you can also sign in with your email).</p>';
    }

    $body .= '<h3 style="font-family:Georgia,serif;font-weight:normal;margin:26px 0 8px">Your personal referral QR code</h3>'
        . '<p>Ask clients to scan this code. It opens a short form so Dignity can contact them — and the referral is automatically credited to you.</p>'
        . '<p style="text-align:center;margin:18px 0"><img src="' . e(qr_image_url($code)) . '" alt="QR code" width="220" height="220" style="border:1px solid #eee;border-radius:8px;padding:8px"></p>'
        . email_kv(['Referral code' => $code, 'Referral link' => $link])
        . '<p style="font-size:13px;color:#8a8580">The QR code is also attached to this email so you can save it to your phone.</p>';

    $attachments = [];
    $png = qr_png($link);
    if ($png) $attachments[] = ['name' => 'Dignity-QR-' . $code . '.png', 'content' => $png];

    return brevo_send($agent['email'], $agent['full_name'], 'Welcome to Dignity Jewels - your affiliate account is ready', email_layout('Welcome aboard!', $body), $attachments);
}

function email_password_reset(array $user, string $token): bool
{
    $body = '<p>Hi ' . e($user['full_name']) . ',</p>'
        . '<p>We received a request to reset your password. Tap the button below to choose a new one.</p>'
        . email_button(url('reset.php?token=' . $token), 'Reset password')
        . '<p style="font-size:13px;color:#8a8580">This link expires in 1 hour. If you didn\'t request this, you can ignore this email.</p>';
    return brevo_send($user['email'], $user['full_name'], 'Reset your Dignity Jewels password', email_layout('Password reset', $body));
}

function email_admin_new_referral(array $client, ?array $agent): void
{
    $body = '<p>' . ($agent
            ? '<strong>' . e($agent['full_name']) . '</strong> (' . e($agent['phone'] ?: $agent['email']) . ') has referred a new client.'
            : ($client['source'] === 'self' ? 'A new client registered directly on the website (no referral).' : 'A new client lead was added.')) . '</p>'
        . email_kv([
            'Agent'          => $agent ? $agent['full_name'] . ' · ' . $agent['referral_code'] : '—',
            'Agent phone'    => $agent['phone'] ?? '',
            'Agent email'    => $agent['email'] ?? '',
            'Client name'    => $client['full_name'],
            'Client phone'   => $client['phone'],
            'Client email'   => $client['email'] ?? '',
            'Interested in'  => $client['interests'] ?? '',
            'Looking for'    => $client['looking_for'] ?? '',
            'Budget'         => $client['budget'] ?? '',
            'Prefers'        => ucfirst($client['preferred_contact'] ?? ''),
            'Source'         => $client['source'] === 'qr' ? 'QR code / referral link' : ($client['source'] === 'self' ? 'Registered directly on the website' : 'Added by agent'),
        ])
        . email_button(url('admin/client.php?id=' . $client['id']), 'Open in admin panel');
    $self = ($client['source'] ?? '') === 'self';
    $subject = ($self ? 'New client registration: ' : 'New referral: ') . $client['full_name'] . ($agent ? ' (by ' . $agent['full_name'] . ')' : '');
    notify_admins($subject, email_layout($self ? 'New client registration' : 'New client referral', $body));
}

function email_admin_new_agent(array $agent): void
{
    $body = '<p>A new agent has registered for the affiliate program.</p>'
        . email_kv(['Name' => $agent['full_name'], 'Email' => $agent['email'], 'Phone' => $agent['phone'], 'Code' => $agent['referral_code']])
        . email_button(url('admin/agent.php?id=' . $agent['id']), 'View agent');
    notify_admins('New affiliate agent: ' . $agent['full_name'], email_layout('New agent registered', $body));
}

function email_client_thanks(array $client): void
{
    if (empty($client['email']) || setting('client_confirmation_email', '1') !== '1') return;
    $body = '<p>Hi ' . e($client['full_name']) . ',</p>'
        . '<p>Thank you for your interest in Dignity Jewels. A member of our team will reach out to you shortly'
        . ($client['preferred_contact'] ? ' via <strong>' . e(ucfirst($client['preferred_contact'])) . '</strong>' : '') . ' to help you find exactly what you are looking for.</p>'
        . '<p>In the meantime, explore our collections:</p>'
        . email_button(setting('company_website', 'https://dignityjewelry.com'), 'Visit Dignity Jewels')
        . '<p><em>You dream it, We make it.</em></p>';
    brevo_send($client['email'], $client['full_name'], 'Thank you — Dignity Jewels will be in touch', email_layout('We received your request', $body));
}

function email_agent_bonus_earned(array $agent, array $commission, array $client): void
{
    $body = '<p>Hi ' . e($agent['full_name']) . ',</p>'
        . '<p>Great news! Your referral <strong>' . e($client['full_name']) . '</strong> has completed their purchase and you\'ve earned a referral bonus.</p>'
        . email_kv([
            'Sale amount' => money($commission['sale_amount']),
            'Bonus rate'  => rtrim(rtrim($commission['rate'], '0'), '.') . '%',
            'Your bonus'  => money($commission['amount']),
        ])
        . email_button(url('agent/earnings.php'), 'View my earnings');
    brevo_send($agent['email'], $agent['full_name'], 'You earned a ' . money($commission['amount']) . ' referral bonus!', email_layout('Referral bonus earned', $body));
}

function email_agent_payout(array $agent, array $payout, array $commission): void
{
    $remaining = (float)$commission['amount'] - (float)$commission['amount_paid'];
    $body = '<p>Hi ' . e($agent['full_name']) . ',</p>'
        . '<p>A referral bonus payment has been recorded for you.</p>'
        . email_kv([
            'Amount paid' => money($payout['amount']),
            'Date'        => fdate($payout['payout_date']),
            'Method'      => $payout['method'],
            'Reference'   => $payout['reference'] ?? '',
            'Bonus total' => money($commission['amount']),
            'Remaining'   => money(max(0, $remaining)),
        ])
        . email_button(url('agent/earnings.php'), 'View my earnings');
    brevo_send($agent['email'], $agent['full_name'], 'Referral bonus payment: ' . money($payout['amount']), email_layout('Bonus payment recorded', $body));
}

function invoice_email_html(array $inv, array $items, array $client): string
{
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:8px;border-bottom:1px solid #eee">' . e($it['description']) . '<br><span style="color:#8a8580;font-size:12px">'
            . e(rtrim(rtrim($it['qty'], '0'), '.')) . ' × ' . money($it['unit_price']) . '</span></td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . money($it['line_total']) . '</td></tr>';
    }
    $totals = '<tr><td style="padding:6px 8px;text-align:right;color:#8a8580">Subtotal</td><td style="padding:6px 8px;text-align:right">' . money($inv['subtotal']) . '</td></tr>';
    if ((float)$inv['discount'] > 0) $totals .= '<tr><td style="padding:6px 8px;text-align:right;color:#8a8580">Discount</td><td style="padding:6px 8px;text-align:right">−' . money($inv['discount']) . '</td></tr>';
    if ((float)$inv['tax_amount'] > 0) $totals .= '<tr><td style="padding:6px 8px;text-align:right;color:#8a8580">Tax (' . e(rtrim(rtrim($inv['tax_rate'], '0'), '.')) . '%)</td><td style="padding:6px 8px;text-align:right">' . money($inv['tax_amount']) . '</td></tr>';
    $totals .= '<tr><td style="padding:8px;text-align:right;font-weight:bold">Total</td><td style="padding:8px;text-align:right;font-weight:bold">' . money($inv['total']) . '</td></tr>'
        . '<tr><td style="padding:6px 8px;text-align:right;color:#8a8580">Paid</td><td style="padding:6px 8px;text-align:right">' . money($inv['amount_paid']) . '</td></tr>'
        . '<tr><td style="padding:8px;text-align:right;font-weight:bold;color:#b8975a">Balance due</td><td style="padding:8px;text-align:right;font-weight:bold;color:#b8975a">' . money(max(0, $inv['total'] - $inv['amount_paid'])) . '</td></tr>';

    return '<p>Dear ' . e($client['full_name']) . ',</p><p>Thank you for choosing Dignity Jewels. Please find your invoice details below.</p>'
        . email_kv(['Invoice #' => $inv['invoice_no'], 'Date' => fdate($inv['invoice_date']), 'Status' => ucfirst($inv['status'])])
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;margin-top:8px">'
        . '<tr><th style="text-align:left;padding:8px;border-bottom:2px solid #1c1c1c">Item</th><th style="text-align:right;padding:8px;border-bottom:2px solid #1c1c1c">Amount</th></tr>'
        . $rows . $totals . '</table>'
        . ($inv['notes'] ? '<p style="margin-top:18px;font-size:13px;color:#555">' . nl2br(e($inv['notes'])) . '</p>' : '');
}
