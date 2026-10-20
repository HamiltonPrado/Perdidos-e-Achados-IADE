<?php

declare(strict_types=1);

class Item
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Guarda um objeto perdido associado ao utilizador autenticado.
    public function criarPerdido(int $userId, array $dados): int
    {
        $sql = "
            INSERT INTO items (
                user_id,
                tipo,
                titulo,
                descricao,
                category_id,
                location_id,
                ponto_recolha_id,
                data_ocorrencia
            )
            VALUES (
                :user_id,
                'lost',
                :titulo,
                :descricao,
                :category_id,
                :location_id,
                NULL,
                :data_ocorrencia
            )
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'user_id' => $userId,
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'],
            'category_id' => $dados['category_id'],
            'location_id' => $dados['location_id'],
            'data_ocorrencia' => $dados['data_ocorrencia'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

        // Lista todos os artigos para a gestão pela equipa.
    public function listarTodos(): array
    {
        $sql = '
            SELECT
                i.id,
                i.tipo,
                i.titulo,
                i.descricao,
                i.user_id,
                u.nome AS reportado_por,
                c.nome AS categoria,
                l.nome AS local,
                p.nome AS ponto_recolha,
                i.data_ocorrencia,
                i.estado,
                i.ia_estado,
                i.criado_em
            FROM items AS i
            INNER JOIN users AS u ON u.id = i.user_id
            INNER JOIN categories AS c ON c.id = i.category_id
            INNER JOIN locations AS l ON l.id = i.location_id
            LEFT JOIN pontos_recolha AS p ON p.id = i.ponto_recolha_id
            ORDER BY i.criado_em DESC, i.id DESC
        ';

        $consulta = $this->pdo->query($sql);

        return $consulta->fetchAll();
    }

        // Guarda um objeto achado registado por um membro da equipa.
    public function criarAchado(int $userId, array $dados): int
    {
        $sql = "
            INSERT INTO items (
                user_id,
                tipo,
                titulo,
                descricao,
                category_id,
                location_id,
                ponto_recolha_id,
                data_ocorrencia
            )
            VALUES (
                :user_id,
                'found',
                :titulo,
                :descricao,
                :category_id,
                :location_id,
                :ponto_recolha_id,
                :data_ocorrencia
            )
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'user_id' => $userId,
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'],
            'category_id' => $dados['category_id'],
            'location_id' => $dados['location_id'],
            'ponto_recolha_id' => $dados['ponto_recolha_id'],
            'data_ocorrencia' => $dados['data_ocorrencia'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

        // Procura um artigo pelo identificador.
    public function procurarPorId(int $id): ?array
    {
        $sql = '
            SELECT
                i.id,
                i.user_id,
                i.tipo,
                i.titulo,
                i.descricao,
                i.category_id,
                c.nome AS categoria,
                i.location_id,
                l.nome AS local,
                i.ponto_recolha_id,
                p.nome AS ponto_recolha,
                i.data_ocorrencia,
                i.estado,
                i.ia_estado,
                i.criado_em,
                i.atualizado_em
            FROM items AS i
            INNER JOIN categories AS c ON c.id = i.category_id
            INNER JOIN locations AS l ON l.id = i.location_id
            LEFT JOIN pontos_recolha AS p ON p.id = i.ponto_recolha_id
            WHERE i.id = :id
            LIMIT 1
        ';

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'id' => $id
        ]);

        $artigo = $consulta->fetch();

        return $artigo === false ? null : $artigo;
    }

        // Lista os artigos de um utilizador, começando pelos mais recentes.
    public function listarPorUtilizador(int $userId): array
    {
        $sql = '
            SELECT
                i.id,
                i.tipo,
                i.titulo,
                i.descricao,
                i.category_id,
                c.nome AS categoria,
                i.location_id,
                l.nome AS local,
                i.data_ocorrencia,
                i.estado,
                i.ia_estado,
                i.criado_em
            FROM items AS i
            INNER JOIN categories AS c ON c.id = i.category_id
            INNER JOIN locations AS l ON l.id = i.location_id
            WHERE i.user_id = :user_id
            ORDER BY i.criado_em DESC, i.id DESC
        ';

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'user_id' => $userId
        ]);

        return $consulta->fetchAll();
    }

}