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
                data_ocorrencia,
                estado_recepcao,
                recebido_por,
                recebido_em
            )
            VALUES (
                :user_id,
                'found',
                :titulo,
                :descricao,
                :category_id,
                :location_id,
                :ponto_recolha_id,
                :data_ocorrencia,
                'na_recepcao',
                :recebido_por,
                CURRENT_TIMESTAMP
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
            'recebido_por' => $userId,
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
                i.estado_recepcao,
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

        // Marca um perdido ativo como resolvido pelo próprio utilizador.
    public function marcarResolvido(int $id, int $userId): bool
    {
        $sql = "
            UPDATE items
            SET estado = 'resolvido'
            WHERE id = :id
              AND user_id = :user_id
              AND tipo = 'lost'
              AND estado = 'ativo'
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        return $consulta->rowCount() === 1;
    }
    

    // Arquiva um artigo apenas se ainda estiver ativo.
public function arquivar(int $id): bool
{
    $consulta = $this->pdo->prepare(
        "UPDATE items
         SET estado = 'arquivado'
         WHERE id = :id
           AND estado = 'ativo'"
    );

    $consulta->execute([
        'id' => $id
    ]);

    return $consulta->rowCount() === 1;
}

// Atualiza os dados de um artigo que ainda esteja ativo.
public function atualizar(int $id, array $dados): int
{
    $sql = "
    UPDATE items
    SET titulo = :titulo,
        descricao = :descricao,
        category_id = :category_id,
        location_id = :location_id,
        ponto_recolha_id = :ponto_recolha_id,
        data_ocorrencia = :data_ocorrencia,
        versao = versao + 1,
        ia_estado = 'pendente',
        ia_erro = NULL,
        embedding_texto = NULL,
        embedding_modelo = NULL
    WHERE id = :id
      AND estado = 'ativo'
";

    $consulta = $this->pdo->prepare($sql);

    $consulta->execute([
        'id' => $id,
        'titulo' => $dados['titulo'],
        'descricao' => $dados['descricao'],
        'category_id' => $dados['category_id'],
        'location_id' => $dados['location_id'],
        'ponto_recolha_id' => $dados['ponto_recolha_id'],
        'data_ocorrencia' => $dados['data_ocorrencia'],
    ]);

    return $consulta->rowCount();
}


// Consulta e bloqueia o artigo durante uma transação.
public function procurarParaAtualizacao(int $id): ?array
{
    $consulta = $this->pdo->prepare(
               'SELECT id, user_id, tipo, estado, estado_recepcao,
                ponto_recolha_id, versao
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

// Invalida a análise anterior após alterar as fotografias.
// Deve ser chamado numa transação, com o artigo bloqueado.
public function marcarAnalisePendente(int $id): void
{
    $consulta = $this->pdo->prepare(
        "UPDATE items
         SET versao = versao + 1,
             ia_estado = 'pendente',
             ia_erro = NULL,
             embedding_texto = NULL,
             embedding_modelo = NULL
         WHERE id = :id
           AND estado = 'ativo'"
    );

    $consulta->execute([
        'id' => $id
    ]);

    if ($consulta->rowCount() !== 1) {
        throw new RuntimeException(
            'Não foi possível preparar a nova análise do artigo.'
        );
    }
}

    // Marca um artigo como devolvido apenas se ainda estiver ativo.
public function marcarDevolvido(int $id): bool
{
    $consulta = $this->pdo->prepare(
        "UPDATE items
         SET estado = 'devolvido'
         WHERE id = :id
           AND estado = 'ativo'"
    );

    $consulta->execute([
        'id' => $id
    ]);

    return $consulta->rowCount() === 1;
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
    
        // Regista um achado do aluno, a aguardar entrega na receção.
    public function criarAchadoPorAluno(
        int $userId,
        array $dados
    ): int {
        $sql = "
            INSERT INTO items (
                user_id,
                tipo,
                titulo,
                descricao,
                category_id,
                location_id,
                ponto_recolha_id,
                data_ocorrencia,
                estado_recepcao
            )
            VALUES (
                :user_id,
                'found',
                :titulo,
                :descricao,
                :category_id,
                :location_id,
                NULL,
                :data_ocorrencia,
                'por_entregar'
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

        // Confirma a entrada física de um achado entregue à equipa.
    public function confirmarEntrada(
        int $id,
        int $adminId,
        array $dados
    ): bool {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException(
                'A confirmação de entrada exige uma transação.'
            );
        }

        $sql = "
            UPDATE items
            SET estado_recepcao = 'na_recepcao',
                ponto_recolha_id = :ponto_recolha_id,
                localizacao_stock = :localizacao_stock,
                observacoes_internas = :observacoes_internas,
                recebido_por = :recebido_por,
                recebido_em = CURRENT_TIMESTAMP,
                versao = versao + 1,
                ia_estado = 'pendente',
                ia_erro = NULL,
                embedding_texto = NULL,
                embedding_modelo = NULL
            WHERE id = :id
              AND tipo = 'found'
              AND estado = 'ativo'
              AND estado_recepcao = 'por_entregar'
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'ponto_recolha_id' => $dados['ponto_recolha_id'],
            'localizacao_stock' => $dados['localizacao_stock'],
            'observacoes_internas' => $dados['observacoes_internas'],
            'recebido_por' => $adminId,
            'id' => $id,
        ]);

        return $consulta->rowCount() === 1;
    }


}