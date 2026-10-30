<?php

declare(strict_types=1);

trait ItemConsultas
{

    // Lista apenas os artigos do utilizador indicado.
public function listarPorUtilizador(int $userId): array
{
    $consulta = $this->pdo->prepare(
        'SELECT
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
            i.estado_recepcao,
            i.ia_estado,
            i.criado_em,
            i.atualizado_em
         FROM items AS i
         INNER JOIN categories AS c ON c.id = i.category_id
         INNER JOIN locations AS l ON l.id = i.location_id
         LEFT JOIN pontos_recolha AS p
            ON p.id = i.ponto_recolha_id
         WHERE i.user_id = :user_id
         ORDER BY i.criado_em DESC, i.id DESC'
    );

    $consulta->execute([
        'user_id' => $userId,
    ]);

    return $consulta->fetchAll();
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
                i.estado_recepcao,
                i.localizacao_stock,
                i.observacoes_internas,
                i.recebido_por,
                i.recebido_em,
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
                i.estado_recepcao,
                i.pergunta_secreta,
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

// Consulta e bloqueia o artigo durante uma transação.
public function procurarParaAtualizacao(int $id): ?array
{
    $consulta = $this->pdo->prepare(
               'SELECT id, user_id, tipo, estado, estado_recepcao,
                ponto_recolha_id, versao, pergunta_secreta
         FROM items
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );

    $consulta->execute([
        'id' => $id
    ]);

    $artigo = $consulta->fetch();

    return $artigo === false ? null : $artigo;
}

    // Lista os perdidos ativos para consulta pelos alunos.
    public function listarPerdidosAtivos(
        int $limite = 20,
        int $offset = 0
    ): array {
        $sql = "
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
                i.criado_em
            FROM items AS i
            INNER JOIN categories AS c ON c.id = i.category_id
            INNER JOIN locations AS l ON l.id = i.location_id
            WHERE i.tipo = 'lost'
              AND i.estado = 'ativo'
            ORDER BY i.criado_em DESC, i.id DESC
            LIMIT :limite OFFSET :offset
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
        $consulta->bindValue(':offset', $offset, PDO::PARAM_INT);

        $consulta->execute();

        return $consulta->fetchAll();
    }

        // Lista os achados disponíveis para os alunos autenticados.
    public function listarAchadosDisponiveis(
        int $limite = 20,
        int $offset = 0
    ): array {
        $sql = "
            SELECT
                i.id,
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
                i.estado_recepcao,
                i.criado_em
            FROM items AS i
            INNER JOIN categories AS c
                ON c.id = i.category_id
            INNER JOIN locations AS l
                ON l.id = i.location_id
            INNER JOIN pontos_recolha AS p
                ON p.id = i.ponto_recolha_id
            WHERE i.tipo = 'found'
              AND i.estado = 'ativo'
              AND i.estado_recepcao = 'na_recepcao'
            ORDER BY i.criado_em DESC, i.id DESC
            LIMIT :limite OFFSET :offset
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->bindValue(
            ':limite',
            $limite,
            PDO::PARAM_INT
        );

        $consulta->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $consulta->execute();

        return $consulta->fetchAll();
    }

    // Obtém os dados de gestão para operações autorizadas da equipa.
public function procurarParaGestao(
    int $id,
    bool $bloquear = false
): ?array {
    if ($bloquear && !$this->pdo->inTransaction()) {
        throw new LogicException(
            'O bloqueio do artigo exige uma transação.'
        );
    }

    $sql = '
        SELECT
            id,
            user_id,
            tipo,
            estado,
            estado_recepcao,
            titulo,
            descricao,
            category_id,
            location_id,
            ponto_recolha_id,
            data_ocorrencia,
            pergunta_secreta,
            resposta_esperada,
            localizacao_stock,
            observacoes_internas,
            recebido_por,
            recebido_em,
            versao,
            ia_estado
        FROM items
        WHERE id = :id
        LIMIT 1
    ';

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $consulta = $this->pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $artigo = $consulta->fetch();

    return $artigo === false ? null : $artigo;
}

}
