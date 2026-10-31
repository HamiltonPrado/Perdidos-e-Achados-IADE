<?php

declare(strict_types=1);

trait ReclamacaoGestao
{

        public function decidir(
        int $id,
        int $adminId,
        array $dados
    ): bool {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'A decisão exige uma transação.'
            );
        }

        if (
            !in_array(
                $dados['estado'],
                ['aprovada', 'recusada'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Estado de decisão inválido.'
            );
        }

        $consulta = $this->pdo->prepare(
            "UPDATE reclamacoes
             SET estado = :estado,
                 motivo_decisao = :motivo_decisao,
                 decidido_por = :decidido_por,
                 decidido_em = CURRENT_TIMESTAMP
             WHERE id = :id
               AND estado = 'pendente'"
        );

        $consulta->execute([
            'estado' => $dados['estado'],
            'motivo_decisao' => $dados['motivo_decisao'],
            'decidido_por' => $adminId,
            'id' => $id,
        ]);

        return $consulta->rowCount() === 1;
    }

// Permite responder novamente quando a pergunta do objeto mudou.
public function reenviarDesatualizada(
    int $id,
    int $userId,
    array $achado,
    array $dados
): bool {
    if (!$this->pdo->inTransaction()) {
        throw new LogicException(
            'O reenvio da reclamação exige uma transação.'
        );
    }

    // O controller já deve ter bloqueado o artigo.
    $reclamacao = $this->procurarPorId($id, true);

    if (
        $reclamacao === null
        || (int) $reclamacao['user_id'] !== $userId
        || (int) $reclamacao['item_achado_id'] !== (int) $achado['id']
        || !in_array(
    $reclamacao['estado'],
    ['pendente', 'aprovada', 'reaberta'],
    true
)
|| (
    $reclamacao['estado'] !== 'reaberta'
    && $reclamacao['pergunta_secreta'] === $achado['pergunta_secreta']
)
    ) {
        return false;
    }

    $consulta = $this->pdo->prepare(
        "UPDATE reclamacoes
         SET item_perdido_id = :item_perdido_id,
             match_id = NULL,
             versao_item = :versao_item,
             pergunta_secreta = :pergunta_secreta,
             resposta = :resposta,
             informacao_adicional = :informacao_adicional,
             estado = 'pendente',
             decidido_por = NULL,
             motivo_decisao = NULL,
             decidido_em = NULL
         WHERE id = :id"
    );

    $consulta->execute([
        'item_perdido_id' => $dados['item_perdido_id'],
        'versao_item' => (int) $achado['versao'],
        'pergunta_secreta' => $achado['pergunta_secreta'],
        'resposta' => $dados['resposta'],
        'informacao_adicional' => $dados['informacao_adicional'],
        'id' => $id,
    ]);

    // A nova resposta precisa de aprovação antes de gerar outro código.
    $cancelarCodigos = $this->pdo->prepare(
        "UPDATE pedidos_levantamento
         SET estado = 'cancelado'
         WHERE reclamacao_id = :reclamacao_id
           AND estado = 'pendente'"
    );

    $cancelarCodigos->execute([
        'reclamacao_id' => $id,
    ]);

    return true;
}

// Reabre uma reclamação recusada, por decisão da equipa.
public function reabrir(int $id, int $adminId): bool
{
    if (!$this->pdo->inTransaction()) {
        throw new LogicException(
            'A reabertura da reclamação exige uma transação.'
        );
    }

    $consulta = $this->pdo->prepare(
        "UPDATE reclamacoes
         SET estado = 'reaberta',
             reaberta_por = :reaberta_por,
             reaberta_em = CURRENT_TIMESTAMP
         WHERE id = :id
           AND estado = 'recusada'"
    );

    $consulta->execute([
        'reaberta_por' => $adminId,
        'id' => $id,
    ]);

    return $consulta->rowCount() === 1;
}

public function atualizarAposEdicaoDoAchado(
    int $achadoId,
    int $versaoAnterior,
    int $novaVersao,
    bool $perguntaAlterada
): array {
    if (!$this->pdo->inTransaction()) {
        throw new LogicException(
            'A atualização das reclamações exige uma transação.'
        );
    }

    if ($novaVersao <= $versaoAnterior) {
        throw new LogicException(
            'A nova versão deve ser superior à anterior.'
        );
    }

    // As aprovações anteriores precisam de nova análise da equipa.
    $reavaliar = $this->pdo->prepare(
        "UPDATE reclamacoes
         SET estado = 'pendente',
             decidido_por = NULL,
             motivo_decisao = NULL,
             decidido_em = NULL,
             match_id = NULL
         WHERE item_achado_id = :achado_id
           AND estado = 'aprovada'"
    );

    $reavaliar->execute([
        'achado_id' => $achadoId,
    ]);

    $aprovacoesRevogadas = $reavaliar->rowCount();

    // Com a pergunta igual, conserva a resposta e atualiza a versão.
    // A equipa poderá analisar essa resposta perante os novos dados.
    if (!$perguntaAlterada) {
        $atualizarVersao = $this->pdo->prepare(
            "UPDATE reclamacoes AS r
             INNER JOIN items AS i
                 ON i.id = r.item_achado_id
             SET r.versao_item = :nova_versao,
                 r.match_id = NULL
             WHERE r.item_achado_id = :achado_id
               AND r.estado IN ('pendente', 'reaberta')
               AND r.versao_item = :versao_anterior
               AND i.versao = :versao_atual
               AND BINARY r.pergunta_secreta
                   = BINARY i.pergunta_secreta"
        );

        $atualizarVersao->execute([
            'nova_versao' => $novaVersao,
            'achado_id' => $achadoId,
            'versao_anterior' => $versaoAnterior,
            'versao_atual' => $novaVersao,
        ]);
    }

    // Um código anterior exige uma nova aprovação para ser renovado.
    $cancelarCodigos = $this->pdo->prepare(
        "UPDATE pedidos_levantamento
         SET estado = 'cancelado'
         WHERE item_achado_id = :achado_id
           AND estado = 'pendente'"
    );

    $cancelarCodigos->execute([
        'achado_id' => $achadoId,
    ]);

    return [
        'aprovacoes_revogadas' => $aprovacoesRevogadas,
        'codigos_cancelados' => $cancelarCodigos->rowCount(),
    ];
}

}
