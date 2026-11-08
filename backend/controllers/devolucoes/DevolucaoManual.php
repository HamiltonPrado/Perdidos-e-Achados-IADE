<?php

declare(strict_types=1);

trait DevolucaoManual
{
    // Regista uma entrega presencial confirmada pela equipa.
    public function criar(int $achadoId): void
    {
        $admin = exigirAdministrador($this->pdo);
        exigirCsrf();

        $dados = DevolucaoValidator::lerDados();

        $recebedor = $this->utilizadores->procurarPorId(
            $dados['recebido_por']
        );

        if (
            $recebedor === null
            || (int) $recebedor['ativo'] !== 1
        ) {
            responderJson([
                'erro' => 'A pessoa indicada não existe ou está inativa.'
            ], 422);
        }

        $perdidoId = $dados['item_perdido_id'];
        $matchId = $dados['match_id'];

        try {
            $this->pdo->beginTransaction();

            $artigos = $this->bloquearArtigos(
                $achadoId,
                $perdidoId
            );

            $achado = $artigos[$achadoId];

            // O artigo indicado no URL tem de ser um achado ativo.
            if ($achado['tipo'] !== 'found') {
                throw new DomainException(
                    'O artigo indicado deve ser um objeto achado.',
                    422
                );
            }

            if ($achado['estado'] !== 'ativo') {
                throw new DomainException(
                    'Só é possível devolver um objeto achado ativo.',
                    409
                );
            }

            if ($achado['ponto_recolha_id'] === null) {
                throw new DomainException(
                    'O achado não tem um ponto de recolha definido.',
                    409
                );
            }

            // Se foi indicado um perdido, verifica se está ativo e pertence à pessoa que recebe.
            if ($perdidoId !== null) {
                $perdido = $artigos[$perdidoId];

                if ($perdido['tipo'] !== 'lost') {
                    throw new DomainException(
                        'O artigo perdido indicado não é do tipo perdido.',
                        422
                    );
                }

                if (
                    (int) $perdido['user_id']
                    !== $dados['recebido_por']
                ) {
                    throw new DomainException(
                        'O perdido não pertence à pessoa que recebe o objeto.',
                        422
                    );
                }

                if ($perdido['estado'] !== 'ativo') {
                    throw new DomainException(
                        'O objeto perdido associado deve estar ativo.',
                        409
                    );
                }
            }

            // Se foi indicado um match_id, confirma que a sugestão da IA
            // liga exatamente este perdido a este achado e continua válida.
            if ($matchId !== null) {
                $correspondencia =
                    $this->correspondencias->procurarParaAtualizacao(
                        $matchId
                    );

                if ($correspondencia === null) {
                    throw new DomainException(
                        'Correspondência não encontrada.',
                        404
                    );
                }

                if (
                    (int) $correspondencia['item_id'] !== $perdidoId
                    || (int) $correspondencia['matched_item_id'] !== $achadoId
                ) {
                    throw new DomainException(
                        'A correspondência não pertence aos artigos indicados.',
                        422
                    );
                }

                if ($correspondencia['estado'] !== 'sugerido') {
                    throw new DomainException(
                        'A correspondência já não está disponível.',
                        409
                    );
                }
            }

            // Guarda a entrega com o ID da conta admin e o ponto de recolha do achado,
            // sem aceitar que o cliente escolha esses dois valores.
            $devolucaoId = $this->devolucoes->criar(
                $admin['id'],
                [
                    'match_id' => $matchId,
                    'item_perdido_id' => $perdidoId,
                    'item_achado_id' => $achadoId,
                    'recebido_por' => $dados['recebido_por'],
                    'ponto_recolha_id' => (int) $achado['ponto_recolha_id'],
                ]
            );

            if (!$this->artigos->marcarDevolvido($achadoId)) {
                throw new DomainException(
                    'Não foi possível atualizar o estado do achado.',
                    409
                );
            }

            if (
                $perdidoId !== null
                && !$this->artigos->marcarDevolvido($perdidoId)
            ) {
                throw new DomainException(
                    'Não foi possível atualizar o estado do perdido.',
                    409
                );
            }

            if (
                $matchId !== null
                && !$this->correspondencias->concluir($matchId)
            ) {
                throw new DomainException(
                    'Não foi possível concluir a correspondência.',
                    409
                );
            }

            // Depois da entrega manual, cancela os códigos pendentes dos objetos entregues.
            $this->pedidosLevantamento->cancelarOutrosPedidos(
                $achadoId,
                $perdidoId
            );

            // O objeto foi entregue: as notificações das reclamações dele passam ao histórico.
            $this->notificacoes->arquivarPorAchado($achadoId);

            $this->pdo->commit();

        } catch (DomainException $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            responderJson([
                'erro' => $erro->getMessage()
            ], $erro->getCode());

        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($erro instanceof PDOException) {
                $codigo = (int) ($erro->errorInfo[1] ?? 0);

                if ($codigo === 1062) {
                    responderJson([
                        'erro' => 'Já existe uma devolução associada '
                            . 'a um dos artigos ou à correspondência.'
                    ], 409);
                }

                if ($codigo === 1452) {
                    responderJson([
                        'erro' => 'Uma das associações da devolução '
                            . 'deixou de ser válida. Atualiza os dados.'
                    ], 422);
                }
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Devolução registada.',
            'devolucao' => [
                'id' => $devolucaoId,
                'item_achado_id' => $achadoId,
                'item_perdido_id' => $perdidoId,
                'recebido_por' => $dados['recebido_por'],
            ]
        ], 201);
    }

}
