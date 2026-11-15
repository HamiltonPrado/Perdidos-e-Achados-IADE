<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Utilizador.php';
require_once __DIR__ . '/../validators/PerfilValidator.php';
require_once __DIR__ . '/../validators/FotoValidator.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../helpers/resposta.php';

// Perfil da conta com sessão iniciada: editar o nome e a fotografia.
// O email e o perfil (aluno/equipa) não se editam aqui.
class PerfilController
{
    private PDO $pdo;
    private Utilizador $utilizadores;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->utilizadores = new Utilizador($pdo);
    }

    // PATCH /api/me
    //   multipart/form-data: nome, foto (JPEG/PNG até 2 MB, opcional), removerFoto=true (opcional)
    //   ou application/json: { "nome": "…", "removerFoto": true }
    public function atualizar(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        [$campos, $temFoto] = $this->lerPedido();

        $nome = array_key_exists('nome', $campos)
            ? PerfilValidator::validarNome($campos['nome'])
            : null;

        $removerFoto = in_array($campos['removerFoto'] ?? null, [true, 'true', '1', 1], true);

        if ($temFoto && $removerFoto) {
            responderJson(['erro' => 'Envia uma foto nova ou pede para a remover, não as duas coisas.'], 422);
        }

        if ($nome === null && !$temFoto && !$removerFoto) {
            responderJson(['erro' => 'Não há nada para alterar: envia o nome, uma foto ou removerFoto.'], 422);
        }

        // Valida a foto antes de gravar o que quer que seja.
        $foto = $temFoto ? FotoValidator::lerFotografia() : null;

        $id = $utilizador['id'];

        $this->pdo->beginTransaction();

        try {
            if ($nome !== null) {
                $this->utilizadores->atualizarNome($id, $nome);
            }

            if ($foto !== null) {
                $this->utilizadores->guardarFoto($id, $foto['mime'], $foto['dados']);
            } elseif ($removerFoto) {
                $this->utilizadores->removerFoto($id);
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }

        $atualizado = $this->utilizadores->procurarPorId($id);

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Perfil atualizado.',
            'utilizador' => Utilizador::paraResposta($atualizado),
        ]);
    }

    // GET /api/me/photo — a fotografia de perfil de quem tem sessão iniciada.
    public function mostrarFoto(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        $foto = $this->utilizadores->lerFoto($utilizador['id']);

        if ($foto === null) {
            responderJson(['erro' => 'Esta conta não tem fotografia de perfil.'], 404);
        }

        http_response_code(200);
        header('Content-Type: ' . $foto['mime']);
        header('Content-Disposition: inline');
        // O endereço muda quando a foto muda (?v=…), por isso pode ficar guardada no browser.
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');

        echo $foto['dados'];
        exit;
    }

    // Lê os campos do pedido. O PHP só preenche $_POST/$_FILES em pedidos POST;
    // num PATCH com multipart é preciso pedir-lhe que leia o corpo (PHP 8.4+).
    private function lerPedido(): array
    {
        $tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));

        if ($tipo === 'application/json') {
            try {
                $dados = json_decode(file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                responderJson(['erro' => 'O JSON enviado é inválido.'], 400);
            }

            if (!is_array($dados)) {
                responderJson(['erro' => 'O JSON enviado é inválido.'], 400);
            }

            return [$dados, false];
        }

        if ($tipo === 'multipart/form-data') {
            try {
                [$_POST, $_FILES] = request_parse_body();
            } catch (RequestParseBodyException) {
                responderJson(['erro' => 'Não foi possível ler o formulário enviado (a foto pode ser grande demais).'], 413);
            }

            $temFoto = isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            return [$_POST, $temFoto];
        }

        responderJson([
            'erro' => 'O pedido deve usar multipart/form-data ou application/json.'
        ], 415);
    }
}
