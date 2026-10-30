<?php

declare(strict_types=1);

require_once __DIR__ . '/item/ItemConsultas.php';

class Item
{
    use ItemConsultas;

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

public function atualizarPelaEquipa(int $id, array $dados): array
{
    if (!$this->pdo->inTransaction()) {
        throw new LogicException(
            'A edição do artigo exige uma transação.'
        );
    }

    $atual = $this->procurarParaGestao($id, true);

    if ($atual === null) {
        throw new DomainException('Artigo não encontrado.', 404);
    }

    if ($atual['estado'] !== 'ativo') {
        throw new DomainException(
            'Só é possível editar artigos ativos.',
            409
        );
    }

    $eAchado = $atual['tipo'] === 'found';

    $eAchadoRecebido =
        $eAchado
        && $atual['estado_recepcao'] === 'na_recepcao';

    $camposStock = [
        'localizacao_stock',
        'observacoes_internas',
    ];

    if (
        !$eAchadoRecebido
        && array_intersect($camposStock, array_keys($dados))
    ) {
        throw new DomainException(
            'Os dados de stock aplicam-se a achados já recebidos.',
            409
        );
    }

    $campos = [
        'titulo',
        'descricao',
        'category_id',
        'location_id',
        'data_ocorrencia',
    ];

    if ($eAchado) {
        $campos = array_merge($campos, [
            'pergunta_secreta',
            'resposta_esperada',
            'localizacao_stock',
            'observacoes_internas',
        ]);
    }

    // Os campos opcionais omitidos mantêm o valor atual.
    $novos = array_intersect_key(
        $dados,
        array_flip($campos)
    );

    $novos['ponto_recolha_id'] = $eAchadoRecebido
        ? $dados['ponto_recolha_id']
        : null;

    $alteracoes = [];

    foreach ($novos as $campo => $valor) {
        $anterior = $atual[$campo];

        if (in_array(
            $campo,
            ['category_id', 'location_id', 'ponto_recolha_id'],
            true
        )) {
            $anterior = $anterior === null ? null : (int) $anterior;
            $valor = $valor === null ? null : (int) $valor;
        }

        if ($anterior !== $valor) {
            $alteracoes[$campo] = $valor;
        }
    }

    // Alterações exclusivamente de stock preservam a versão.
    $mudouVersao = count(
        array_diff(array_keys($alteracoes), $camposStock)
    ) > 0;

    $versaoAnterior = (int) $atual['versao'];
    $novaVersao = $versaoAnterior + ($mudouVersao ? 1 : 0);

    $resultado = [
        'alterado' => $alteracoes !== [],
        'versao_anterior' => $versaoAnterior,
        'versao' => $novaVersao,
        'versao_alterada' => $mudouVersao,
        'pergunta_alterada' => array_key_exists(
            'pergunta_secreta',
            $alteracoes
        ),
        'ia_estado' => $mudouVersao
            ? 'pendente'
            : $atual['ia_estado'],
        'reagendar_ia' => $mudouVersao
            && ($atual['tipo'] === 'lost' || $eAchadoRecebido),
    ];

    if ($alteracoes === []) {
        return $resultado;
    }

    $atribuicoes = [];
    $parametros = ['id' => $id];

    // Os nomes das colunas vêm da lista fixa acima.
    foreach ($alteracoes as $campo => $valor) {
        $atribuicoes[] = $campo . ' = :' . $campo;
        $parametros[$campo] = $valor;
    }

    if ($mudouVersao) {
        $atribuicoes = array_merge($atribuicoes, [
            'versao = versao + 1',
            "ia_estado = 'pendente'",
            'ia_erro = NULL',
            'embedding_texto = NULL',
            'embedding_modelo = NULL',
        ]);
    }

    $consulta = $this->pdo->prepare(
        'UPDATE items
         SET ' . implode(', ', $atribuicoes) . "
         WHERE id = :id
           AND estado = 'ativo'"
    );

    $consulta->execute($parametros);

    if ($consulta->rowCount() !== 1) {
        throw new DomainException(
            'Não foi possível atualizar o artigo.',
            409
        );
    }

    return $resultado;
}


}