<?php

declare(strict_types=1);

class DatabaseSessionHandler implements
    SessionHandlerInterface,
    SessionUpdateTimestampHandlerInterface
{
    private PDO $pdo;
    private int $duracao;

    // Regista quais as sessões que já existiam quando foram lidas.
    private array $existentes = [];

    public function __construct(PDO $pdo, int $duracao = 3600)
    {
        $this->pdo = $pdo;
        $this->duracao = $duracao;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $consulta = $this->pdo->prepare(
            'SELECT dados
             FROM sessions
             WHERE id = ?
               AND expira_em > NOW()'
        );

        $consulta->execute([$id]);

        $dados = $consulta->fetchColumn();

        $this->existentes[$id] = $dados !== false;

        return $dados === false ? '' : $dados;
    }

    public function write(string $id, string $data): bool
    {
        $userId = isset($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : null;

        if ($this->existentes[$id] ?? false) {
            $consulta = $this->pdo->prepare(
                'UPDATE sessions
                 SET user_id = ?,
                     dados = ?,
                     expira_em = DATE_ADD(NOW(), INTERVAL ? SECOND)
                 WHERE id = ?
                   AND expira_em > NOW()'
            );

            $consulta->execute([
                $userId,
                $data,
                $this->duracao,
                $id
            ]);
        } else {
            $consulta = $this->pdo->prepare(
                'INSERT INTO sessions (id, user_id, dados, expira_em)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
            );

            $consulta->execute([
                $id,
                $userId,
                $data,
                $this->duracao
            ]);
        }

        $this->existentes[$id] = true;

        return true;
    }

    public function destroy(string $id): bool
    {
        $consulta = $this->pdo->prepare(
            'DELETE FROM sessions WHERE id = ?'
        );

        $consulta->execute([$id]);

        // Impede que este pedido volte a inserir a sessão eliminada.
        $this->existentes[$id] = true;

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $consulta = $this->pdo->prepare(
            'DELETE FROM sessions WHERE expira_em <= NOW()'
        );

        $consulta->execute();

        return $consulta->rowCount();
    }

    public function validateId(string $id): bool
    {
        if (preg_match('/\A[a-zA-Z0-9,-]{22,128}\z/', $id) !== 1) {
            return false;
        }

        $consulta = $this->pdo->prepare(
            'SELECT 1
             FROM sessions
             WHERE id = ?
               AND expira_em > NOW()'
        );

        $consulta->execute([$id]);

        return $consulta->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}