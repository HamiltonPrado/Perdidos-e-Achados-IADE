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

require_once __DIR__ . '/reclamacoes/ReclamacaoCriacao.php';
require_once __DIR__ . '/reclamacoes/ReclamacaoDecisao.php';
require_once __DIR__ . '/reclamacoes/ReclamacaoReabertura.php';

class ReclamacaoController
{
    use ReclamacaoCriacao;
    use ReclamacaoDecisao;
    use ReclamacaoReabertura;

    private PDO $pdo;
    private Item $artigos;
    private Reclamacao $reclamacoes;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
        $this->reclamacoes = new Reclamacao($pdo);
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
// Devolve as reclamações do utilizador autenticado.
public function listarMinhas(): void
{
    $utilizador = exigirAutenticacao($this->pdo);

    header('Cache-Control: private, no-store');

    $reclamacoes = $this->reclamacoes->listarPorUtilizador(
        (int) $utilizador['id']
    );

    responderJson([
        'estado' => 'ok',
        'reclamacoes' => $reclamacoes,
    ]);
}



}