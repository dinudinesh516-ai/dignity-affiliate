<?php
/**
 * Core bootstrap: config, session, DB, helpers, auth, CSRF.
 */

define('APP_ROOT', dirname(__DIR__));

$CONFIG = require APP_ROOT . '/config.php';
date_default_timezone_set($CONFIG['TIMEZONE']);

if (!empty($CONFIG['DEBUG'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// ---------- URL helpers ----------
function app_url(): string
{
    global $CONFIG;
    static $base = null;
    if ($base !== null) return $base;
    if (!empty($CONFIG['APP_URL'])) {
        return $base = rtrim($CONFIG['APP_URL'], '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Directory of the app relative to document root
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $path = '';
    if ($docRoot && strpos($appRoot, $docRoot) === 0) {
        $path = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }
    return $base = ($https ? 'https' : 'http') . '://' . $host . rtrim($path, '/');
}

function url(string $path = ''): string
{
    return app_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

// ---------- Session ----------
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('DJAFFSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- Database ----------
function db(): PDO
{
    global $CONFIG;
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $CONFIG['DB_HOST'], (int)$CONFIG['DB_PORT'], $CONFIG['DB_NAME']);
    try {
        $pdo = new PDO($dsn, $CONFIG['DB_USER'], $CONFIG['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep DB timestamps in the app's timezone
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (PDOException $e) {
        http_response_code(500);
        if (!empty($CONFIG['DEBUG'])) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        die('Database connection failed. Please check config.php.');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

// ---------- Settings ----------
function setting(string $name, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (rows('SELECT name, value FROM settings') as $r) $cache[$r['name']] = $r['value'];
        } catch (Throwable $e) { /* table may not exist before install */ }
    }
    return array_key_exists($name, $cache) ? $cache[$name] : $default;
}

function save_setting(string $name, $value): void
{
    q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, (string)$value]);
}

// ---------- Output helpers ----------
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    return setting('currency_symbol', '$') . number_format((float)$amount, 2);
}

function fdate(?string $d, string $fmt = 'M j, Y'): string
{
    if (!$d || strpos($d, '0000') === 0) return '—';
    return date($fmt, strtotime($d));
}

function fdatetime(?string $d): string
{
    return fdate($d, 'M j, Y g:i A');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $i = strtoupper(substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
    return $i ?: '?';
}

function pct(float $part, float $whole): int
{
    if ($whole <= 0) return 0;
    return (int)max(0, min(100, round($part / $whole * 100)));
}

function mask_phone(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone);
    if (strlen($d) < 4) return $phone;
    return str_repeat('•', max(0, strlen($d) - 4)) . substr($d, -4);
}

function status_badge(string $status): string
{
    $map = [
        'new' => ['New', 'info'], 'contacted' => ['Contacted', 'primary'], 'negotiating' => ['Negotiating', 'warning'],
        'won' => ['Won', 'success'], 'lost' => ['Lost', 'secondary'],
        'pending' => ['Pending', 'warning'], 'partial' => ['Partially Paid', 'info'], 'paid' => ['Paid', 'success'],
        'cancelled' => ['Cancelled', 'secondary'], 'unpaid' => ['Unpaid', 'danger'],
        'active' => ['Active', 'success'], 'disabled' => ['Disabled', 'secondary'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'secondary'];
    return '<span class="badge badge-soft-' . $cls . '">' . e($label) . '</span>';
}

// ---------- Phone / WhatsApp ----------
function phone_digits(string $phone): string
{
    return preg_replace('/\D/', '', $phone);
}

/** Key used for duplicate detection: full international number, digits only (e.g. 12135550101). */
function phone_key(string $phone): string
{
    return whatsapp_number($phone);
}

/** Digits with country code for wa.me links. */
function whatsapp_number(string $phone): string
{
    $raw = trim($phone);
    $d = phone_digits($raw);
    if (strpos($raw, '+') === 0) return $d;
    if (strpos($d, '00') === 0) return substr($d, 2);
    $cc = preg_replace('/\D/', '', (string)setting('default_country_code', '1'));
    if (strlen($d) <= 10) return $cc . $d;
    return $d;
}

function whatsapp_link(string $phone, string $text = ''): string
{
    return 'https://wa.me/' . whatsapp_number($phone) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

// ---------- Flash messages ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Old input ----------
function old(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        flash('danger', 'Your session expired. Please try again.');
        $back = $_SERVER['HTTP_REFERER'] ?? url();
        header('Location: ' . $back);
        exit;
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------- Auth ----------
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $user = null;
    $u = row('SELECT * FROM users WHERE id = ? AND status = "active"', [$id]);
    if (!$u) { unset($_SESSION['uid']); return $user = null; }
    return $user = $u;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_role(string $role): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
    if ($u['role'] !== $role) {
        redirect($u['role'] === 'admin' ? 'admin/' : 'agent/');
    }
    return $u;
}

function require_admin(): array { return require_role('admin'); }
function require_agent(): array { return require_role('agent'); }

function home_for(array $u): string
{
    return $u['role'] === 'admin' ? 'admin/' : 'agent/';
}

// ---------- Rate limiting (DB based) ----------
function too_many_attempts(string $bucket, int $max, int $minutes): bool
{
    q('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    $n = (int)val('SELECT COUNT(*) FROM login_attempts WHERE bucket = ? AND created_at > (NOW() - INTERVAL ' . (int)$minutes . ' MINUTE)', [$bucket]);
    return $n >= $max;
}

function hit_attempt(string $bucket): void
{
    q('INSERT INTO login_attempts (bucket) VALUES (?)', [$bucket]);
}

function clear_attempts(string $bucket): void
{
    q('DELETE FROM login_attempts WHERE bucket = ?', [$bucket]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---------- Password tokens ----------
function create_password_token(int $userId, string $purpose = 'reset'): string
{
    $token = bin2hex(random_bytes(32));
    $hours = $purpose === 'setup' ? 24 * 7 : 1;
    q('UPDATE password_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
    q('INSERT INTO password_tokens (user_id, token_hash, purpose, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . $hours . ' HOUR))',
        [$userId, hash('sha256', $token), $purpose]);
    return $token;
}

function find_password_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return row('SELECT t.*, u.email, u.full_name, u.username, u.role FROM password_tokens t JOIN users u ON u.id = t.user_id
                WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.status = "active"',
        [hash('sha256', $token)]);
}

function validate_password(string $pw, string $confirm): ?string
{
    if (strlen($pw) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Password must contain letters and numbers.';
    if ($pw !== $confirm) return 'Passwords do not match.';
    return null;
}

// ---------- Agents ----------
function generate_referral_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = 'DJ';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (val('SELECT id FROM users WHERE referral_code = ?', [$code]));
    return $code;
}

function referral_link(string $code): string
{
    return url('r/' . rawurlencode($code));
}

function qr_image_url(string $code, bool $download = false): string
{
    return url('qr.php?c=' . rawurlencode($code) . ($download ? '&dl=1' : ''));
}

function agent_rate(array $agent): float
{
    return $agent['commission_rate'] !== null && $agent['commission_rate'] !== ''
        ? (float)$agent['commission_rate']
        : (float)setting('commission_rate', '5');
}

function username_from_email(string $email): string
{
    $base = strtolower(preg_replace('/[^a-z0-9._]/i', '', explode('@', $email)[0])) ?: 'agent';
    $base = substr($base, 0, 50);
    $u = $base; $i = 1;
    while (val('SELECT id FROM users WHERE username = ?', [$u])) { $u = $base . $i++; }
    return $u;
}

function valid_username(string $u): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $u);
}

// ---------- Pagination ----------
function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function pager_links(array $p): string
{
    if ($p['pages'] <= 1) return '';
    $qs = $_GET;
    $html = '<nav class="d-flex justify-content-center my-3"><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 7 && abs($i - $p['page']) > 2 && $i !== 1 && $i !== $p['pages']) {
            if ($i === 2 || $i === $p['pages'] - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
        $qs['page'] = $i;
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($qs)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/business.php';
require_once __DIR__ . '/phone.php';

if (is_post()) phone_merge_post();
if (PHP_SAPI !== 'cli' && !defined('INSTALLING')) { phone_upgrade_keys(); app_schema_upgrade(); }
