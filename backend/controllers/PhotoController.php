<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/ItemPhoto.php';
require_once __DIR__ . '/../validators/PhotoValidator.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../models/IaJob.php';

class PhotoController
{
    private PDO $pdo;
    private Item $artigos;
    private ItemPhoto $fotografias;
    private IaJob $filaIa;

   public function __construct(PDO $pdo)
{
    $this->pdo = $pdo;
    $this->artigos = new Item($pdo);
    $this->fotografias = new ItemPhoto($pdo);
    $this->filaIa = new IaJob($pdo);
}

    // Adiciona uma fotografia a um artigo ativo.
    public function adicionar(int $itemId): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        // Valida o ficheiro antes de abrir a transação.
        $foto = PhotoValidator::lerFotografia();

        try {
            $this->pdo->beginTransaction();

            // Bloqueia o artigo até terminar a operação.
            $artigo = $this->artigos->procurarParaAtualizacao(
                $itemId
            );

            if ($artigo === null) {
                $this->pdo->rollBack();

                responderJson([
                    'erro' => 'Artigo não encontrado.'
                ], 404);
            }

            // A equipa pode alterar fotografias de artigos ativos.
            // O autor pode alterar as dos seus perdidos
            // ou dos seus achados ainda por entregar.
            $eAdministrador = $utilizador['role'] === 'admin';

                        $eArtigoDoUtilizador =
                (int) $artigo['user_id'] === (int) $utilizador['id']
                && (
                    $artigo['tipo'] === 'lost'
                    || (
                        $artigo['tipo'] === 'found'
                        && $artigo['estado_recepcao'] === 'por_entregar'
                    )
                );

            if (!$eAdministrador && !$eArtigoDoUtilizador) {
                $this->pdo->rollBack();

                responderJson([
                    'erro' => 'Artigo não encontrado.'
                ], 404);
            }

            if ($artigo['estado'] !== 'ativo') {
                $this->pdo->rollBack();

                responderJson([
                    'erro' => 'Só é possível adicionar fotografias '
                        . 'a artigos ativos.'
                ], 409);
            }

            // Guarda a fotografia e solicita uma nova análise da IA.
            $fotoId = $this->fotografias->criar(
                $itemId,
                $foto['mime'],
                $foto['dados']
            );

                   $this->artigos->marcarAnalisePendente($itemId);

        $novaVersao = (int) $artigo['versao'] + 1;

        // Achados por entregar só serão analisados após a receção.
        $podeAgendarIa =
            $artigo['tipo'] === 'lost'
            || (
                $artigo['tipo'] === 'found'
                && $artigo['estado_recepcao'] === 'na_recepcao'
            );

        if ($podeAgendarIa) {
            $this->filaIa->agendar($itemId, $novaVersao);
        }
            $this->pdo->commit();

        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Fotografia adicionada.',
            'foto' => [
                'id' => $fotoId,
                'item_id' => $itemId,
                'mime' => $foto['mime'],
            ]
        ], 201);
    }

    // Lista as fotografias de um artigo acessível ao utilizador.
public function listar(int $itemId): void
{
    $utilizador = exigirAutenticacao($this->pdo);

    $artigo = $this->artigos->procurarPorId($itemId);

        if (!$this->podeConsultarArtigo($artigo, $utilizador)) {
        responderJson([
            'erro' => 'Artigo não encontrado.'
        ], 404);
    }

    $fotos = $this->fotografias->listarPorArtigo($itemId);

    responderJson([
        'estado' => 'ok',
        'fotos' => $fotos
    ]);
}

// Devolve a imagem ao autor do artigo ou à equipa.
public function mostrar(int $id): void
{
    $utilizador = exigirAutenticacao($this->pdo);

    $foto = $this->fotografias->procurarPorId($id);

    if ($foto === null) {
        responderJson([
            'erro' => 'Fotografia não encontrada.'
        ], 404);
    }

    $artigo = $this->artigos->procurarPorId(
        (int) $foto['item_id']
    );

       if (!$this->podeConsultarArtigo($artigo, $utilizador)) {
        responderJson([
            'erro' => 'Fotografia não encontrada.'
        ], 404);
    }


    // Substitui o Content-Type JSON pelo tipo da imagem.
    http_response_code(200);
    header('Content-Type: ' . $foto['mime']);
    header('Content-Disposition: inline');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    echo $foto['dados'];
    exit;
}

// Remove uma fotografia de um artigo ativo.
public function apagar(int $id): void
{
    $utilizador = exigirAutenticacao($this->pdo);
    exigirCsrf();

    $foto = $this->fotografias->procurarPorId($id);

    if ($foto === null) {
        responderJson([
            'erro' => 'Fotografia não encontrada.'
        ], 404);
    }

    $itemId = (int) $foto['item_id'];

    try {
        $this->pdo->beginTransaction();

        // Bloqueia o artigo enquanto verifica e executa a remoção.
        $artigo = $this->artigos->procurarParaAtualizacao(
            $itemId
        );

        if ($artigo === null) {
            $this->pdo->rollBack();

            responderJson([
                'erro' => 'Fotografia não encontrada.'
            ], 404);
        }

        // Aplica as mesmas permissões usadas no upload.
                $eAdministrador = $utilizador['role'] === 'admin';

        $eArtigoDoUtilizador =
            (int) $artigo['user_id'] === (int) $utilizador['id']
            && (
                $artigo['tipo'] === 'lost'
                || (
                    $artigo['tipo'] === 'found'
                    && $artigo['estado_recepcao'] === 'por_entregar'
                )
            );

               if (!$eAdministrador && !$eArtigoDoUtilizador) {
            $this->pdo->rollBack();

            responderJson([
                'erro' => 'Fotografia não encontrada.'
            ], 404);
        }

        if ($artigo['estado'] !== 'ativo') {
            $this->pdo->rollBack();

            responderJson([
                'erro' => 'Só é possível remover fotografias '
                    . 'de artigos ativos.'
            ], 409);
        }

        $apagada = $this->fotografias->apagar($id, $itemId);

        // A fotografia pode ter sido removida por outro pedido.
        if (!$apagada) {
            $this->pdo->rollBack();

            responderJson([
                'erro' => 'Fotografia não encontrada.'
            ], 404);
        }

        // Solicita uma nova análise com as fotografias restantes.
               $this->artigos->marcarAnalisePendente($itemId);

        $novaVersao = (int) $artigo['versao'] + 1;

        // Achados por entregar só serão analisados após a receção.
        $podeAgendarIa =
            $artigo['tipo'] === 'lost'
            || (
                $artigo['tipo'] === 'found'
                && $artigo['estado_recepcao'] === 'na_recepcao'
            );

        if ($podeAgendarIa) {
            $this->filaIa->agendar($itemId, $novaVersao);
        }
        $this->pdo->commit();

    } catch (Throwable $erro) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        throw $erro;
    }

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Fotografia removida.',
        'foto' => [
            'id' => $id,
            'item_id' => $itemId,
        ]
    ]);
}


        // Autoriza a consulta pelo autor, pela equipa ou pelo catálogo.
    private function podeConsultarArtigo(
        ?array $artigo,
        array $utilizador
    ): bool {
        if ($artigo === null) {
            return false;
        }

        $eAutor =
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

        return $eAutor || $eAdministrador || $eArtigoDisponivel;
    }
}