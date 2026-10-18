<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../helpers/sessao.php';
require_once __DIR__ . '/../middleware/autenticacao.php';

class AuthController
{
    private PDO $pdo;
    private User $utilizadores;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->utilizadores = new User($pdo);
    }

    public function login(): void
    {
        $tipoConteudo = strtolower(
            trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
        );

        if ($tipoConteudo !== 'application/json') {
            responderJson([
                'erro' => 'O pedido deve usar Content-Type: application/json.'
            ], 415);
        }

        try {
            $dados = json_decode(
                file_get_contents('php://input'),
                true,
                32,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $erro) {
            responderJson([
                'erro' => 'O JSON enviado é inválido.'
            ], 400);
        }

        if (
            !is_array($dados)
            || !isset($dados['email'], $dados['password'])
            || !is_string($dados['email'])
            || !is_string($dados['password'])
        ) {
            responderJson([
                'erro' => 'Envia o email e a password como texto.'
            ], 422);
        }

        $email = trim($dados['email']);
        $password = $dados['password'];

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 150
            || $password === ''
            || strlen($password) > 4096
        ) {
            responderJson([
                'erro' => 'Preenche um email válido e a password.'
            ], 422);
        }

        $utilizador = $this->autenticar($email, $password);

        if ($utilizador === null) {
            responderJson([
                'erro' => 'Email ou password incorretos.'
            ], 401);
            
        }

        try {
            iniciarSessao($this->pdo);

            if (!session_regenerate_id(true)) {
                throw new RuntimeException(
                    'Não foi possível renovar o identificador da sessão.'
                );
            }

            $_SESSION = [
                'user_id' => $utilizador['id'],
                'csrf_token' => bin2hex(random_bytes(32)),
            ];

            $csrfToken = $_SESSION['csrf_token'];

            if (!session_write_close()) {
                throw new RuntimeException(
                    'Não foi possível guardar a sessão.'
                );
            }

        } catch (Throwable $erro) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_abort();
            }

            error_log($erro->getMessage());

            responderJson([
                'erro' => 'Não foi possível criar a sessão.'
            ], 503);
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Login efetuado.',
            'utilizador' => $utilizador,
            'csrf_token' => $csrfToken,
        ]);
    }

    // Devolve os dados da conta associada à sessão atual.
    public function me(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        responderJson([
            'estado' => 'ok',
            'utilizador' => $utilizador
        ]);
    }


        // Valida o token CSRF e termina a sessão atual.
    public function logout(): void
    {
        iniciarSessao($this->pdo);

        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) || $userId <= 0) {
            session_abort();

            responderJson([
                'erro' => 'Não existe uma sessão autenticada.'
            ], 401);
        }

        // Compara o token recebido com o token guardado na sessão.
        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        $tokenPedido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (
            !is_string($tokenSessao)
            || $tokenSessao === ''
            || !hash_equals($tokenSessao, $tokenPedido)
        ) {
            session_abort();

            responderJson([
                'erro' => 'Token CSRF inválido.'
            ], 403);
        }

        $parametros = session_get_cookie_params();
        $nomeCookie = session_name();

        // Limpa os dados em memória e elimina a sessão na BD.
        $_SESSION = [];

        if (!session_destroy()) {
            throw new RuntimeException(
                'Não foi possível eliminar a sessão.'
            );
        }

        // Remove o cookie usando os mesmos parâmetros da sua criação.
        $cookieRemovido = setcookie($nomeCookie, '', [
            'expires' => time() - 3600,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'],
        ]);

        if (!$cookieRemovido) {
            throw new RuntimeException(
                'Não foi possível remover o cookie.'
            );
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Sessão terminada.'
        ]);
    }

    
    public function autenticar(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));

        $utilizador = $this->utilizadores->procurarPorEmail($email);

        if ($utilizador === null) {
            return null;
        }

        if ((int) $utilizador['ativo'] !== 1) {
            return null;
        }

        if (!password_verify($password, $utilizador['password_hash'])) {
            return null;
        }

        return [
            'id' => (int) $utilizador['id'],
            'nome' => $utilizador['nome'],
            'email' => $utilizador['email'],
            'role' => $utilizador['role'],
        ];
    }
}