<?php

declare(strict_types=1);

class Utilizador
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Endereço da fotografia de perfil, ou null se não tiver.
    // A data vai no endereço para o browser ir buscar a nova quando a foto muda.
    public static function urlFoto(array $utilizador): ?string
    {
        $data = $utilizador['foto_atualizada_em'] ?? null;

        if (!is_string($data) || $data === '') {
            return null;
        }

        return '/api/me/photo?v=' . rawurlencode((string) strtotime($data));
    }

    // Dados públicos da conta, como a API os devolve.
    public static function paraResposta(array $utilizador): array
    {
        return [
            'id' => (int) $utilizador['id'],
            'nome' => $utilizador['nome'],
            'email' => $utilizador['email'],
            'role' => $utilizador['role'],
            'foto' => self::urlFoto($utilizador),
        ];
    }

    public function procurarPorEmail(string $email): ?array
    {
        $sql = '
            SELECT id, nome, email, password_hash, role, ativo, foto_atualizada_em
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

    // Não lê a fotografia em si (pode ter 2 MB): só a data, para montar o endereço.
    public function procurarPorId(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, nome, email, role, ativo, foto_atualizada_em
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

    public function atualizarNome(int $id, string $nome): void
    {
        $this->pdo->prepare('UPDATE users SET nome = :nome WHERE id = :id')
            ->execute(['nome' => $nome, 'id' => $id]);
    }

    public function guardarFoto(int $id, string $mime, string $dados): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE users
             SET foto = :foto, foto_mime = :mime, foto_atualizada_em = NOW()
             WHERE id = :id'
        );

        $consulta->bindValue('foto', $dados, PDO::PARAM_LOB);
        $consulta->bindValue('mime', $mime);
        $consulta->bindValue('id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    public function removerFoto(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE users SET foto = NULL, foto_mime = NULL, foto_atualizada_em = NULL WHERE id = :id'
        )->execute(['id' => $id]);
    }

    // Devolve ['mime', 'dados'] da fotografia de perfil, ou null se não tiver.
    public function lerFoto(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT foto_mime AS mime, foto AS dados
             FROM users
             WHERE id = :id AND foto IS NOT NULL
             LIMIT 1'
        );

        $consulta->execute(['id' => $id]);

        $foto = $consulta->fetch();

        return $foto === false ? null : $foto;
    }
}
