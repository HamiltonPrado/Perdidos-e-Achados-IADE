<?php

declare(strict_types=1);

require_once __DIR__ . '/item/ItemConsultas.php';

require_once __DIR__ . '/item/ItemRegistos.php';
require_once __DIR__ . '/item/ItemGestao.php';

class Item
{
    use ItemRegistos;
    use ItemGestao;

    use ItemConsultas;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


}