<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/FotoItem.php';
require_once __DIR__ . '/../validators/FotoValidator.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../models/TarefaIa.php';

class FotoController
{
    private PDO $pdo;
    private Item $artigos;
    private FotoItem $fotografias;
    private TarefaIa $filaIa;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
        $this->fotografias = new FotoItem($pdo);
        $this->filaIa = new TarefaIa($pdo);
    }

    // Adiciona uma fotografia a um artigo ativo.
    public function adicionar(int $itemId): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        // Verifica a fotografia antes de iniciar as alterações na base de dados.
        $foto = FotoValidator::lerFotografia();

        try {
            $this->pdo->beginTransaction();

            // Impede outras operações de alterarem o artigo enquanto esta guarda a fotografia.
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

            // Guarda a fotografia; mais abaixo decide se já pode agendar a análise da IA.
            $fotoId = $this->fotografias->criar(
                $itemId,
                $foto['mime'],
                $foto['dados']
            );

            $this->artigos->marcarAnalisePendente($itemId);

            $novaVersao = (int) $artigo['versao'] + 1;

            // Não agenda a IA para achados ainda por entregar;
            // a análise fica para depois da confirmação da receção.
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

    // Mostra a imagem apenas a quem tem permissão para consultar o artigo.
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

        // Indica ao browser que a resposta é uma imagem, em vez de texto JSON.
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

            // Impede outras operações de alterarem o artigo enquanto remove a fotografia.
            $artigo = $this->artigos->procurarParaAtualizacao(
                $itemId
            );

            if ($artigo === null) {
                $this->pdo->rollBack();

                responderJson([
                    'erro' => 'Fotografia não encontrada.'
                ], 404);
            }

            // Só a equipa ou um autor com permissão para adicionar fotos pode removê-las.
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

            // Se outra operação já apagou esta fotografia, não regista uma segunda remoção.
            if (!$apagada) {
                $this->pdo->rollBack();

                responderJson([
                    'erro' => 'Fotografia não encontrada.'
                ], 404);
            }

            // Prepara uma nova análise com as fotos que ficaram;
            // o agendamento abaixo respeita o estado de receção do objeto.
            $this->artigos->marcarAnalisePendente($itemId);

            $novaVersao = (int) $artigo['versao'] + 1;

            // Não agenda a IA para achados ainda por entregar;
            // a análise fica para depois da confirmação da receção.
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

    // Permite consultar a foto ao autor, à equipa e a quem pode ver o artigo no catálogo.
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
