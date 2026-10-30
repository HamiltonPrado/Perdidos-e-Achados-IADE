<?php

declare(strict_types=1);



// Lista as notificações do utilizador autenticado.
if ($metodo === 'GET' && $caminho === '/api/notifications') {
    $controller = new NotificationController(obterLigacao());

    $controller->listar();
}

// Marca uma notificação do utilizador como lida.
if (
    $metodo === 'PATCH'
    && preg_match(
                '~\A/api/notifications/([1-9][0-9]*)/read\z~',
        $caminho,
        $parametros
    ) === 1
) {
    $id = filter_var(
        $parametros[1],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        responderJson([
            'erro' => 'Notificação não encontrada.'
        ], 404);
    }

    $controller = new NotificationController(obterLigacao());

    $controller->marcarLida($id);
}
