<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/PedidoLevantamento.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/Reclamacao.php';

class PedidoLevantamentoController
{
    private PDO $pdo;
    private PedidoLevantamento $pedidos;
    private Item $artigos;
    private Reclamacao $reclamacoes;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pedidos = new PedidoLevantamento($pdo);
        $this->artigos = new Item($pdo);
        $this->reclamacoes = new Reclamacao($pdo);
    }

       // A emissão de códigos passa pelas reclamações aprovadas.
public function criar(int $notificacaoId): void
{
    exigirAutenticacao($this->pdo);
    exigirCsrf();

    responderJson([
 'erro' => 'O código deve ser solicitado através '
   . 'de uma reclamação aprovada pela equipa.',
    ], 409);
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
                'reclamacao_id' => (int) $pedido['reclamacao_id'],
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
             'perdido' => $pedido['item_perdido_id'] === null
                    ? null
                    : [
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

        // Emite ou recupera o código de uma reclamação aprovada.
    public function criarPorReclamacao(int $reclamacaoId): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        header('Cache-Control: private, no-store');

        $userId = (int) $utilizador['id'];

        $referencia = $this->reclamacoes->procurarPorId(
            $reclamacaoId
        );

        if (
            $referencia === null
            || (int) $referencia['user_id'] !== $userId
        ) {
            responderJson([
                'erro' => 'Reclamação não encontrada.'
            ], 404);

            return;
        }

        $achadoId = (int) $referencia['item_achado_id'];

        $perdidoId = $referencia['item_perdido_id'] === null
            ? null
            : (int) $referencia['item_perdido_id'];

        try {
            $this->pdo->beginTransaction();

            $ids = [$achadoId];

            if ($perdidoId !== null) {
                $ids[] = $perdidoId;
            }

            $ids = array_unique($ids);
            sort($ids, SORT_NUMERIC);

            foreach ($ids as $id) {
                $artigo = $this->artigos->procurarParaAtualizacao($id);

                if ($artigo === null) {
                    throw new DomainException(
                        'O objeto já não está disponível.',
                        409
                    );
                }
            }

            $reclamacao = $this->reclamacoes->procurarPorId(
                $reclamacaoId,
                true
            );

            if (
                $reclamacao === null
                || (int) $reclamacao['user_id'] !== $userId
            ) {
                throw new DomainException(
                    'Reclamação não encontrada.',
                    404
                );
            }

            $perdidoAtualId =
                $reclamacao['item_perdido_id'] === null
                    ? null
                    : (int) $reclamacao['item_perdido_id'];

            if (
                (int) $reclamacao['item_achado_id'] !== $achadoId
                || $perdidoAtualId !== $perdidoId
            ) {
                throw new DomainException(
                    'A reclamação foi alterada. Atualiza os dados.',
                    409
                );
            }

            if ($reclamacao['estado'] !== 'aprovada') {
                throw new DomainException(
                    'O código só está disponível após '
                        . 'a aprovação da equipa.',
                    409
                );
            }

            $matchId = $reclamacao['match_id'] === null
                ? null
                : (int) $reclamacao['match_id'];

            $pedido = $this->pedidos->procurarPorAlunoEAchado(
                $userId,
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

            $reutilizavel =
                $pedido !== null
                && $pedido['estado'] === 'pendente'
                && (int) $pedido['prazo_expirado'] === 0
                && (int) $pedido['reclamacao_id'] === $reclamacaoId;

            if ($reutilizavel) {
                $codigo = $pedido['codigo'];
            } else {
                $codigo = $this->pedidos->gerarCodigo();

                if ($pedido === null) {
                    $this->pedidos->criar(
                        $userId,
                        $achadoId,
                        $codigo,
                        $reclamacaoId,
                        $perdidoId,
                        $matchId
                    );
                } else {
                    $renovado = $this->pedidos->renovar(
                        (int) $pedido['id'],
                        $codigo,
                        $reclamacaoId,
                        $perdidoId,
                        $matchId
                    );

                    if (!$renovado) {
                        throw new DomainException(
                            'Não foi possível renovar o código.',
                            409
                        );
                    }
                }
            }

            // Confirma aprovação, versão, disponibilidade e associações.
            $pedido = $this->pedidos->procurarValidoPorCodigo(
                $codigo,
                true
            );

            if ($pedido === null) {
                throw new DomainException(
                    'O objeto ou a reclamação já não estão '
                        . 'disponíveis para levantamento.',
                    409
                );
            }

            $this->pdo->commit();

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

            if (
                $erro instanceof PDOException
                && in_array(
                    (int) ($erro->errorInfo[1] ?? 0),
                    [1062, 1213, 1205],
                    true
                )
            ) {
                responderJson([
                    'erro' => 'Ocorreu um conflito. '
                        . 'Tenta novamente.'
                ], 409);

                return;
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Código de levantamento disponível.',
            'pedido' => [
                'id' => (int) $pedido['id'],
                'reclamacao_id' => $reclamacaoId,
                'codigo' => $pedido['codigo'],
                'estado' => $pedido['estado'],
                'expira_em' => $pedido['expira_em'],
                'item_achado_id' => $achadoId,
                'item_perdido_id' => $perdidoId,
                'ponto_recolha_id' => (int) $pedido['ponto_recolha_id'],
            ],
        ], $codigoHttp);
    }
}