<?php

declare(strict_types=1);



// Solicita um código através de uma notificação do utilizador.
if (
    $metodo === 'POST'
    && preg_match(
        '#\A/api/notifications/([1-9][0-9]*)/collection-request\z#',
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

    $controller = new PedidoLevantamentoController(
        obterLigacao()
    );

    $controller->criar($id);
}

// Permite à equipa consultar um código de levantamento.
if (
    $metodo === 'GET'
    && preg_match(
        '#\A/api/admin/collection-requests/([^/]+)\z#',
        $caminho,
        $parametros
    ) === 1
) {
    $controller = new PedidoLevantamentoController(
        obterLigacao()
    );

    $controller->consultar(
        rawurldecode($parametros[1])
    );
}

// Emite o código de levantamento de uma reclamação aprovada.
if (
    $metodo === 'POST'
    && preg_match(
        '#^/api/claims/([1-9][0-9]*)/collection-request$#D',
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
            'erro' => 'Reclamação não encontrada.'
        ], 404);
    }

    $controller = new PedidoLevantamentoController(
        obterLigacao()
    );

    $controller->criarPorReclamacao($id);
}
