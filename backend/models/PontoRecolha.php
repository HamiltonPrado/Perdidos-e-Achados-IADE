<?php

declare(strict_types=1);

class PontoRecolha
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Devolve os pontos de recolha por ordem alfabética.
    public function listar(): array
    {
        $consulta = $this->pdo->query(
            'SELECT id, nome
             FROM pontos_recolha
             ORDER BY nome'
        );

        return $consulta->fetchAll();
    }
}
