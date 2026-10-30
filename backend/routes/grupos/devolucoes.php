<?php

declare(strict_types=1);



// Regista uma devolução presencial confirmada pela equipa.
if (
    $metodo === 'POST'
    && preg_match(
        '#\A/api/admin/items/([1-9][0-9]*)/return\z#',
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
            'erro' => 'Artigo não encontrado.'
        ], 404);
    }

    $controller = new DevolucaoController(obterLigacao());

    $controller->criar($id);
}
// Devolve o histórico de devoluções à equipa.
if (
    $metodo === 'GET'
    && $caminho === '/api/admin/returns'
) {
    $controller = new DevolucaoController(obterLigacao());

    $controller->listarTodos();
}

// Confirma uma entrega presencial através do código de levantamento.
if (
    $metodo === 'POST'
    && preg_match(
        '#^/api/admin/collection-requests/([^/]+)/confirm$#D',
        $caminho,
        $parametros
    ) === 1
) {
    $controller = new DevolucaoController(obterLigacao());

    $controller->confirmarPorCodigo(
        rawurldecode($parametros[1])
    );
}
