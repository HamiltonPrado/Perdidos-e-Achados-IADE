<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Notificacao.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';

class NotificacaoController
{
    private PDO $pdo;
    private Notificacao $notificacoes;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->notificacoes = new Notificacao($pdo);
    }

    // Devolve apenas as notificações do utilizador autenticado.
    public function listar(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        // Correspondências da IA + decisões da receção, das mais recentes para as mais antigas.
        // O campo `tipo` distingue-as: correspondencia, pedido_aprovado, pedido_recusado, pedido_reaberto.
        $notificacoes = array_merge(
            $this->notificacoes->listarPorUtilizador($utilizador['id']),
            $this->notificacoes->listarPedidosPorUtilizador($utilizador['id'])
        );

        usort(
            $notificacoes,
            fn (array $a, array $b): int =>
                [$b['criado_em'], (int) $b['id']] <=> [$a['criado_em'], (int) $a['id']]
        );

        responderJson([
            'estado' => 'ok',
            'notificacoes' => $notificacoes
        ]);
    }

    // Marca uma notificação como lida pelo seu destinatário.
    public function marcarLida(int $id): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        $encontrada = $this->notificacoes->marcarLida(
            $id,
            $utilizador['id']
        );

        if (!$encontrada) {
            responderJson([
                'erro' => 'Notificação não encontrada.'
            ], 404);
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Notificação marcada como lida.',
            'notificacao' => [
                'id' => $id,
                'lida' => true
            ]
        ]);
    }

}
