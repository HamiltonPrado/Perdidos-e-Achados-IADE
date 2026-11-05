<?php

declare(strict_types=1);

class IaJob
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Agenda a análise de uma versão do artigo.
    // Deve ser chamado na mesma transação que guarda o artigo.
    public function agendar(int $itemId, int $versao): void
    {
        $consulta = $this->pdo->prepare(
            "INSERT INTO ia_jobs (
                item_id,
                versao_item,
                estado,
                tentativas,
                disponivel_em
            ) VALUES (
                :item_id,
                :versao_insert,
                'pendente',
                0,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                versao_item = :versao_update,
                estado = 'pendente',
                tentativas = 0,
                disponivel_em = CURRENT_TIMESTAMP,
                reservado_ate = NULL,
                token_reserva = NULL,
                ultimo_erro = NULL"
        );

        $consulta->execute([
            'item_id' => $itemId,
            'versao_insert' => $versao,
            'versao_update' => $versao,
        ]);
    }
}