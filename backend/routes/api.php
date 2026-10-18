<?php

declare(strict_types=1);

// Verifica se o backend PHP está a responder.
if ($metodo === 'GET' && $caminho === '/api/health') {
    responderJson([
        'estado' => 'ok',
        'mensagem' => 'O backend está a funcionar.'
    ]);
}

// Encaminha a validação das credenciais e a criação da sessão para o controller.
if ($metodo === 'POST' && $caminho === '/api/login') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../controllers/AuthController.php';

        $controller = new AuthController($pdo);

        $controller->login();

    } catch (PDOException $erro) {
        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível consultar a base de dados.'
        ], 503);
    }
}

// Testa a ligação do backend à base de dados.
if ($metodo === 'GET' && $caminho === '/api/health/database') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        $pdo->query('SELECT 1')->fetchColumn();

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Ligação à base de dados estabelecida.'
        ]);

    } catch (PDOException $erro) {
        error_log($erro->getMessage());

        responderJson([
            'estado' => 'erro',
            'mensagem' => 'Não foi possível ligar à base de dados.'
        ], 503);
    }
}

// Devolve os dados do utilizador autenticado, verificando se a conta está ativa.
if ($metodo === 'GET' && $caminho === '/api/me') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../middleware/autenticacao.php';

        $utilizador = exigirAutenticacao($pdo);

        responderJson([
            'estado' => 'ok',
            'utilizador' => $utilizador
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

// Valida o token CSRF, elimina a sessão e remove o cookie.
if ($metodo === 'POST' && $caminho === '/api/logout') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../helpers/sessao.php';

        iniciarSessao($pdo);

        // Confirma que existe um utilizador autenticado.
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) || $userId <= 0) {
            session_abort();

            responderJson([
                'erro' => 'Não existe uma sessão autenticada.'
            ], 401);
        }

        // Compara o token recebido com o token guardado na sessão.
        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        $tokenPedido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (
            !is_string($tokenSessao)
            || $tokenSessao === ''
            || !hash_equals($tokenSessao, $tokenPedido)
        ) {
            session_abort();

            responderJson([
                'erro' => 'Token CSRF inválido.'
            ], 403);
        }

        // Guarda os parâmetros necessários para remover o cookie.
        $parametros = session_get_cookie_params();
        $nomeCookie = session_name();

        // Limpa os dados em memória e elimina a sessão da base de dados.
        $_SESSION = [];

        if (!session_destroy()) {
            throw new RuntimeException(
                'Não foi possível eliminar a sessão.'
            );
        }

        // Define uma data de expiração passada para remover o cookie.
        $cookieRemovido = setcookie($nomeCookie, '', [
            'expires' => time() - 3600,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'],
        ]);

        if (!$cookieRemovido) {
            throw new RuntimeException(
                'Não foi possível remover o cookie.'
            );
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Sessão terminada.'
        ]);

    } catch (Throwable $erro) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível terminar a sessão.'
        ], 503);
    }
}

// Testa o acesso a uma operação reservada à equipa administradora.
if ($metodo === 'GET' && $caminho === '/api/admin/teste') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../middleware/administrador.php';

        $utilizador = exigirAdministrador($pdo);

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Acesso da equipa autorizado.',
            'utilizador' => $utilizador
        ]);

    } catch (Throwable $erro) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível verificar as permissões.'
        ], 503);
    }
}

// Devolve as categorias dos artigos por ordem alfabética.
if ($metodo === 'GET' && $caminho === '/api/categories') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../controllers/ReferenciaController.php';

        $controller = new ReferenciaController($pdo);

        $controller->listarCategorias();

    } catch (PDOException $erro) {
        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível obter as categorias.'
        ], 503);
    }
}

// Devolve os locais da ocorrência por ordem alfabética.
if ($metodo === 'GET' && $caminho === '/api/locations') {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        require_once __DIR__ . '/../controllers/ReferenciaController.php';

        $controller = new ReferenciaController($pdo);

        $controller->listarLocais();

    } catch (PDOException $erro) {
        error_log($erro->getMessage());

        responderJson([
            'erro' => 'Não foi possível obter os locais.'
        ], 503);
    }
}

// Responde com erro 404 quando nenhuma rota corresponde ao pedido.
responderJson([
    'erro' => 'Rota não encontrada.'
], 404);