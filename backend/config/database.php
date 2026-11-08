<?php

declare(strict_types=1);

// Em Docker as credenciais chegam por variáveis de ambiente (docker-compose.yml).
// Fora do Docker (MAMP, php -S) usa o credenciais.php local, como até agora.
//
// DB_HOST pode ter várias bases de dados separadas por vírgulas (ex.: "galera1,galera2,galera3"),
// para tolerância a falhas. Liga-se à primeira que:
//  - responde (uma BD em baixo é saltada ao fim de 2 s);
//  - num cluster Galera, está sincronizada (wsrep_ready = ON): um nó isolado da maioria
//    aceita ligações mas recusa consultas, por isso é saltado;
//  - aceita escritas (read_only = 0).
// Como todos tentam pela mesma ordem, as escritas vão sempre para o mesmo nó enquanto ele
// estiver bem, o que evita conflitos entre nós. Se nenhuma aceitar escritas, usa a primeira
// que responder, em modo só-leitura: as escritas falham com erro e o site responde 503.
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

        // Só existe em MariaDB Galera; num MySQL normal a consulta não devolve linhas.
        $galera = $pdo->query("SHOW GLOBAL STATUS LIKE 'wsrep_ready'")->fetch();
        if ($galera !== false && strtoupper((string) $galera['Value']) !== 'ON') {
            continue;
        }

        $soLeituraNo = (int) $pdo->query('SELECT @@GLOBAL.read_only')->fetchColumn() !== 0;
    } catch (PDOException $erro) {
        $ultimoErro = $erro;
        continue;
    }

    if (!$soLeituraNo) {
        return $pdo;
    }

    $soLeitura ??= $pdo;
}

if ($soLeitura !== null) {
    return $soLeitura;
}

throw $ultimoErro ?? new PDOException('Nenhuma base de dados sincronizada disponível.');
