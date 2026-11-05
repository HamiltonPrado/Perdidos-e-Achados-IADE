<?php

declare(strict_types=1);

require_once __DIR__ . '/reclamacoes/ReclamacaoConsultas.php';
require_once __DIR__ . '/reclamacoes/ReclamacaoGestao.php';

class Reclamacao
{
    use ReclamacaoConsultas;
    use ReclamacaoGestao;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Guarda a reclamação com a pergunta e versão obtidas do artigo.
    public function criar(
        int $userId,
        array $artigo,
        array $dados,
        ?int $matchId = null
    ): int {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'A criação da reclamação exige uma transação.'
            );
        }

        $sql = "
            INSERT INTO reclamacoes (
                user_id,
                item_achado_id,
                item_perdido_id,
                match_id,
                versao_item,
                pergunta_secreta,
                resposta,
                informacao_adicional
            )
            VALUES (
                :user_id,
                :item_achado_id,
                :item_perdido_id,
                :match_id,
                :versao_item,
                :pergunta_secreta,
                :resposta,
                :informacao_adicional
            )
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'user_id' => $userId,
            'item_achado_id' => (int) $artigo['id'],
            'item_perdido_id' => $dados['item_perdido_id'],
            'match_id' => $matchId,
            'versao_item' => (int) $artigo['versao'],
            'pergunta_secreta' => $artigo['pergunta_secreta'],
            'resposta' => $dados['resposta'],
            'informacao_adicional' => $dados['informacao_adicional'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }


}