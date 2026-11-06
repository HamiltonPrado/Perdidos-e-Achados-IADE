<?php

declare(strict_types=1);

require_once __DIR__ . '/item/ItemConsultas.php';

require_once __DIR__ . '/item/ItemRegistos.php';
require_once __DIR__ . '/item/ItemGestao.php';

class Item
{
    use ItemRegistos;
    use ItemGestao;

    use ItemConsultas;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Transforma o ID do perdido numa referência fácil de comunicar à receção.
    // Por exemplo, o ID 37 fica PER-000037. Esta referência não permite levantar o objeto.
    public static function formatarReferenciaPerdido(int $id): string
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('O ID do perdido deve ser positivo.');
        }

        return 'PER-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    // Cria a referência de inventário do achado recebido pela equipa.
    // Por exemplo, o ID 242 fica ACH-000242. Os zeros não cortam IDs maiores.
    public static function formatarReferenciaAchado(int $id): string
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('O ID do achado deve ser positivo.');
        }

        return 'ACH-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    // Calcula a referência pelo ID, incluindo nos registos antigos.
    // Um achado por entregar ainda não tem referência de inventário.
    private function adicionarReferencia(array $artigo): array
    {
        if ($artigo['tipo'] === 'lost') {
            $artigo['referencia'] = self::formatarReferenciaPerdido((int) $artigo['id']);
        } elseif (
            $artigo['tipo'] === 'found'
            && $artigo['estado_recepcao'] === 'na_recepcao'
        ) {
            $artigo['referencia'] = self::formatarReferenciaAchado((int) $artigo['id']);
        }

        return $artigo;
    }

}
