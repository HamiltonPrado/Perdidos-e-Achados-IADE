<?php

declare(strict_types=1);

trait AdminItemGestao
{

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

}
