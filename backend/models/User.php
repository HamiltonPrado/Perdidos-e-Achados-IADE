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

    public function procurarPorId(int $id): ?array
{
    $consulta = $this->pdo->prepare(
        'SELECT id, nome, email, role, ativo
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute([
        'id' => $id
    ]);

    $utilizador = $consulta->fetch();

    return $utilizador === false ? null : $utilizador;
}
}