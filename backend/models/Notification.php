<?php

declare(strict_types=1);

class Notification
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Lista as notificações válidas do utilizador autenticado.
    public function listarPorUtilizador(int $userId): array
    {
        $sql = "
            SELECT
                n.id,
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

        // Uma notificação já lida também representa sucesso.
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