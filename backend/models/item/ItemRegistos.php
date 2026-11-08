<?php

declare(strict_types=1);

trait ItemRegistos
{
    // Guarda o perdido com o ID da conta que tem sessão iniciada.
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

    // Guarda um achado registado pela equipa, já recebido na receção.
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
            pergunta_secreta,
            resposta_esperada,
            localizacao_stock,
            observacoes_internas,
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
            :pergunta_secreta,
            :resposta_esperada,
            :localizacao_stock,
            :observacoes_internas,
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
            'pergunta_secreta' => $dados['pergunta_secreta'],
            'resposta_esperada' => $dados['resposta_esperada'],
            'localizacao_stock' => $dados['localizacao_stock'] ?? null,
            'observacoes_internas' => $dados['observacoes_internas'] ?? null,
            'recebido_por' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // Guarda o achado registado pelo aluno como por_entregar;
    // a equipa ainda terá de confirmar que recebeu o objeto.
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

}
