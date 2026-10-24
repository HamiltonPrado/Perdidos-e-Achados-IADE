<?php

declare(strict_types=1);

class Correspondencia
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Consulta e bloqueia uma correspondência dentro de uma transação.
    public function procurarParaAtualizacao(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT
                id,
                item_id,
                matched_item_id,
                versao_perdido,
                versao_achado,
                estado
             FROM matches
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );

        $consulta->execute([
            'id' => $id
        ]);

        $correspondencia = $consulta->fetch();

        return $correspondencia === false
            ? null
            : $correspondencia;
    }

    // Conclui uma sugestão quando a entrega presencial é confirmada.
    public function concluir(int $id): bool
    {
        $consulta = $this->pdo->prepare(
            "UPDATE matches
             SET estado = 'concluido'
             WHERE id = :id
               AND estado = 'sugerido'"
        );

        $consulta->execute([
            'id' => $id
        ]);

        return $consulta->rowCount() === 1;
    }
}