<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/administrador.php';

// Estado da base de dados para a equipa e para o laboratório da API (testar-api.html):
// que nós existem, quais respondem, se estão sincronizados (Galera) e se têm os mesmos dados.
class EstadoBdController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // GET /api/health/cluster — só para a equipa (expõe nomes internos dos servidores).
    public function cluster(): void
    {
        exigirAdministrador($this->pdo);

        $config = require __DIR__ . '/../config/configuracao_bd.php';

        // O nó que esta ligação (a do pedido) está a usar.
        $emUso = (string) $this->pdo->query('SELECT @@hostname')->fetchColumn();

        $nos = array_map(
            fn (string $host): array => $this->estadoDoNo($host, $config, $emUso),
            $config['hosts']
        );

        $galera = array_filter($nos, fn (array $no): bool => $no['galera'] !== null);
        $acessiveis = array_filter($nos, fn (array $no): bool => $no['acessivel']);

        // Com os mesmos dados, todos os nós acessíveis contam o mesmo e têm o mesmo maior id.
        $assinaturas = array_unique(array_map(
            fn (array $no): string => json_encode($no['dados']),
            $acessiveis
        ));

        responderJson([
            'estado' => 'ok',
            'web' => gethostname(),
            'bd_em_uso' => $emUso,
            'tipo' => $galera ? 'galera' : (count($nos) > 1 ? 'varias' : 'unica'),
            'nos' => $nos,
            'resumo' => [
                'total' => count($nos),
                'acessiveis' => count($acessiveis),
                'sincronizados' => count(array_filter(
                    $nos,
                    fn (array $no): bool => ($no['galera']['local_state'] ?? null) === 'Synced'
                )),
                'dados_iguais' => count($assinaturas) <= 1,
            ],
            'verificado_em' => date('c'),
        ]);
    }

    private function estadoDoNo(string $host, array $config, string $emUso): array
    {
        $inicio = microtime(true);
        $no = [
            'host' => $host,
            'acessivel' => false,
            'servidor' => null,
            'em_uso' => false,
            'so_leitura' => null,
            'galera' => null,
            'dados' => null,
            'latencia_ms' => null,
            'erro' => null,
        ];

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $config['port'], $config['database']),
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 1,
                ]
            );

            $base = $pdo->query('SELECT @@hostname AS servidor, @@GLOBAL.read_only AS so_leitura')->fetch();
            $no['acessivel'] = true;
            $no['servidor'] = $base['servidor'];
            $no['em_uso'] = $base['servidor'] === $emUso;
            $no['so_leitura'] = (int) $base['so_leitura'] === 1;

            $estado = [];
            foreach ($pdo->query(
                "SHOW GLOBAL STATUS WHERE Variable_name IN ('wsrep_cluster_status', 'wsrep_cluster_size',
                 'wsrep_local_state_comment', 'wsrep_ready', 'wsrep_last_committed')"
            ) as $linha) {
                $estado[$linha['Variable_name']] = $linha['Value'];
            }

            if ($estado !== []) {
                $no['galera'] = [
                    'cluster_status' => $estado['wsrep_cluster_status'] ?? null,
                    'cluster_size' => isset($estado['wsrep_cluster_size']) ? (int) $estado['wsrep_cluster_size'] : null,
                    'local_state' => $estado['wsrep_local_state_comment'] ?? null,
                    'ready' => ($estado['wsrep_ready'] ?? '') === 'ON',
                    'last_committed' => isset($estado['wsrep_last_committed']) ? (int) $estado['wsrep_last_committed'] : null,
                ];
            }

            // Um nó sem maioria recusa consultas aos dados: fica sem contagens.
            if ($no['galera'] === null || $no['galera']['ready']) {
                $no['dados'] = $pdo->query(
                    'SELECT (SELECT COUNT(*) FROM users) AS users,
                            (SELECT COUNT(*) FROM items) AS artigos,
                            (SELECT COALESCE(MAX(id), 0) FROM items) AS maior_id_artigo,
                            (SELECT COUNT(*) FROM reclamacoes) AS reclamacoes'
                )->fetch();
                $no['dados'] = array_map('intval', $no['dados']);
            }
        } catch (PDOException $erro) {
            $no['erro'] = $no['acessivel']
                ? 'Responde, mas recusa consultas (sem maioria no cluster?).'
                : 'Sem resposta.';
        }

        $no['latencia_ms'] = (int) round((microtime(true) - $inicio) * 1000);

        return $no;
    }
}
