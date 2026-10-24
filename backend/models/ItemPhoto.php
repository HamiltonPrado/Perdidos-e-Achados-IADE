<?php

declare(strict_types=1);

class ItemPhoto
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Guarda uma fotografia associada a um artigo.
    public function criar(
        int $itemId,
        string $mime,
        string $dados
    ): int {
        $consulta = $this->pdo->prepare(
            'INSERT INTO item_photos (
                item_id,
                mime,
                dados
            ) VALUES (
                :item_id,
                :mime,
                :dados
            )'
        );

        $consulta->bindValue(
            ':item_id',
            $itemId,
            PDO::PARAM_INT
        );

        $consulta->bindValue(
            ':mime',
            $mime,
            PDO::PARAM_STR
        );

        $consulta->bindValue(
            ':dados',
            $dados,
            PDO::PARAM_LOB
        );

        $consulta->execute();

        return (int) $this->pdo->lastInsertId();
    }

    // Lista as fotografias de um artigo sem carregar os dados binários.
public function listarPorArtigo(int $itemId): array
{
    $consulta = $this->pdo->prepare(
        'SELECT id, item_id, mime, criado_em
         FROM item_photos
         WHERE item_id = :item_id
         ORDER BY criado_em ASC, id ASC'
    );

    $consulta->execute([
        'item_id' => $itemId
    ]);

    return $consulta->fetchAll();
}

// Obtém uma fotografia com os dados necessários para a visualizar.
public function procurarPorId(int $id): ?array
{
    $consulta = $this->pdo->prepare(
        'SELECT id, item_id, mime, dados
         FROM item_photos
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute([
        'id' => $id
    ]);

    $foto = $consulta->fetch();

    return $foto === false ? null : $foto;
}

// Remove uma fotografia associada ao artigo indicado.
public function apagar(int $id, int $itemId): bool
{
    $consulta = $this->pdo->prepare(
        'DELETE FROM item_photos
         WHERE id = :id
           AND item_id = :item_id'
    );

    $consulta->execute([
        'id' => $id,
        'item_id' => $itemId,
    ]);

    return $consulta->rowCount() === 1;
}

}