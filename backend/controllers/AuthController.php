<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    private User $utilizadores;

    public function __construct(PDO $pdo)
    {
        $this->utilizadores = new User($pdo);
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