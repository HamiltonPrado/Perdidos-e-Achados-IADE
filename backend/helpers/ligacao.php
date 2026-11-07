<?php

declare(strict_types=1);

// Abre a ligação à base de dados através do PDO.
// Se este pedido já abriu uma ligação, usa a mesma.
function obterLigacao(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = require __DIR__ . '/../config/database.php';
    }

    return $pdo;
}
