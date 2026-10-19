<?php

declare(strict_types=1);

// Obtém a ligação PDO, reutilizando-a durante o mesmo pedido.
function obterLigacao(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = require __DIR__ . '/../config/database.php';
    }

    return $pdo;
}