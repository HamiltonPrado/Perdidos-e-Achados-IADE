<?php

declare(strict_types=1);

require_once __DIR__ . '/sessionhandler.php';

function iniciarSessao(PDO $pdo): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $handler = new DatabaseSessionHandler($pdo, 3600);

    session_name('IADESESSID');

    session_set_save_handler($handler, true);

    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    $iniciada = session_start([
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
        'use_cookies' => 1,
        'use_trans_sid' => 0,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
        'cookie_lifetime' => 0,
        'gc_maxlifetime' => 3600,
    ]);

    if (!$iniciada) {
        throw new RuntimeException('Não foi possível iniciar a sessão.');
    }
}