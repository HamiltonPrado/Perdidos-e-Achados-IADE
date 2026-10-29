<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/PedidoLevantamento.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/administrador.php';

class PedidoLevantamentoController
{
    private PDO $pdo;
    private PedidoLevantamento $pedidos;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pedidos = new PedidoLevantamento($pdo);
    }

    // Emite ou recupera um código através de uma notificação do aluno.
    public function criar(int $notificacaoId): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        // Repete a transação em conflitos temporários ou colisões de código.
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            try {
                $this->pdo->beginTransaction();

                $contexto = $this->pedidos->procurarNotificacaoValida(
                    $notificacaoId,
                    (int) $utilizador['id']
                );

                if ($contexto === null) {
                    throw new DomainException(
                        'Notificação não encontrada ou correspondência '
                            . 'já indisponível.',
                        404
                    );
                }

                $achadoId = (int) $contexto['item_achado_id'];
                $perdidoId = (int) $contexto['item_perdido_id'];
                $matchId = (int) $contexto['match_id'];

                $pedido = $this->pedidos->procurarPorAlunoEAchado(
                    (int) $utilizador['id'],
                    $achadoId
                );

                $codigoHttp = $pedido === null ? 201 : 200;

                if (
                    $pedido !== null
                    && $pedido['estado'] === 'utilizado'
                ) {
                    throw new DomainException(
                        'Este pedido de levantamento já foi utilizado.',
                        409
                    );
                }

                // Mantém o código quando o mesmo pedido continua válido.
                $reutilizavel =
                    $pedido !== null
                    && $pedido['estado'] === 'pendente'
                    && (int) $pedido['prazo_expirado'] === 0
                    && (int) $pedido['item_perdido_id'] === $perdidoId
                    && (int) $pedido['match_id'] === $matchId;

                if (!$reutilizavel) {
                    $codigo = $this->pedidos->gerarCodigo();

                    if ($pedido === null) {
                        $this->pedidos->criar(
                            (int) $utilizador['id'],
                            $achadoId,
                            $codigo,
                            $perdidoId,
                            $matchId
                        );
                    } else {
                        $renovado = $this->pedidos->renovar(
                            (int) $pedido['id'],
                            $codigo,
                            $perdidoId,
                            $matchId
                        );

                        if (!$renovado) {
                            throw new DomainException(
                                'O pedido foi alterado entretanto. '
                                    . 'Atualiza os dados e tenta novamente.',
                                409
                            );
                        }
                    }

                    $pedido = $this->pedidos->procurarPorCodigo(
                        $codigo
                    );
                }

                if ($pedido === null) {
                    throw new RuntimeException(
                        'Não foi possível consultar o pedido criado.'
                    );
                }

                $this->pdo->commit();

                responderJson([
                    'estado' => 'ok',
                    'mensagem' => 'Código de levantamento disponível.',
                    'pedido' => [
                        'id' => (int) $pedido['id'],
                        'codigo' => $pedido['codigo'],
                        'estado' => $pedido['estado'],
                        'expira_em' => $pedido['expira_em'],
                        'item_achado_id' => $achadoId,
                        'item_perdido_id' => $perdidoId,
                        'ponto_recolha_id' =>
                            (int) $contexto['ponto_recolha_id'],
                    ],
                ], $codigoHttp);

                return;

            } catch (Throwable $erro) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                if ($erro instanceof DomainException) {
                    responderJson([
                        'erro' => $erro->getMessage(),
                    ], $erro->getCode());
                }

                if ($erro instanceof PDOException) {
                    $codigoMysql = (int) (
                        $erro->errorInfo[1] ?? 0
                    );

                    if (
                        in_array(
                            $codigoMysql,
                            [1062, 1213, 1205],
                            true
                        )
                        && $tentativa < 2
                    ) {
                        continue;
                    }
                }

                throw $erro;
            }
        }
    }

        // Permite à equipa consultar um código antes da entrega presencial.
    public function consultar(string $codigo): void
    {
        exigirAdministrador($this->pdo);

        header('Cache-Control: private, no-store');

        // Aceita letras minúsculas e um hífen de separação.
        $codigo = strtoupper(
            str_replace(['-', ' '], '', trim($codigo))
        );

        if (
            preg_match('/\A[A-HJ-NP-Z2-9]{8}\z/', $codigo) !== 1
        ) {
            responderJson([
                'erro' => 'Indica um código válido de 8 caracteres.'
            ], 422);
        }

        $pedido = $this->pedidos->procurarValidoPorCodigo(
            $codigo
        );

        if ($pedido === null) {
            responderJson([
                'erro' => 'Código inválido, expirado ou indisponível.'
            ], 404);
        }

        responderJson([
            'estado' => 'ok',
            'pedido' => [
                'id' => (int) $pedido['id'],
                'codigo' => $pedido['codigo'],
                'estado' => $pedido['estado'],
                'expira_em' => $pedido['expira_em'],
                'aluno' => [
                    'id' => (int) $pedido['user_id'],
                    'nome' => $pedido['aluno_nome'],
                    'email' => $pedido['aluno_email'],
                ],
                'achado' => [
                    'id' => (int) $pedido['item_achado_id'],
                    'titulo' => $pedido['achado_titulo'],
                ],
                'perdido' => [
                    'id' => (int) $pedido['item_perdido_id'],
                    'titulo' => $pedido['perdido_titulo'],
                ],
                'ponto_recolha' => [
                    'id' => (int) $pedido['ponto_recolha_id'],
                    'nome' => $pedido['ponto_recolha'],
                ],
            ],
        ]);
    }
}