<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

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

if ($metodo === 'POST' && $caminho === '/api/login') {
    $tipoConteudo = strtolower(
        trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
    );

    if ($tipoConteudo !== 'application/json') {
        responderJson([
            'erro' => 'O pedido deve usar Content-Type: application/json.'
        ], 415);
    }

    try {
        $dados = json_decode(
            file_get_contents('php://input'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $erro) {
        responderJson([
            'erro' => 'O JSON enviado é inválido.'
        ], 400);
    }

    if (
        !is_array($dados)
        || !isset($dados['email'], $dados['password'])
        || !is_string($dados['email'])
        || !is_string($dados['password'])
    ) {
        responderJson([
            'erro' => 'Envia o email e a password como texto.'
        ], 422);
    }

    $email = trim($dados['email']);
    $password = $dados['password'];

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150
        || $password === ''
        || strlen($password) > 4096
    ) {
        responderJson([
            'erro' => 'Preenche um email válido e a password.'
        ], 422);
    }

    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../controllers/AuthController.php';

        $controller = new AuthController($pdo);

        $utilizador = $controller->autenticar($email, $password);

    } catch (PDOException $erro) {
        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível consultar a base de dados.'
        ], 503);
    }

    if ($utilizador === null) {
        responderJson([
            'erro' => 'Email ou password incorretos.'
        ], 401);
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Credenciais válidas.',
        'utilizador' => $utilizador
    ]);
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