<?php

declare(strict_types=1);

trait DevolucaoPorCodigo
{

    // Confirma a entrega presencial e consome o código numa transação.
    public function confirmarPorCodigo(string $codigo): void
    {
        $admin = exigirAdministrador($this->pdo);
        exigirCsrf();

        header('Cache-Control: private, no-store');

        $codigo = strtoupper(
            str_replace(['-', ' '], '', trim($codigo))
        );

                       $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        if (
            strlen($codigo) !== 8
            || strspn($codigo, $alfabeto) !== 8
        ) {
            responderJson([
                'erro' => 'Indica um código válido de 8 caracteres.'
            ], 422);
        }
        // Obtém os IDs para bloquear os artigos pela ordem habitual.
        $referencia = $this->pedidosLevantamento
            ->procurarValidoPorCodigo($codigo);

        if ($referencia === null) {
            responderJson([
                'erro' => 'Código inválido, expirado ou indisponível.'
            ], 409);
        }

        $achadoId = (int) $referencia['item_achado_id'];
            $perdidoId = $referencia['item_perdido_id'] === null
            ? null
            : (int) $referencia['item_perdido_id'];

        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            try {
                $this->pdo->beginTransaction();

                $this->bloquearArtigos($achadoId, $perdidoId);

                // Volta a validar com os registos bloqueados.
                $pedido = $this->pedidosLevantamento
                    ->procurarValidoPorCodigo($codigo, true);

                if (
                    $pedido === null
                    || (int) $pedido['item_achado_id'] !== $achadoId
                                        || (
                        $pedido['item_perdido_id'] === null
                            ? null
                            : (int) $pedido['item_perdido_id']
                    ) !== $perdidoId
                ) {
                    throw new DomainException(
                        'O código ou os artigos já não estão disponíveis.',
                        409
                    );
                }

                $pedidoId = (int) $pedido['id'];
                $matchId = $pedido['match_id'] === null
                ? null
                : (int) $pedido['match_id'];
                $recebedorId = (int) $pedido['user_id'];

                if (
                    !$this->pedidosLevantamento
                        ->marcarUtilizado($pedidoId)
                ) {
                    throw new DomainException(
                        'O código expirou ou já foi utilizado.',
                        409
                    );
                }

                $devolucaoId = $this->devolucoes->criar(
                    (int) $admin['id'],
                    [
                        'match_id' => $matchId,
                        'item_perdido_id' => $perdidoId,
                        'item_achado_id' => $achadoId,
                        'recebido_por' => $recebedorId,
                        'ponto_recolha_id' =>
                            (int) $pedido['ponto_recolha_id'],
                    ]
                );

                  if (
                    !$this->artigos->marcarDevolvido($achadoId)
                    || (
                        $perdidoId !== null
                        && !$this->artigos->marcarDevolvido($perdidoId)
                    )
                ) {
                    throw new DomainException(
                        'Não foi possível atualizar os artigos.',
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

                         // Cancela os outros pedidos dos artigos entregues.
                $this->pedidosLevantamento->cancelarOutrosPedidos(
                    $achadoId,
                    $perdidoId,
                    $pedidoId
                );

                $this->pdo->commit();

                responderJson([
                    'estado' => 'ok',
                    'mensagem' => 'Entrega confirmada.',
                    'pedido' => [
                        'id' => $pedidoId,
                        'estado' => 'utilizado',
                    ],
                    'devolucao' => [
                        'id' => $devolucaoId,
                        'item_achado_id' => $achadoId,
                        'item_perdido_id' => $perdidoId,
                        'recebido_por' => $recebedorId,
                    ],
                ], 201);

                return;

            } catch (Throwable $erro) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                if ($erro instanceof DomainException) {
                    responderJson([
                        'erro' => $erro->getMessage()
                    ], $erro->getCode());

                    return;
                }

                if ($erro instanceof PDOException) {
                    $codigoMysql = (int) (
                        $erro->errorInfo[1] ?? 0
                    );

                    if (
                        in_array($codigoMysql, [1213, 1205], true)
                        && $tentativa < 2
                    ) {
                        continue;
                    }

                    if (
                        in_array(
                            $codigoMysql,
                            [1062, 1213, 1205],
                            true
                        )
                    ) {
                        responderJson([
                            'erro' => 'Já existe uma entrega ou ocorreu '
                                . 'um conflito. Atualiza os dados.'
                        ], 409);

                        return;
                    }
                }

                throw $erro;
            }
        }
    }

}
