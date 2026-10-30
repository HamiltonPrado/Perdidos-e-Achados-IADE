<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Devolucao.php';
require_once __DIR__ . '/../models/Correspondencia.php';
require_once __DIR__ . '/../validators/DevolucaoValidator.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../models/PedidoLevantamento.php';

require_once __DIR__ . '/devolucoes/DevolucaoManual.php';
require_once __DIR__ . '/devolucoes/DevolucaoPorCodigo.php';

class DevolucaoController
{
    use DevolucaoManual;
    use DevolucaoPorCodigo;

    private PDO $pdo;
    private Item $artigos;
    private User $utilizadores;
    private Devolucao $devolucoes;
    private Correspondencia $correspondencias;
     private PedidoLevantamento $pedidosLevantamento;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
        $this->utilizadores = new User($pdo);
        $this->devolucoes = new Devolucao($pdo);
        $this->correspondencias = new Correspondencia($pdo);
        $this->pedidosLevantamento = new PedidoLevantamento($pdo);
    }

    // Bloqueia os artigos envolvidos, dentro de uma transação.
    private function bloquearArtigos(
        int $achadoId,
        ?int $perdidoId
    ): array {
        if ($perdidoId === $achadoId) {
            throw new DomainException(
                'O perdido e o achado devem ser artigos diferentes.',
                422
            );
        }

        $ids = [$achadoId];

        if ($perdidoId !== null) {
            $ids[] = $perdidoId;
        }

        // Todos os pedidos bloqueiam os artigos pela mesma ordem.
        sort($ids, SORT_NUMERIC);

        $artigos = [];

        foreach ($ids as $id) {
            $artigo = $this->artigos->procurarParaAtualizacao($id);

            if ($artigo === null) {
                throw new DomainException(
                    'Um dos artigos indicados não foi encontrado.',
                    404
                );
            }

            $artigos[$id] = $artigo;
        }

        return $artigos;
    }

    // Permite à equipa consultar o histórico de devoluções.
public function listarTodos(): void
{
    exigirAdministrador($this->pdo);

    $devolucoes = $this->devolucoes->listarTodos();

    responderJson([
        'estado' => 'ok',
        'devolucoes' => $devolucoes
    ]);
}

}