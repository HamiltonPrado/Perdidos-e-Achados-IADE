<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

// Confirma que o pedido apresenta o token CSRF da sessão.
function exigirCsrf(): void
{
    $tokenSessao = $_SESSION['csrf_token'] ?? '';
    $tokenPedido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (
        !is_string($tokenSessao)
        || $tokenSessao === ''
        || !hash_equals($tokenSessao, $tokenPedido)
    ) {
        responderJson([
            'erro' => 'Token CSRF inválido.'
        ], 403);
    }
}