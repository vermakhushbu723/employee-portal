<?php

/*
 * Only the parts that differ from Laravel's default mail config (the rest is merged in).
 *
 * MAIL_POOL lists sending mailboxes (comma separated) that share MAIL_POOL_PASSWORD.
 * Each mailbox becomes its own SMTP mailer that sends From itself (Hostinger rejects
 * mail whose From differs from the login). Daily reports rotate across them and fall
 * back to the next mailbox if one fails — see DailyReportService::send().
 */

$pool = array_values(array_filter(array_map('trim', explode(',', (string) env('MAIL_POOL', '')))));

$mailers = [];
foreach ($pool as $i => $address) {
    $mailers['pool'.($i + 1)] = [
        'transport' => 'smtp',
        'scheme' => env('MAIL_SCHEME', 'smtps'),
        'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
        'port' => (int) env('MAIL_PORT', 465),
        'username' => $address,
        'password' => env('MAIL_POOL_PASSWORD', env('MAIL_PASSWORD')),
        'timeout' => 20,
        'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        'from' => ['address' => $address, 'name' => env('MAIL_FROM_NAME', 'Employee Portal')],
    ];
}

return [
    'mailers' => $mailers,

    // Mailer names used in rotation (empty = use the default mailer)
    'pool' => array_keys($mailers),
];
