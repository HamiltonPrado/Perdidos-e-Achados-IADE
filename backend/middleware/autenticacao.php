<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/sessao.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../models/User.php';

function exigirAutenticacao(PDO $pdo): array
{
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

    return [
        'id' => (int) $utilizador['id'],
        'nome' => $utilizador['nome'],
        'email' => $utilizador['email'],
        'role' => $utilizador['role'],
    ];
}