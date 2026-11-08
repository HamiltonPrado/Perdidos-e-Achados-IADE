<?php

declare(strict_types=1);

// Credenciais da base de dados, partilhadas pela ligação (database.php) e pelo estado do
// cluster (EstadoBdController).
// Em Docker chegam por variáveis de ambiente (docker-compose.yml); fora do Docker (MAMP)
// vêm do credenciais.php local. `hosts` é a lista de nós de DB_HOST (separados por vírgulas).
$config = getenv('DB_HOST') !== false
    ? [
        'host' => getenv('DB_HOST'),
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'perdidos_achados',
        'username' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ]
    : require __DIR__ . '/credenciais.php';

$config['hosts'] = array_values(array_filter(array_map('trim', explode(',', (string) $config['host']))));

return $config;
