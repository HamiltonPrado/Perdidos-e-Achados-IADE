<?php

declare(strict_types=1);

class Category
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(): array
    {
        $consulta = $this->pdo->query(
            'SELECT id, nome
             FROM categories
             ORDER BY nome'
        );

        return $consulta->fetchAll();
    }
}