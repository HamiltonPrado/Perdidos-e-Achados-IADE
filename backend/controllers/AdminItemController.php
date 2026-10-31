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
require_once __DIR__ . '/artigos/AdminItemRececao.php';
require_once __DIR__ . '/artigos/AdminItemGestao.php';

class AdminItemController
{
    use AdminItemRececao;
    use AdminItemGestao;

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