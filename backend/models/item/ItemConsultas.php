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
            i.atualizado_em,
            (
                SELECT MIN(f.id)
                FROM item_photos AS f
                WHERE f.item_id = i.id
            ) AS foto_id
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

        return array_map([$this, 'adicionarReferencia'], $consulta->fetchAll());
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
                i.criado_em,
                (
                    SELECT MIN(f.id)
                    FROM item_photos AS f
                    WHERE f.item_id = i.id
                ) AS foto_id
            FROM items AS i
            INNER JOIN users AS u ON u.id = i.user_id
            INNER JOIN categories AS c ON c.id = i.category_id
            INNER JOIN locations AS l ON l.id = i.location_id
            LEFT JOIN pontos_recolha AS p ON p.id = i.ponto_recolha_id
            ORDER BY i.criado_em DESC, i.id DESC
        ';

        $consulta = $this->pdo->query($sql);

        return array_map([$this, 'adicionarReferencia'], $consulta->fetchAll());
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

        return $artigo === false ? null : $this->adicionarReferencia($artigo);
    }

    // Consulta o artigo e impede outras operações de o alterarem
    // até esta transação terminar.
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

        return array_map([$this, 'adicionarReferencia'], $consulta->fetchAll());
    }

    // Lista apenas achados ativos já entregues à receção, para quem tem sessão iniciada.
    // Filtros opcionais: categoria (ID), local (ID), q (texto no título ou descrição)
    // e ordem ('recentes' ou 'antigos', pela data em que o objeto foi encontrado).
    public function listarAchadosDisponiveis(
        int $limite = 20,
        int $offset = 0,
        array $filtros = []
    ): array {
        [$condicoes, $valores] = $this->condicoesAchados($filtros);

        $direcao = ($filtros['ordem'] ?? 'recentes') === 'antigos' ? 'ASC' : 'DESC';

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
                i.criado_em,
                (
                    SELECT MIN(f.id)
                    FROM item_photos AS f
                    WHERE f.item_id = i.id
                ) AS foto_id
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
              {$condicoes}
            ORDER BY i.data_ocorrencia {$direcao}, i.id {$direcao}
            LIMIT :limite OFFSET :offset
        ";

        $consulta = $this->pdo->prepare($sql);
        $this->ligarValores($consulta, $valores);

        $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
        $consulta->bindValue(':offset', $offset, PDO::PARAM_INT);

        $consulta->execute();

        return array_map([$this, 'adicionarReferencia'], $consulta->fetchAll());
    }

    // Conta os achados disponíveis com os mesmos filtros da lista (para a paginação).
    public function contarAchadosDisponiveis(array $filtros = []): int
    {
        [$condicoes, $valores] = $this->condicoesAchados($filtros);

        $consulta = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM items AS i
            WHERE i.tipo = 'found'
              AND i.estado = 'ativo'
              AND i.estado_recepcao = 'na_recepcao'
              {$condicoes}
        ");
        $this->ligarValores($consulta, $valores);
        $consulta->execute();

        return (int) $consulta->fetchColumn();
    }

    // Achados que a IA sugeriu para um perdido, do mais parecido para o menos.
    // Só os que continuam disponíveis na receção e cuja sugestão não foi descartada.
    public function listarSugestoesIa(int $perdidoId, int $limite = 6): array
    {
        $consulta = $this->pdo->prepare("
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
                i.criado_em,
                m.score,
                (
                    SELECT MIN(f.id)
                    FROM item_photos AS f
                    WHERE f.item_id = i.id
                ) AS foto_id
            FROM matches AS m
            INNER JOIN items AS i
                ON i.id = m.matched_item_id
            INNER JOIN categories AS c
                ON c.id = i.category_id
            INNER JOIN locations AS l
                ON l.id = i.location_id
            INNER JOIN pontos_recolha AS p
                ON p.id = i.ponto_recolha_id
            WHERE m.item_id = :perdido
              AND m.estado = 'sugerido'
              AND i.tipo = 'found'
              AND i.estado = 'ativo'
              AND i.estado_recepcao = 'na_recepcao'
            ORDER BY m.score DESC, i.id DESC
            LIMIT :limite
        ");

        $consulta->bindValue(':perdido', $perdidoId, PDO::PARAM_INT);
        $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
        $consulta->execute();

        return array_map([$this, 'adicionarReferencia'], $consulta->fetchAll());
    }

    // Filtros opcionais da lista de achados → [condições SQL, valores para os marcadores].
    private function condicoesAchados(array $filtros): array
    {
        $condicoes = '';
        $valores = [];

        if (isset($filtros['categoria'])) {
            $condicoes .= ' AND i.category_id = :categoria';
            $valores[':categoria'] = $filtros['categoria'];
        }

        if (isset($filtros['local'])) {
            $condicoes .= ' AND i.location_id = :local';
            $valores[':local'] = $filtros['local'];
        }

        if (isset($filtros['q'])) {
            // O PDO sem emulação não aceita o mesmo marcador duas vezes.
            $condicoes .= ' AND (i.titulo LIKE :q_titulo OR i.descricao LIKE :q_descricao)';
            $padrao = '%' . addcslashes($filtros['q'], '%_\\') . '%';
            $valores[':q_titulo'] = $padrao;
            $valores[':q_descricao'] = $padrao;
        }

        return [$condicoes, $valores];
    }

    private function ligarValores(PDOStatement $consulta, array $valores): void
    {
        foreach ($valores as $marcador => $valor) {
            $consulta->bindValue(
                $marcador,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    // Lê os dados internos de que a equipa precisa para gerir o artigo.
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

        return $artigo === false ? null : $this->adicionarReferencia($artigo);
    }

}
