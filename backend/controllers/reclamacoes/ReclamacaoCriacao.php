<?php

declare(strict_types=1);

trait ReclamacaoCriacao
{

    public function criar(int $achadoId): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        $dados = ReclamacaoValidator::lerDados();
        $perdidoId = $dados['item_perdido_id'];

        if ($perdidoId === $achadoId) {
            responderJson([
                'erro' => 'O perdido e o achado devem ser diferentes.'
            ], 422);

            return;
        }

        try {
            $this->pdo->beginTransaction();

            // Bloqueia os artigos pela mesma ordem em todos os pedidos.
            $ids = [$achadoId];

            if ($perdidoId !== null) {
                $ids[] = $perdidoId;
            }

            sort($ids, SORT_NUMERIC);

            $artigos = [];

            foreach ($ids as $id) {
                $artigo = $this->artigos->procurarParaAtualizacao($id);

                if ($artigo === null) {
                    throw new DomainException(
                        'Artigo não encontrado.',
                        404
                    );
                }

                $artigos[$id] = $artigo;
            }

            $achado = $artigos[$achadoId];

            if (
                $achado['tipo'] !== 'found'
                || $achado['estado'] !== 'ativo'
                || $achado['estado_recepcao'] !== 'na_recepcao'
                || $achado['ponto_recolha_id'] === null
            ) {
                throw new DomainException(
                    'O objeto não está disponível para reclamação.',
                    409
                );
            }

            if (
                !is_string($achado['pergunta_secreta'])
                || trim($achado['pergunta_secreta']) === ''
            ) {
                throw new DomainException(
                    'A equipa ainda não definiu a pergunta deste objeto.',
                    409
                );
            }

            if ($perdidoId !== null) {
                $perdido = $artigos[$perdidoId];

                if (
                    $perdido['tipo'] !== 'lost'
                    || (int) $perdido['user_id']
                        !== (int) $utilizador['id']
                ) {
                    throw new DomainException(
                        'Objeto perdido não encontrado.',
                        404
                    );
                }

                if ($perdido['estado'] !== 'ativo') {
                    throw new DomainException(
                        'O perdido associado deve estar ativo.',
                        409
                    );
                }
            }

           $existente = $this->reclamacoes->procurarPorAlunoEAchado(
    (int) $utilizador['id'],
    $achadoId
);

if ($existente === null) {
    $id = $this->reclamacoes->criar(
        (int) $utilizador['id'],
        $achado,
        $dados
    );

    $codigoHttp = 201;
} else {
    if ($existente['estado'] === 'recusada') {
        throw new DomainException(
            'A tua reclamação foi recusada. Consulta o motivo '
                . 'e fala presencialmente com a equipa da receção.',
            409
        );
    }

    if ($existente['estado'] === 'cancelada') {
        throw new DomainException(
            'Esta reclamação foi cancelada. '
                . 'Contacta a equipa da receção.',
            409
        );
    }

    $id = (int) $existente['id'];

    $reenviada = $this->reclamacoes->reenviarDesatualizada(
        $id,
        (int) $utilizador['id'],
        $achado,
        $dados
    );

    if (!$reenviada) {
        throw new DomainException(
            'Já existe uma reclamação tua para este objeto '
                . 'e a pergunta não foi alterada.',
            409
        );
    }

    $codigoHttp = 200;
}

            $this->pdo->commit();

        } catch (DomainException $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            responderJson([
                'erro' => $erro->getMessage()
            ], $erro->getCode());

            return;

        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($erro instanceof PDOException) {
                $codigo = (int) ($erro->errorInfo[1] ?? 0);

                if ($codigo === 1062) {
                    responderJson([
                        'erro' => 'Já existe uma reclamação tua '
                            . 'para este objeto.'
                    ], 409);

                    return;
                }

                if ($codigo === 1452) {
                    responderJson([
                        'erro' => 'Uma associação deixou de ser válida. '
                            . 'Atualiza os dados.'
                    ], 422);

                    return;
                }
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Reclamação enviada para análise da equipa.',
            'reclamacao' => [
                'id' => $id,
                'item_achado_id' => $achadoId,
                'item_perdido_id' => $perdidoId,
                                'estado' => 'pendente',
            ],
        ], $codigoHttp);
    }

}
