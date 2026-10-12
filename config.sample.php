<?php
// SAMPLE CONFIG — copy this file to config.php and fill in the real values.
// config.php is NOT stored in git (it contains passwords and API keys).
/**
 * Dignity Jewels — Affiliate Program
 * Main configuration. Edit the values below for each environment.
 *
 * The app picks the "local" block when running on localhost (XAMPP),
 * otherwise the "production" block (Cloudways).
 */

$isLocal = in_array(strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]), ['localhost', '127.0.0.1'], true)
    || PHP_SAPI === 'cli';

$ENV = $isLocal ? [
    // ---------- LOCAL (XAMPP) ----------
    'APP_URL'   => '',            // leave empty to auto-detect (e.g. http://localhost/dignity_affiliate)
    'DB_HOST'   => '127.0.0.1',
    'DB_PORT'   => 3306,
    'DB_NAME'   => 'dignity_affiliate',
    'DB_USER'   => 'root',
    'DB_PASS'   => '',
    'DEBUG'     => true,
] : [
    // ---------- PRODUCTION (Cloudways) ----------
    'APP_URL'   => 'https://affiliate.dignityjewelry.com',
    'DB_HOST'   => 'localhost',
    'DB_PORT'   => 3306,
    'DB_NAME'   => 'CHANGE_ME',
    'DB_USER'   => 'CHANGE_ME',
    'DB_PASS'   => 'CHANGE_ME',
    'DEBUG'     => false,
];

return array_merge([
    'APP_NAME'  => 'Dignity Jewels Affiliate',
    'TIMEZONE'  => 'America/Los_Angeles',

    // ---------- Brevo (transactional email) ----------
    // Create an API key in Brevo → SMTP & API → API Keys.
    // The sender email must be a verified sender/domain in Brevo.
    'BREVO_API_KEY'      => 'CHANGE_ME',
    'BREVO_SENDER_EMAIL' => 'sales@dignityjewelry.com',
    'BREVO_SENDER_NAME'  => 'Dignity Jewels',
], $ENV);
