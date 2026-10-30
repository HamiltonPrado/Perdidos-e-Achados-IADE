<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/Reclamacao.php';
require_once __DIR__ . '/../validators/ReclamacaoValidator.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../validators/DecisaoReclamacaoValidator.php';

class ReclamacaoController
{
    private PDO $pdo;
    private Item $artigos;
    private Reclamacao $reclamacoes;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
        $this->reclamacoes = new Reclamacao($pdo);
    }

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

            if ($existente !== null) {
                throw new DomainException(
                    'Já existe uma reclamação tua para este objeto.',
                    409
                );
            }

            $id = $this->reclamacoes->criar(
                (int) $utilizador['id'],
                $achado,
                $dados
            );

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
        ], 201);
    }

        // A resposta esperada só é apresentada à equipa administradora.
    public function listarTodos(): void
    {
        exigirAdministrador($this->pdo);

        header('Cache-Control: private, no-store');

        $reclamacoes = $this->reclamacoes->listarParaEquipa();

        responderJson([
            'estado' => 'ok',
            'reclamacoes' => $reclamacoes,
        ]);
    }

        public function decidir(int $id): void
    {
        $admin = exigirAdministrador($this->pdo);
        exigirCsrf();

        $dados = DecisaoReclamacaoValidator::lerDados();

        $referencia = $this->reclamacoes->procurarPorId($id);

        if ($referencia === null) {
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

            // Mantém a ordem de bloqueio usada nas outras operações.
            $ids = [$achadoId];

            if ($perdidoId !== null) {
                $ids[] = $perdidoId;
            }

            $ids = array_unique($ids);
            sort($ids, SORT_NUMERIC);

            $artigos = [];

            foreach ($ids as $artigoId) {
                $artigo = $this->artigos->procurarParaAtualizacao(
                    $artigoId
                );

                if ($artigo === null) {
                    throw new DomainException(
                        'Artigo não encontrado.',
                        404
                    );
                }

                $artigos[$artigoId] = $artigo;
            }

            $reclamacao = $this->reclamacoes->procurarPorId(
                $id,
                true
            );

            if ($reclamacao === null) {
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

            if ($reclamacao['estado'] !== 'pendente') {
                throw new DomainException(
                    'Só é possível decidir reclamações pendentes.',
                    409
                );
            }

            if ($dados['estado'] === 'aprovada') {
                $achado = $artigos[$achadoId];

                if (
                    $achado['tipo'] !== 'found'
                    || $achado['estado'] !== 'ativo'
                    || $achado['estado_recepcao'] !== 'na_recepcao'
                    || $achado['ponto_recolha_id'] === null
                ) {
                    throw new DomainException(
                        'O achado já não está disponível.',
                        409
                    );
                }

                if (
                    (int) $achado['versao']
                        !== (int) $reclamacao['versao_item']
                    || $achado['pergunta_secreta']
                        !== $reclamacao['pergunta_secreta']
                ) {
                    throw new DomainException(
                        'A versão do objeto já não corresponde '
                            . 'à reclamação.',
                        409
                    );
                }

                if ($perdidoId !== null) {
                    $perdido = $artigos[$perdidoId];

                    if (
                        $perdido['tipo'] !== 'lost'
                        || $perdido['estado'] !== 'ativo'
                        || (int) $perdido['user_id']
                            !== (int) $reclamacao['user_id']
                    ) {
                        throw new DomainException(
                            'O perdido associado já não é válido.',
                            409
                        );
                    }
                }

                if ($this->reclamacoes->existeOutraAprovada(
                    $achadoId,
                    $id
                )) {
                    throw new DomainException(
                        'Já existe outra reclamação aprovada '
                            . 'para este achado.',
                        409
                    );
                }
            }

            $alterada = $this->reclamacoes->decidir(
                $id,
                (int) $admin['id'],
                $dados
            );

            if (!$alterada) {
                throw new DomainException(
                    'Não foi possível guardar a decisão.',
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
                    [1213, 1205],
                    true
                )
            ) {
                responderJson([
                    'erro' => 'Ocorreu um conflito. '
                        . 'Atualiza os dados e tenta novamente.'
                ], 409);

                return;
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => $dados['estado'] === 'aprovada'
                ? 'Reclamação aprovada.'
                : 'Reclamação recusada.',
            'reclamacao' => [
                'id' => $id,
                'item_achado_id' => $achadoId,
                'estado' => $dados['estado'],
                'motivo_decisao' => $dados['motivo_decisao'],
                'decidido_por' => (int) $admin['id'],
            ],
        ]);
    }

}