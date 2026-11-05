<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';

class NotificationController
{
    private PDO $pdo;
    private Notification $notificacoes;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->notificacoes = new Notification($pdo);
    }

    // Devolve apenas as notificações do utilizador autenticado.
    public function listar(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        $notificacoes = $this->notificacoes->listarPorUtilizador(
            $utilizador['id']
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