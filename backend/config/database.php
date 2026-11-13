<?php

declare(strict_types=1);

// Credenciais: variáveis de ambiente (Docker) ou credenciais.php (MAMP).
// DB_HOST aceita vários nós ("galera1,galera2,galera3"): usa o primeiro que responde, está sincronizado e aceita escritas.
$config = require __DIR__ . '/configuracao_bd.php';
$hosts = $config['hosts'];

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

// Lembra o último nó que funcionou e tenta-o primeiro nos pedidos seguintes.
// Com um nó parado, o Docker demora mais de 3 s a dizer que o nome dele não existe
// (o ATTR_TIMEOUT não conta esse tempo); sem isto, todos os pedidos pagavam esse atraso.
$ficheiroUltimoNo = sys_get_temp_dir() . '/perdidos-achados-ultimo-no-bd';
$ultimoNo = @file_get_contents($ficheiroUltimoNo);
if (is_string($ultimoNo) && in_array($ultimoNo, $hosts, true)) {
    $hosts = array_values(array_unique([$ultimoNo, ...$hosts]));
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
        if ($host !== $ultimoNo) {
            @file_put_contents($ficheiroUltimoNo, $host, LOCK_EX);
        }
        return $pdo;
    }

    $soLeitura ??= $pdo;
}

if ($soLeitura !== null) {
    return $soLeitura;
}

throw $ultimoErro ?? new PDOException('Nenhuma base de dados sincronizada disponível.');
