<?php

declare(strict_types=1);

class PedidoLevantamento
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Gera um código aleatório sem caracteres fáceis de confundir.
    public function gerarCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $ultimoIndice = strlen($alfabeto) - 1;
        $codigo = '';

        for ($i = 0; $i < 8; $i++) {
            $codigo .= $alfabeto[random_int(0, $ultimoIndice)];
        }

        return $codigo;
    }

    // Guarda um pedido com validade de 24 horas.
    public function criar(
        int $userId,
        int $itemAchadoId,
        string $codigo,
        ?int $itemPerdidoId = null,
        ?int $matchId = null
    ): int {
        $consulta = $this->pdo->prepare(
            "INSERT INTO pedidos_levantamento (
                user_id,
                item_achado_id,
                item_perdido_id,
                match_id,
                codigo,
                expira_em
            ) VALUES (
                :user_id,
                :item_achado_id,
                :item_perdido_id,
                :match_id,
                :codigo,
                DATE_ADD(NOW(), INTERVAL 24 HOUR)
            )"
        );

        $consulta->execute([
            'user_id' => $userId,
            'item_achado_id' => $itemAchadoId,
            'item_perdido_id' => $itemPerdidoId,
            'match_id' => $matchId,
            'codigo' => $codigo,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // Procura um pedido existente do aluno para o mesmo achado.
    public function procurarPorAlunoEAchado(
        int $userId,
        int $itemAchadoId
    ): ?array {
        $consulta = $this->pdo->prepare(
            'SELECT
                p.*,
                (p.expira_em <= NOW()) AS prazo_expirado
             FROM pedidos_levantamento AS p
             WHERE p.user_id = :user_id
               AND p.item_achado_id = :item_achado_id
             LIMIT 1'
        );

        $consulta->execute([
            'user_id' => $userId,
            'item_achado_id' => $itemAchadoId,
        ]);

        $pedido = $consulta->fetch();

        return $pedido === false ? null : $pedido;
    }

    // Procura um pedido pelo código de levantamento.
    public function procurarPorCodigo(string $codigo): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT
                p.*,
                (p.expira_em <= NOW()) AS prazo_expirado
             FROM pedidos_levantamento AS p
             WHERE p.codigo = :codigo
             LIMIT 1'
        );

        $consulta->execute([
            'codigo' => $codigo,
        ]);

        $pedido = $consulta->fetch();

        return $pedido === false ? null : $pedido;
    }

        // Valida e bloqueia a notificação e os artigos associados.
    // Deve ser chamado dentro de uma transação.
    public function procurarNotificacaoValida(
        int $notificacaoId,
        int $userId
    ): ?array {
        $consulta = $this->pdo->prepare(
            "SELECT
                m.id AS match_id,
                perdido.id AS item_perdido_id,
                achado.id AS item_achado_id,
                achado.ponto_recolha_id
             FROM notifications AS n
             INNER JOIN matches AS m
                ON m.id = n.match_id
                AND m.item_id = n.item_id
             INNER JOIN items AS perdido
                ON perdido.id = m.item_id
                AND perdido.user_id = n.user_id
             INNER JOIN items AS achado
                ON achado.id = m.matched_item_id
             WHERE n.id = :notificacao_id
               AND n.user_id = :user_id
               AND n.invalidada_em IS NULL
               AND m.estado = 'sugerido'
               AND perdido.tipo = 'lost'
               AND achado.tipo = 'found'
               AND perdido.estado = 'ativo'
               AND achado.estado = 'ativo'
               AND achado.ponto_recolha_id IS NOT NULL
               AND n.ponto_recolha_id = achado.ponto_recolha_id
               AND m.versao_perdido = perdido.versao
               AND m.versao_achado = achado.versao
             LIMIT 1
             FOR UPDATE"
        );

        $consulta->execute([
            'notificacao_id' => $notificacaoId,
            'user_id' => $userId,
        ]);

        $resultado = $consulta->fetch();

        return $resultado === false ? null : $resultado;
    }

       // Renova um código ainda não utilizado.
    public function renovar(
        int $id,
        string $codigo,
        int $reclamacaoId,
        ?int $itemPerdidoId = null,
        ?int $matchId = null
    ): bool {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'A renovação do código exige uma transação.'
            );
        }

        $consulta = $this->pdo->prepare(
            "UPDATE pedidos_levantamento
             SET codigo = :codigo,
                 reclamacao_id = :reclamacao_id,
                 item_perdido_id = :item_perdido_id,
                 match_id = :match_id,
                 estado = 'pendente',
                 expira_em = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                 utilizado_em = NULL
             WHERE id = :id
               AND estado IN ('pendente', 'expirado', 'cancelado')
               AND utilizado_em IS NULL"
        );

        $consulta->execute([
            'id' => $id,
            'codigo' => $codigo,
            'reclamacao_id' => $reclamacaoId,
            'item_perdido_id' => $itemPerdidoId,
            'match_id' => $matchId,
        ]);

        return $consulta->rowCount() === 1;
    }

          // Consulta um código associado a uma reclamação ainda válida.
    public function procurarValidoPorCodigo(
        string $codigo,
        bool $bloquear = false
    ): ?array {
        if ($bloquear && !$this->pdo->inTransaction()) {
            throw new LogicException(
                'O bloqueio exige uma transação ativa.'
            );
        }

        $sql = "
            SELECT
                p.id,
                p.user_id,
                p.item_achado_id,
                p.item_perdido_id,
                p.match_id,
                p.reclamacao_id,
                p.codigo,
                p.estado,
                p.expira_em,
                aluno.nome AS aluno_nome,
                aluno.email AS aluno_email,
                achado.titulo AS achado_titulo,
                perdido.titulo AS perdido_titulo,
                achado.ponto_recolha_id,
                ponto.nome AS ponto_recolha
            FROM pedidos_levantamento AS p
            INNER JOIN reclamacoes AS r
                ON r.id = p.reclamacao_id
                AND r.user_id = p.user_id
                AND r.item_achado_id = p.item_achado_id
                AND (
                    r.item_perdido_id <=> p.item_perdido_id
                )
                AND (
                    r.match_id <=> p.match_id
                )
            INNER JOIN users AS aluno
                ON aluno.id = p.user_id
            INNER JOIN items AS achado
                ON achado.id = p.item_achado_id
            INNER JOIN pontos_recolha AS ponto
                ON ponto.id = achado.ponto_recolha_id
            LEFT JOIN items AS perdido
                ON perdido.id = p.item_perdido_id
            LEFT JOIN matches AS m
                ON m.id = p.match_id
            WHERE p.codigo = :codigo
              AND p.estado = 'pendente'
              AND p.utilizado_em IS NULL
              AND p.expira_em > NOW()
              AND aluno.ativo = 1
              AND r.estado = 'aprovada'
              AND r.versao_item = achado.versao
              AND r.pergunta_secreta = achado.pergunta_secreta
              AND achado.tipo = 'found'
              AND achado.estado = 'ativo'
              AND achado.estado_recepcao = 'na_recepcao'
              AND (
                  p.item_perdido_id IS NULL
                  OR (
                      perdido.tipo = 'lost'
                      AND perdido.user_id = p.user_id
                      AND perdido.estado = 'ativo'
                  )
              )
              AND (
                  p.match_id IS NULL
                  OR (
                      m.item_id = p.item_perdido_id
                      AND m.matched_item_id = achado.id
                      AND m.estado = 'sugerido'
                      AND m.versao_perdido = perdido.versao
                      AND m.versao_achado = achado.versao
                  )
              )
            LIMIT 1
        ";

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'codigo' => $codigo,
        ]);

        $pedido = $consulta->fetch();

        return $pedido === false ? null : $pedido;
    }

        // Consome um código válido dentro da transação da entrega.
    public function marcarUtilizado(int $id): bool
    {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'A utilização do código exige uma transação ativa.'
            );
        }

        $consulta = $this->pdo->prepare(
            "UPDATE pedidos_levantamento
             SET estado = 'utilizado',
                 utilizado_em = NOW()
             WHERE id = :id
               AND estado = 'pendente'
               AND utilizado_em IS NULL
               AND expira_em > NOW()"
        );

        $consulta->execute([
            'id' => $id,
        ]);

        return $consulta->rowCount() === 1;
    }

    // Cancela outros pedidos relativos aos artigos já entregues.
        public function cancelarOutrosPedidos(
        int $achadoId,
        ?int $perdidoId,
        ?int $pedidoUtilizadoId = null
    ): void {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'O cancelamento exige uma transação ativa.'
            );
        }

        $consulta = $this->pdo->prepare(
            "UPDATE pedidos_levantamento
             SET estado = 'cancelado'
             WHERE id <> COALESCE(:pedido_utilizado_id, 0)
               AND estado = 'pendente'
               AND (
                   item_achado_id = :achado_id
                   OR item_perdido_id = :perdido_id
               )"
        );

        $consulta->execute([
            'pedido_utilizado_id' => $pedidoUtilizadoId,
            'achado_id' => $achadoId,
            'perdido_id' => $perdidoId,
        ]);
    }


}