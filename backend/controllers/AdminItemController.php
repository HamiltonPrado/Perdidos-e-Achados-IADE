<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../validators/ItemValidator.php';
require_once __DIR__ . '/../models/IaJob.php';
require_once __DIR__ . '/../validators/RecepcaoValidator.php';
require_once __DIR__ . '/../models/Reclamacao.php';
class AdminItemController
{
    private PDO $pdo;
    private Item $artigos;

    private IaJob $filaIa;

  public function __construct(PDO $pdo)
{
    $this->pdo = $pdo;
    $this->artigos = new Item($pdo);
    $this->filaIa = new IaJob($pdo);
}
    // Permite à equipa administradora consultar todos os artigos.
    public function listarTodos(): void
    {
        exigirAdministrador($this->pdo);

        $artigos = $this->artigos->listarTodos();

        responderJson([
            'estado' => 'ok',
            'artigos' => $artigos
        ]);
    }

    // Guarda o achado e agenda a sua análise na mesma transação.
public function criarAchado(): void
{
    $utilizador = exigirAdministrador($this->pdo);
    exigirCsrf();

    $dados = ItemValidator::lerDadosArtigo(true, true);

    try {
        $this->pdo->beginTransaction();

        $id = $this->artigos->criarAchado(
            $utilizador['id'],
            $dados
        );

        // Os novos artigos começam na versão 1.
        $this->filaIa->agendar($id, 1);

        $this->pdo->commit();

    } catch (Throwable $erro) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        if (
            $erro instanceof PDOException
            && (int) ($erro->errorInfo[1] ?? 0) === 1452
        ) {
            responderJson([
                'erro' => 'Não foi possível associar o registo. '
                    . 'Confirma a categoria, o local e o ponto de recolha.'
            ], 422);
        }

        throw $erro;
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Objeto achado registado.',
        'item' => [
            'id' => $id,
            'tipo' => 'found',
            'estado' => 'ativo',
            'ia_estado' => 'pendente',
        ]
    ], 201);
}

// Edita o artigo e trata a IA e as reclamações na mesma transação.
public function atualizar(int $id): void
{
    exigirAdministrador($this->pdo);
    exigirCsrf();

    header('Cache-Control: private, no-store');

    $artigo = $this->artigos->procurarParaGestao($id);

    if ($artigo === null) {
        responderJson([
            'erro' => 'Artigo não encontrado.',
        ], 404);

        return;
    }

    $eAchado = $artigo['tipo'] === 'found';

    $eAchadoRecebido =
        $eAchado
        && $artigo['estado_recepcao'] === 'na_recepcao';

    $dados = ItemValidator::lerDadosArtigo(
        $eAchadoRecebido,
        $eAchado,
        $eAchado
    );

    try {
        $this->pdo->beginTransaction();

        $bloqueado = $this->artigos->procurarParaGestao(
            $id,
            true
        );

        if ($bloqueado === null) {
            throw new DomainException(
                'Artigo não encontrado.',
                404
            );
        }

        if ($bloqueado['estado'] !== 'ativo') {
            throw new DomainException(
                'Só é possível editar artigos ativos.',
                409
            );
        }

        if (
            $bloqueado['tipo'] !== $artigo['tipo']
            || $bloqueado['estado_recepcao']
                !== $artigo['estado_recepcao']
            || (int) $bloqueado['versao']
                !== (int) $artigo['versao']
        ) {
            throw new DomainException(
                'O artigo foi alterado entretanto. Atualiza os dados.',
                409
            );
        }

        $resultado = $this->artigos->atualizarPelaEquipa(
            $id,
            $dados
        );

        $efeitos = [
            'aprovacoes_revogadas' => 0,
            'codigos_cancelados' => 0,
        ];

        if ($eAchado && $resultado['versao_alterada']) {
            $reclamacoes = new Reclamacao($this->pdo);

            $efeitos = $reclamacoes->atualizarAposEdicaoDoAchado(
                $id,
                $resultado['versao_anterior'],
                $resultado['versao'],
                $resultado['pergunta_alterada']
            );
        }

        if ($resultado['reagendar_ia']) {
            $this->filaIa->agendar(
                $id,
                $resultado['versao']
            );
        }

        $this->pdo->commit();

    } catch (DomainException $erro) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        responderJson([
            'erro' => $erro->getMessage(),
        ], $erro->getCode());

        return;

    } catch (Throwable $erro) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        if ($erro instanceof PDOException) {
            $codigo = (int) ($erro->errorInfo[1] ?? 0);

            if ($codigo === 1452) {
                responderJson([
                    'erro' => 'Não foi possível associar o registo. '
                        . 'Confirma a categoria, o local '
                        . 'e o ponto de recolha.',
                ], 422);

                return;
            }

            if (in_array($codigo, [1213, 1205], true)) {
                responderJson([
                    'erro' => 'Ocorreu um conflito. '
                        . 'Atualiza os dados e tenta novamente.',
                ], 409);

                return;
            }
        }

        throw $erro;
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => $resultado['alterado']
            ? 'Artigo atualizado.'
            : 'Os dados já estavam atualizados.',
        'alterado' => $resultado['alterado'],
        'item' => [
            'id' => $id,
            'tipo' => $artigo['tipo'],
            'versao' => $resultado['versao'],
            'ia_estado' => $resultado['ia_estado'],
        ],
        'ia_agendada' => $resultado['reagendar_ia'],
        'aprovacoes_revogadas' => $efeitos['aprovacoes_revogadas'],
        'codigos_cancelados' => $efeitos['codigos_cancelados'],
    ]);
}

// Permite à equipa arquivar um artigo ativo.
public function arquivar(int $id): void
{
    $utilizador = exigirAdministrador($this->pdo);
    exigirCsrf();

    $artigo = $this->artigos->procurarPorId($id);

    if ($artigo === null) {
        responderJson([
            'erro' => 'Artigo não encontrado.'
        ], 404);
    }

    // Repetir o pedido para um artigo já arquivado mantém o resultado.
    if ($artigo['estado'] !== 'arquivado') {
        if ($artigo['estado'] !== 'ativo') {
            responderJson([
                'erro' => 'Só é possível arquivar artigos ativos.'
            ], 409);
        }

        $alterado = $this->artigos->arquivar($id);

        // Outra operação pode ter alterado o artigo entretanto.
        if (!$alterado) {
            responderJson([
                'erro' => 'O artigo foi alterado entretanto. '
                    . 'Atualiza os dados antes de tentar novamente.'
            ], 409);
        }
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Artigo arquivado.',
        'item' => [
            'id' => $id,
            'estado' => 'arquivado',
        ]
    ]);
}
    // Confirma a entrada do achado e agenda a análise da IA.
    public function confirmarEntrada(int $id): void
    {
        $admin = exigirAdministrador($this->pdo);
        exigirCsrf();

        $dados = RecepcaoValidator::lerDados();

        try {
            $this->pdo->beginTransaction();

            $artigo = $this->artigos->procurarParaAtualizacao($id);

            if ($artigo === null) {
                throw new DomainException(
                    'Artigo não encontrado.',
                    404
                );
            }

            if ($artigo['tipo'] !== 'found') {
                throw new DomainException(
                    'Só é possível confirmar a entrada de objetos achados.',
                    422
                );
            }

            if ($artigo['estado'] !== 'ativo') {
                throw new DomainException(
                    'Só é possível receber um objeto achado ativo.',
                    409
                );
            }

            if ($artigo['estado_recepcao'] !== 'por_entregar') {
                throw new DomainException(
                    'O objeto já foi recebido ou não está por entregar.',
                    409
                );
            }

            $confirmado = $this->artigos->confirmarEntrada(
                $id,
                (int) $admin['id'],
                $dados
            );

            if (!$confirmado) {
                throw new DomainException(
                    'O artigo foi alterado entretanto. Atualiza os dados.',
                    409
                );
            }

            $novaVersao = (int) $artigo['versao'] + 1;

            // A entrada e o trabalho da IA são guardados em conjunto.
            $this->filaIa->agendar($id, $novaVersao);

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

            if (
                $erro instanceof PDOException
                && (int) ($erro->errorInfo[1] ?? 0) === 1452
            ) {
                responderJson([
                    'erro' => 'Não foi possível associar a receção. '
                        . 'Confirma o ponto de recolha selecionado.'
                ], 422);

                return;
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Entrada na receção confirmada.',
            'item' => [
                'id' => $id,
                'tipo' => 'found',
                'estado' => 'ativo',
                'estado_recepcao' => 'na_recepcao',
                'ponto_recolha_id' => $dados['ponto_recolha_id'],
                'localizacao_stock' => $dados['localizacao_stock'],
                'recebido_por' => (int) $admin['id'],
                'versao' => $novaVersao,
                'ia_estado' => 'pendente',
            ],
        ]);
    }

    // Devolve os dados completos de gestão à equipa.
public function mostrar(int $id): void
{
    exigirAdministrador($this->pdo);

    header('Cache-Control: private, no-store');

    $artigo = $this->artigos->procurarParaGestao($id);

    if ($artigo === null) {
        responderJson([
            'erro' => 'Artigo não encontrado.',
        ], 404);

        return;
    }

    responderJson([
        'estado' => 'ok',
        'artigo' => $artigo,
    ]);
}

}