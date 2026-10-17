<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($metodo === 'GET' && $caminho === '/api/health') {
    echo json_encode([
        'estado' => 'ok',
        'mensagem' => 'O backend está a funcionar.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($metodo === 'GET' && $caminho === '/api/health/database') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        $pdo->query('SELECT 1')->fetchColumn();

        echo json_encode([
            'estado' => 'ok',
            'mensagem' => 'Ligação à base de dados estabelecida.'
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $erro) {
        error_log($erro->getMessage());

        http_response_code(503);

        echo json_encode([
            'estado' => 'erro',
            'mensagem' => 'Não foi possível ligar à base de dados.'
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}
http_response_code(404);

echo json_encode([
    'erro' => 'Rota não encontrada.'
], JSON_UNESCAPED_UNICODE);