<?php

declare(strict_types=1);

require_once __DIR__ . '/autenticacao.php';

function exigirAdministrador(PDO $pdo): array
{
    $utilizador = exigirAutenticacao($pdo);

    if ($utilizador['role'] !== 'admin') {
        responderJson([
            'erro' => 'Não tens permissão para realizar esta operação.'
        ], 403);
    }

    return $utilizador;
}