<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../validators/ItemValidator.php';
require_once __DIR__ . '/../models/IaJob.php';
class ItemController
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

   // Devolve os artigos pertencentes ao utilizador autenticado.
    public function listarMeus(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        $artigos = $this->artigos->listarPorUtilizador(
            $utilizador['id']
        );

        responderJson([
            'estado' => 'ok',
            'artigos' => $artigos
        ]);
    }

        // Regista um objeto perdido associado ao utilizador autenticado.
    // Guarda o perdido e agenda a sua análise na mesma transação.
public function criarPerdido(): void
{
    $utilizador = exigirAutenticacao($this->pdo);
    exigirCsrf();

    $dados = ItemValidator::lerDadosArtigo();

    try {
        $this->pdo->beginTransaction();

        $id = $this->artigos->criarPerdido(
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
                    . 'Confirma a categoria e o local selecionados.'
            ], 422);
        }

        throw $erro;
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Objeto perdido registado.',
        'item' => [
            'id' => $id,
            'tipo' => 'lost',
            'estado' => 'ativo',
            'ia_estado' => 'pendente',
        ]
    ], 201);
}

        // Permite ao autor marcar o seu objeto perdido como resolvido.
    public function resolver(int $id): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        $artigo = $this->artigos->procurarPorId($id);

        // Apenas o autor pode executar esta operação.
        if (
            $artigo === null
            || (int) $artigo['user_id'] !== $utilizador['id']
        ) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        // A opção "Já encontrei" aplica-se apenas aos perdidos.
        if ($artigo['tipo'] !== 'lost') {
            responderJson([
                'erro' => 'Esta operação aplica-se apenas a objetos perdidos.'
            ], 409);
        }

        // Uma repetição do pedido mantém o resultado já alcançado.
        if ($artigo['estado'] !== 'resolvido') {
            if ($artigo['estado'] !== 'ativo') {
                responderJson([
                    'erro' => 'Só podes resolver um objeto perdido ativo.'
                ], 409);
            }

            $alterado = $this->artigos->marcarResolvido(
                $id,
                $utilizador['id']
            );

            // O estado pode ter mudado entre a consulta e a atualização.
            if (!$alterado) {
                responderJson([
                    'erro' => 'O artigo foi alterado entretanto. '
                        . 'Atualiza os dados antes de tentar novamente.'
                ], 409);
            }
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Objeto perdido marcado como resolvido.',
            'item' => [
                'id' => $id,
                'estado' => 'resolvido',
            ]
        ]);
    }

         // Devolve o detalhe respeitando as permissões de consulta.
    public function mostrar(int $id): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        $artigo = $this->artigos->procurarPorId($id);

        if ($artigo === null) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        $pertenceAoUtilizador =
            (int) $artigo['user_id'] === (int) $utilizador['id'];

        $eAdministrador = $utilizador['role'] === 'admin';

               $eArtigoDisponivel =
            $artigo['estado'] === 'ativo'
            && (
                $artigo['tipo'] === 'lost'
                || (
                    $artigo['tipo'] === 'found'
                    && $artigo['estado_recepcao'] === 'na_recepcao'
                )
            );

        if (
            !$pertenceAoUtilizador
            && !$eAdministrador
                       && !$eArtigoDisponivel
        ) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        // Limita os campos apresentados a outros alunos.
        if (!$pertenceAoUtilizador && !$eAdministrador) {
                        $camposVisiveis = array_flip([
                'id',
                'tipo',
                'titulo',
                'descricao',
                'category_id',
                'categoria',
                'location_id',
                'local',
                'ponto_recolha_id',
                'ponto_recolha',
                'data_ocorrencia',
                'estado',
                'estado_recepcao',
                'pergunta_secreta',
                'criado_em',
                'atualizado_em',

            ]);

            $artigo = array_intersect_key(
                $artigo,
                $camposVisiveis
            );
        }

        responderJson([
            'estado' => 'ok',
            'artigo' => $artigo,
        ]);
    }

        // Permite aos utilizadores autenticados consultar os perdidos ativos.
    public function listarPerdidos(): void
    {
        exigirAutenticacao($this->pdo);

        $pagina = filter_var(
            $_GET['pagina'] ?? '1',
            FILTER_VALIDATE_INT,
            ['options' => [
                'min_range' => 1,
                'max_range' => 1000000,
            ]]
        );

        if ($pagina === false) {
            responderJson([
                'erro' => 'Página inválida.'
            ], 422);
        }

        $limite = 20;
        $offset = ($pagina - 1) * $limite;

        $artigos = $this->artigos->listarPerdidosAtivos(
            $limite,
            $offset
        );

        responderJson([
            'estado' => 'ok',
            'artigos' => $artigos,
            'paginacao' => [
                'pagina' => $pagina,
                'por_pagina' => $limite,
            ],
        ]);
    }

        // Regista um achado que o aluno ainda vai entregar à equipa.
    public function criarAchado(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        // O aluno não define o ponto de recolha nem os dados da receção.
        $dados = ItemValidator::lerDadosArtigo();

        try {
            $id = $this->artigos->criarAchadoPorAluno(
                (int) $utilizador['id'],
                $dados
            );
        } catch (PDOException $erro) {
            if ((int) ($erro->errorInfo[1] ?? 0) === 1452) {
                responderJson([
                    'erro' => 'Não foi possível associar o registo. '
                        . 'Confirma a categoria e o local selecionados.'
                ], 422);

                return;
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Objeto registado. Entrega-o à equipa '
                . 'para confirmar a entrada na receção.',
            'item' => [
                'id' => $id,
                'tipo' => 'found',
                'estado' => 'ativo',
                'estado_recepcao' => 'por_entregar',
            ],
        ], 201);
    }

}