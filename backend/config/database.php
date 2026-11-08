<?php

declare(strict_types=1);

// Em Docker as credenciais chegam por variáveis de ambiente (docker-compose.yml).
// Fora do Docker (MAMP, php -S) usa o credenciais.php local, como até agora.
//
// DB_HOST pode ter várias bases de dados separadas por vírgulas (ex.: "db1,db2"), para
// tolerância a falhas: liga-se à primeira que ACEITA ESCRITAS (a principal). Se nenhuma
// aceitar (a principal caiu e a réplica ainda não foi promovida), usa a primeira que
// responder, em modo só-leitura: as escritas falham com erro e o site responde 503.
$config = getenv('DB_HOST') !== false
    ? [
        'host' => getenv('DB_HOST'),
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'perdidos_achados',
        'username' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ]
    : require __DIR__ . '/credenciais.php';

$hosts = array_values(array_filter(array_map('trim', explode(',', (string) $config['host']))));

$opcoes = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    // Uma BD em baixo não pode prender o pedido: desiste ao fim de 2 s e tenta a seguinte.
    PDO::ATTR_TIMEOUT => 2,
];

$ligar = static fn (string $host): PDO => new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        $config['port'],
        $config['database']
    ),
    $config['username'],
    $config['password'],
    $opcoes
);

// Uma só BD (MAMP, ou Docker com um só nó): liga-se diretamente, como antes.
if (count($hosts) === 1) {
    return $ligar($hosts[0]);
}

$soLeitura = null;
$ultimoErro = null;

foreach ($hosts as $host) {
    try {
        $pdo = $ligar($host);
    } catch (PDOException $erro) {
        $ultimoErro = $erro;
        continue;
    }

    if ((int) $pdo->query('SELECT @@GLOBAL.read_only')->fetchColumn() === 0) {
        return $pdo;
    }

    $soLeitura ??= $pdo;
}

if ($soLeitura !== null) {
    return $soLeitura;
}

throw $ultimoErro;
