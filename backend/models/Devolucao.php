<?php

declare(strict_types=1);

class Devolucao
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Regista uma entrega presencial confirmada pela equipa.
    public function criar(int $adminId, array $dados): int
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO devolucoes (
                match_id,
                item_perdido_id,
                item_achado_id,
                recebido_por,
                confirmado_por,
                ponto_recolha_id
            ) VALUES (
                :match_id,
                :item_perdido_id,
                :item_achado_id,
                :recebido_por,
                :confirmado_por,
                :ponto_recolha_id
            )'
        );

        $consulta->execute([
            'match_id' => $dados['match_id'] ?? null,
            'item_perdido_id' => $dados['item_perdido_id'] ?? null,
            'item_achado_id' => $dados['item_achado_id'],
            'recebido_por' => $dados['recebido_por'],
            'confirmado_por' => $adminId,
            'ponto_recolha_id' => $dados['ponto_recolha_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}