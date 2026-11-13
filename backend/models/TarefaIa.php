<?php

declare(strict_types=1);

class TarefaIa
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Coloca na fila um trabalho para a IA analisar esta versão do artigo.
    // Guarda o trabalho da IA e o artigo na mesma transação:
    // se uma das gravações falhar, nenhuma das duas fica guardada.
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
