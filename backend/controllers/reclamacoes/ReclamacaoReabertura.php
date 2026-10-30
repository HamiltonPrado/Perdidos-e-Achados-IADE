<?php

declare(strict_types=1);

trait ReclamacaoReabertura
{

// Permite à equipa autorizar uma nova tentativa após uma recusa.
public function reabrir(int $id): void
{
    $admin = exigirAdministrador($this->pdo);
    exigirCsrf();

    header('Cache-Control: private, no-store');

    $referencia = $this->reclamacoes->procurarPorId($id);

    if ($referencia === null) {
        responderJson([
            'erro' => 'Reclamação não encontrada.',
        ], 404);

        return;
    }

    $achadoId = (int) $referencia['item_achado_id'];

    try {
        $this->pdo->beginTransaction();

        // Mantém a ordem: primeiro o artigo, depois a reclamação.
        $achado = $this->artigos->procurarParaAtualizacao(
            $achadoId
        );

        $reclamacao = $this->reclamacoes->procurarPorId(
            $id,
            true
        );

        if ($achado === null || $reclamacao === null) {
            throw new DomainException(
                'O objeto ou a reclamação não foi encontrado.',
                404
            );
        }

        if ((int) $reclamacao['item_achado_id'] !== $achadoId) {
            throw new DomainException(
                'A reclamação foi alterada. Atualiza os dados.',
                409
            );
        }

        if ($reclamacao['estado'] !== 'recusada') {
            throw new DomainException(
                'Só é possível reabrir reclamações recusadas.',
                409
            );
        }

        if (
            $achado['tipo'] !== 'found'
            || $achado['estado'] !== 'ativo'
            || $achado['estado_recepcao'] !== 'na_recepcao'
            || $achado['ponto_recolha_id'] === null
        ) {
            throw new DomainException(
                'O objeto já não está disponível para reclamação.',
                409
            );
        }

        if (
            !is_string($achado['pergunta_secreta'])
            || trim($achado['pergunta_secreta']) === ''
        ) {
            throw new DomainException(
                'Define primeiro a pergunta secreta do objeto.',
                409
            );
        }

        if ($this->reclamacoes->existeOutraAprovada(
            $achadoId,
            $id
        )) {
            throw new DomainException(
                'Já existe outra reclamação aprovada para este objeto.',
                409
            );
        }

        if (!$this->reclamacoes->reabrir(
            $id,
            (int) $admin['id']
        )) {
            throw new DomainException(
                'Não foi possível reabrir a reclamação.',
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
                'erro' => $erro->getMessage(),
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
                    . 'Atualiza os dados e tenta novamente.',
            ], 409);

            return;
        }

        throw $erro;
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Reclamação reaberta. '
            . 'O aluno pode enviar uma nova resposta.',
        'reclamacao' => [
            'id' => $id,
            'item_achado_id' => $achadoId,
            'estado' => 'reaberta',
            'reaberta_por' => (int) $admin['id'],
        ],
    ]);
}

}
