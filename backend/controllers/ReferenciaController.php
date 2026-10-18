<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Location.php';
require_once __DIR__ . '/../helpers/resposta.php';

class ReferenciaController
{
    private Category $categorias;
    private Location $locais;

    public function __construct(PDO $pdo)
    {
        $this->categorias = new Category($pdo);
        $this->locais = new Location($pdo);
    }

    public function listarCategorias(): void
    {
        $categorias = $this->categorias->listar();

        responderJson([
            'estado' => 'ok',
            'categorias' => $categorias
        ]);
    }

    public function listarLocais(): void
    {
        $locais = $this->locais->listar();

        responderJson([
            'estado' => 'ok',
            'locais' => $locais
        ]);
    }
}