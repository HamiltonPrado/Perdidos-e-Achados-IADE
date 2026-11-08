<?php

declare(strict_types=1);

class Notification
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Lista as correspondências da IA ainda válidas do utilizador autenticado.
    public function listarPorUtilizador(int $userId): array
    {
        $sql = "
            SELECT
                n.id,
                n.tipo,
                n.item_id,
                perdido.titulo AS artigo_titulo,
                p.id AS ponto_recolha_id,
                p.nome AS ponto_recolha,
                n.lida,
                n.criado_em
            FROM notifications AS n
            INNER JOIN matches AS m
                ON m.id = n.match_id
                AND m.item_id = n.item_id
            INNER JOIN items AS perdido
                ON perdido.id = m.item_id
                AND perdido.user_id = n.user_id
            INNER JOIN items AS achado
                ON achado.id = m.matched_item_id
            INNER JOIN pontos_recolha AS p
                ON p.id = achado.ponto_recolha_id
            WHERE n.user_id = :user_id
              AND n.tipo = 'correspondencia'
              AND n.invalidada_em IS NULL
              AND m.estado = 'sugerido'
              AND perdido.tipo = 'lost'
              AND achado.tipo = 'found'
              AND perdido.estado = 'ativo'
              AND achado.estado = 'ativo'
              AND m.versao_perdido = perdido.versao
              AND m.versao_achado = achado.versao
              AND n.ponto_recolha_id = achado.ponto_recolha_id
            ORDER BY n.criado_em DESC, n.id DESC
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'user_id' => $userId
        ]);

        return $consulta->fetchAll();
    }

    // Lista as notificações das decisões da receção sobre as reclamações do utilizador,
    // incluindo as arquivadas (ficam no histórico, com `arquivada_em` preenchido).
    public function listarPedidosPorUtilizador(int $userId): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT
                n.id,
                n.tipo,
                n.reclamacao_id,
                r.item_achado_id,
                achado.titulo AS artigo_titulo,
                p.id AS ponto_recolha_id,
                p.nome AS ponto_recolha,
                r.estado AS estado_reclamacao,
                r.motivo_decisao,
                n.lida,
                n.arquivada_em,
                n.criado_em
             FROM notifications AS n
             INNER JOIN reclamacoes AS r
                ON r.id = n.reclamacao_id
                AND r.user_id = n.user_id
             INNER JOIN items AS achado
                ON achado.id = r.item_achado_id
             INNER JOIN pontos_recolha AS p
                ON p.id = n.ponto_recolha_id
             WHERE n.user_id = :user_id
               AND n.tipo IN ('pedido_aprovado', 'pedido_recusado', 'pedido_reaberto')
               AND n.invalidada_em IS NULL
             ORDER BY n.criado_em DESC, n.id DESC"
        );

        $consulta->execute([
            'user_id' => $userId,
        ]);

        return $consulta->fetchAll();
    }

    // Cria a notificação de uma decisão da receção (aprovação, recusa ou reabertura)
    // e arquiva a anterior da mesma reclamação. Chamar dentro da transação da decisão.
    public function criarDePedido(int $reclamacaoId, string $tipo): void
    {
        if (!in_array($tipo, ['pedido_aprovado', 'pedido_recusado', 'pedido_reaberto'], true)) {
            throw new InvalidArgumentException('Tipo de notificação inválido.');
        }

        $this->arquivarPorReclamacao($reclamacaoId);

        // O destinatário é o aluno da reclamação; o ponto de recolha é o do achado.
        $consulta = $this->pdo->prepare(
            'INSERT INTO notifications (tipo, reclamacao_id, user_id, ponto_recolha_id)
             SELECT :tipo, r.id, r.user_id, achado.ponto_recolha_id
             FROM reclamacoes AS r
             INNER JOIN items AS achado ON achado.id = r.item_achado_id
             WHERE r.id = :id
               AND achado.ponto_recolha_id IS NOT NULL'
        );

        $consulta->execute([
            'tipo' => $tipo,
            'id' => $reclamacaoId,
        ]);

        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException(
                'Não foi possível criar a notificação da reclamação ' . $reclamacaoId . '.'
            );
        }
    }

    // Arquiva as notificações ativas de uma reclamação (ex.: o aluno reenviou a resposta).
    public function arquivarPorReclamacao(int $reclamacaoId): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE notifications
             SET arquivada_em = CURRENT_TIMESTAMP
             WHERE reclamacao_id = :id
               AND arquivada_em IS NULL'
        );

        $consulta->execute([
            'id' => $reclamacaoId,
        ]);
    }

    // Arquiva as notificações de todas as reclamações de um achado entregue ao dono.
    public function arquivarPorAchado(int $achadoId): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE notifications AS n
             INNER JOIN reclamacoes AS r ON r.id = n.reclamacao_id
             SET n.arquivada_em = CURRENT_TIMESTAMP
             WHERE r.item_achado_id = :id
               AND n.arquivada_em IS NULL'
        );

        $consulta->execute([
            'id' => $achadoId,
        ]);
    }

    // Marca uma notificação do utilizador como lida.
    public function marcarLida(int $id, int $userId): bool
    {
        $consulta = $this->pdo->prepare(
            'UPDATE notifications
             SET lida = 1,
                 lida_em = COALESCE(lida_em, CURRENT_TIMESTAMP)
             WHERE id = :id
               AND user_id = :user_id
               AND invalidada_em IS NULL'
        );

        $consulta->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        if ($consulta->rowCount() > 0) {
            return true;
        }

        // Se a notificação já estava lida, continua a tratar o pedido como bem-sucedido.
        $consulta = $this->pdo->prepare(
            'SELECT id
             FROM notifications
             WHERE id = :id
               AND user_id = :user_id
               AND invalidada_em IS NULL'
        );

        $consulta->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        return $consulta->fetchColumn() !== false;
    }
}
