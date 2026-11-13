<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Local.php';
require_once __DIR__ . '/../models/PontoRecolha.php';
require_once __DIR__ . '/../helpers/resposta.php';

class ReferenciaController
{
    private Categoria $categorias;
    private Local $locais;
    private PontoRecolha $pontosRecolha;

    public function __construct(PDO $pdo)
    {
        $this->categorias = new Categoria($pdo);
        $this->locais = new Local($pdo);
        $this->pontosRecolha = new PontoRecolha($pdo);
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

    public function listarPontosRecolha(): void
    {
        $pontosRecolha = $this->pontosRecolha->listar();

        responderJson([
            'estado' => 'ok',
            'pontos_recolha' => $pontosRecolha
        ]);
    }
}
