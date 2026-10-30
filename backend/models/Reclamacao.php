<?php

declare(strict_types=1);

class Reclamacao
{
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

    // Procura uma reclamação do próprio aluno para o achado indicado.
    public function procurarPorAlunoEAchado(
        int $userId,
        int $achadoId
    ): ?array {
        $consulta = $this->pdo->prepare(
            'SELECT *
             FROM reclamacoes
             WHERE user_id = :user_id
               AND item_achado_id = :item_achado_id
             LIMIT 1'
        );

        $consulta->execute([
            'user_id' => $userId,
            'item_achado_id' => $achadoId,
        ]);

        $reclamacao = $consulta->fetch();

        return $reclamacao === false ? null : $reclamacao;
    }

        // Obtém os dados necessários à análise pela equipa.
    public function listarParaEquipa(): array
    {
        $sql = '
            SELECT
                r.id,
                r.user_id,
                u.nome AS aluno_nome,
                u.email AS aluno_email,
                r.item_achado_id,
                i.titulo AS achado_titulo,
                i.estado AS estado_artigo,
                i.estado_recepcao,
                p.nome AS ponto_recolha,
                r.item_perdido_id,
                r.match_id,
                r.versao_item,
                i.versao AS versao_atual,
                r.pergunta_secreta,
                i.pergunta_secreta AS pergunta_atual,
                r.resposta,
                i.resposta_esperada,
                r.informacao_adicional,
                r.estado,
                r.decidido_por,
                r.motivo_decisao,
                r.decidido_em,
                r.criado_em,
                r.atualizado_em
            FROM reclamacoes AS r
            INNER JOIN users AS u ON u.id = r.user_id
            INNER JOIN items AS i ON i.id = r.item_achado_id
            LEFT JOIN pontos_recolha AS p
                ON p.id = i.ponto_recolha_id
            ORDER BY r.criado_em DESC, r.id DESC
        ';

        $consulta = $this->pdo->query($sql);

        return $consulta->fetchAll();
    }

        public function procurarPorId(
        int $id,
        bool $bloquear = false
    ): ?array {
        if ($bloquear && !$this->pdo->inTransaction()) {
            throw new LogicException(
                'O bloqueio da reclamação exige uma transação.'
            );
        }

        $sql = '
            SELECT *
            FROM reclamacoes
            WHERE id = :id
            LIMIT 1
        ';

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute(['id' => $id]);

        $reclamacao = $consulta->fetch();

        return $reclamacao === false ? null : $reclamacao;
    }

        public function existeOutraAprovada(
        int $achadoId,
        int $reclamacaoId
    ): bool {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'A verificação da aprovação exige uma transação.'
            );
        }

        $consulta = $this->pdo->prepare(
            "SELECT id
             FROM reclamacoes
             WHERE item_achado_id = :achado_id
               AND id <> :reclamacao_id
               AND estado = 'aprovada'
             LIMIT 1
             FOR UPDATE"
        );

        $consulta->execute([
            'achado_id' => $achadoId,
            'reclamacao_id' => $reclamacaoId,
        ]);

        return $consulta->fetchColumn() !== false;
    }

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

    // Lista apenas as reclamações do aluno indicado.
public function listarPorUtilizador(int $userId): array
{
    $sql = '
        SELECT
            r.id,
            r.item_achado_id,
            achado.titulo AS achado_titulo,
            achado.estado AS estado_artigo,
            achado.estado_recepcao,
            p.nome AS ponto_recolha,
            r.item_perdido_id,
            perdido.titulo AS perdido_titulo,
            r.pergunta_secreta,
            r.resposta,
            r.informacao_adicional,
            r.estado,
            r.motivo_decisao,
            r.decidido_em,
            r.criado_em,
            r.atualizado_em
        FROM reclamacoes AS r
        INNER JOIN items AS achado
            ON achado.id = r.item_achado_id
        LEFT JOIN items AS perdido
            ON perdido.id = r.item_perdido_id
        LEFT JOIN pontos_recolha AS p
            ON p.id = achado.ponto_recolha_id
        WHERE r.user_id = :user_id
        ORDER BY r.criado_em DESC, r.id DESC
    ';

    $consulta = $this->pdo->prepare($sql);

    $consulta->execute([
        'user_id' => $userId,
    ]);

    return $consulta->fetchAll();
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


}