<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Devolucao.php';
require_once __DIR__ . '/../models/Correspondencia.php';
require_once __DIR__ . '/../validators/DevolucaoValidator.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';

class DevolucaoController
{
    private PDO $pdo;
    private Item $artigos;
    private User $utilizadores;
    private Devolucao $devolucoes;
    private Correspondencia $correspondencias;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
        $this->utilizadores = new User($pdo);
        $this->devolucoes = new Devolucao($pdo);
        $this->correspondencias = new Correspondencia($pdo);
    }

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

        // Confirma o perdido e a sua associação à pessoa que recebe.
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

        // Se existir uma recomendação associada, valida o par de artigos.
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

        // Guarda o registo com o administrador e o ponto obtidos no backend.
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

    // Bloqueia os artigos envolvidos, dentro de uma transação.
    private function bloquearArtigos(
        int $achadoId,
        ?int $perdidoId
    ): array {
        if ($perdidoId === $achadoId) {
            throw new DomainException(
                'O perdido e o achado devem ser artigos diferentes.',
                422
            );
        }

        $ids = [$achadoId];

        if ($perdidoId !== null) {
            $ids[] = $perdidoId;
        }

        // Todos os pedidos bloqueiam os artigos pela mesma ordem.
        sort($ids, SORT_NUMERIC);

        $artigos = [];

        foreach ($ids as $id) {
            $artigo = $this->artigos->procurarParaAtualizacao($id);

            if ($artigo === null) {
                throw new DomainException(
                    'Um dos artigos indicados não foi encontrado.',
                    404
                );
            }

            $artigos[$id] = $artigo;
        }

        return $artigos;
    }

    // Permite à equipa consultar o histórico de devoluções.
public function listarTodos(): void
{
    exigirAdministrador($this->pdo);

    $devolucoes = $this->devolucoes->listarTodos();

    responderJson([
        'estado' => 'ok',
        'devolucoes' => $devolucoes
    ]);
}

}