<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../validators/ItemValidator.php';
class ItemController
{
    private PDO $pdo;
    private Item $artigos;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
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
    public function criarPerdido(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        $dados = ItemValidator::lerDadosArtigo();

        try {
            $id = $this->artigos->criarPerdido(
                $utilizador['id'],
                $dados
            );
        } catch (PDOException $erro) {
            if ((int) ($erro->errorInfo[1] ?? 0) === 1452) {
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

        // Regista um objeto achado, com acesso reservado à equipa.

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

        // Devolve um artigo ao seu autor ou à equipa administradora.
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
            (int) $artigo['user_id'] === $utilizador['id'];

        $eAdministrador = $utilizador['role'] === 'admin';

        // Usa também 404 para não revelar artigos de outras pessoas.
        if (!$pertenceAoUtilizador && !$eAdministrador) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        responderJson([
            'estado' => 'ok',
            'artigo' => $artigo
        ]);
    }
}