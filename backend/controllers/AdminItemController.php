<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../validators/ItemValidator.php';
class AdminItemController
{
    private PDO $pdo;
    private Item $artigos;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
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

    // Regista um objeto achado, com acesso reservado à equipa.
public function criarAchado(): void
{
    $utilizador = exigirAdministrador($this->pdo);
    exigirCsrf();

    // Valida os dados e exige um ponto de recolha.
    $dados = ItemValidator::lerDadosArtigo(true);

    try {
        $id = $this->artigos->criarAchado(
            $utilizador['id'],
            $dados
        );
    } catch (PDOException $erro) {
        if ((int) ($erro->errorInfo[1] ?? 0) === 1452) {
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


}