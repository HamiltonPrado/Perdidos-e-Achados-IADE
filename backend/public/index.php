<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

$caminho = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

try {
    require_once __DIR__ . '/../helpers/ligacao.php';

    require __DIR__ . '/../routes/api.php';

} catch (Throwable $erro) {
    // Cancela alterações pendentes na sessão se o pedido falhar.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort();
    }

    // Guarda o detalhe no terminal/log do servidor.
    error_log($erro->getMessage());

    // Envia uma resposta comum, sem expor detalhes internos.
    if ($erro instanceof PDOException) {
        responderJson([
            'erro' => 'Não foi possível aceder à base de dados.'
        ], 503);
    }

    responderJson([
        'erro' => 'Erro interno do servidor.'
    ], 500);
}