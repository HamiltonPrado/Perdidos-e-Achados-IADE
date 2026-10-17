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

 try {
    require_once __DIR__ . '/../helpers/sessao.php';

    iniciarSessao($pdo);

    if (!session_regenerate_id(true)) {
        throw new RuntimeException(
            'Não foi possível renovar o identificador da sessão.'
        );
    }

    $_SESSION = [
        'user_id' => $utilizador['id'],
        'csrf_token' => bin2hex(random_bytes(32)),
    ];

    $csrfToken = $_SESSION['csrf_token'];

    if (!session_write_close()) {
        throw new RuntimeException('Não foi possível guardar a sessão.');
    }

} catch (Throwable $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort();
    }

    error_log($erro->getMessage());

    responderJson([
        'erro' => 'Não foi possível criar a sessão.'
    ], 503);
}

responderJson([
    'estado' => 'ok',
    'mensagem' => 'Login efetuado.',
    'utilizador' => $utilizador,
    'csrf_token' => $csrfToken,
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
    
    if ($metodo === 'GET' && $caminho === '/api/me') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../helpers/sessao.php';
        require_once __DIR__ . '/../models/User.php';

        iniciarSessao($pdo);

        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) || $userId <= 0) {
            session_abort();

            responderJson([
                'erro' => 'Não existe uma sessão autenticada.'
            ], 401);
        }

        $model = new User($pdo);

        $utilizador = $model->procurarPorId($userId);

        if (
            $utilizador === null
            || (int) $utilizador['ativo'] !== 1
        ) {
            $_SESSION = [];
            session_destroy();

            responderJson([
                'erro' => 'A sessão deixou de ser válida.'
            ], 401);
        }

        if (!session_write_close()) {
            throw new RuntimeException(
                'Não foi possível atualizar a sessão.'
            );
        }

        responderJson([
            'estado' => 'ok',
            'utilizador' => [
                'id' => (int) $utilizador['id'],
                'nome' => $utilizador['nome'],
                'email' => $utilizador['email'],
                'role' => $utilizador['role'],
            ]
        ]);

    } catch (Throwable $erro) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível verificar a sessão.'
        ], 503);
    }
}

http_response_code(404);

echo json_encode([
    'erro' => 'Rota não encontrada.'
], JSON_UNESCAPED_UNICODE);