<?php

declare(strict_types=1);

trait ItemRegistos
{


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
            pergunta_secreta,
            resposta_esperada,
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
        'recebido_por' => $userId,
    ]);

    return (int) $this->pdo->lastInsertId();
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
                pergunta_secreta,
                resposta_esperada,
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
                :pergunta_secreta,
                :resposta_esperada,
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
            'pergunta_secreta' => $dados['pergunta_secreta'],
            'resposta_esperada' => $dados['resposta_esperada'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

}
