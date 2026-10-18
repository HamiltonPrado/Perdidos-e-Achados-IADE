<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

$caminho = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

require __DIR__ . '/../routes/api.php';