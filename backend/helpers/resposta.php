<?php

declare(strict_types=1);

function responderJson(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($dados, JSON_UNESCAPED_UNICODE);

    exit;
}