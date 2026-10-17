<?php

declare(strict_types=1);

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function procurarPorEmail(string $email): ?array
    {
        $sql = '
            SELECT id, nome, email, password_hash, role, ativo
            FROM users
            WHERE email = :email
            LIMIT 1
        ';

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            'email' => $email
        ]);

        $utilizador = $consulta->fetch();

        return $utilizador === false ? null : $utilizador;
    }
}