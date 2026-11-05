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

    // Lista as devoluções, começando pelas mais recentes.
public function listarTodos(): array
{
    $consulta = $this->pdo->query(
        'SELECT
            d.id,
            d.match_id,
            d.item_achado_id,
            achado.titulo AS achado_titulo,
            d.item_perdido_id,
            perdido.titulo AS perdido_titulo,
            d.recebido_por,
            recebedor.nome AS recebido_por_nome,
            d.confirmado_por,
            administrador.nome AS confirmado_por_nome,
            d.ponto_recolha_id,
            ponto.nome AS ponto_recolha,
            d.devolvido_em
         FROM devolucoes AS d
         INNER JOIN items AS achado
            ON achado.id = d.item_achado_id
         LEFT JOIN items AS perdido
            ON perdido.id = d.item_perdido_id
         INNER JOIN users AS recebedor
            ON recebedor.id = d.recebido_por
         INNER JOIN users AS administrador
            ON administrador.id = d.confirmado_por
         INNER JOIN pontos_recolha AS ponto
            ON ponto.id = d.ponto_recolha_id
         ORDER BY d.devolvido_em DESC, d.id DESC'
    );

    return $consulta->fetchAll();
}

}