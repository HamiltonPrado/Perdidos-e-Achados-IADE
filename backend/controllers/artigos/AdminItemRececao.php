<?php

declare(strict_types=1);

trait AdminItemRececao
{

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

}
