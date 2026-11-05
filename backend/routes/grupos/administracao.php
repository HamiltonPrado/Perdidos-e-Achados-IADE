<?php

declare(strict_types=1);



// Encaminha a listagem global para o controller da equipa.
if ($metodo === 'GET' && $caminho === '/api/admin/items') {
    $controller = new AdminItemController(obterLigacao());

    $controller->listarTodos();
}

// Encaminha o registo de achados para o controller da equipa.
if ($metodo === 'POST' && $caminho === '/api/admin/items/found') {
    $controller = new AdminItemController(obterLigacao());

    $controller->criarAchado();
}

// Encaminha o arquivo de um artigo para o controller da equipa.
if (
    $metodo === 'PATCH'
    && preg_match(
        '#\A/api/admin/items/([1-9][0-9]*)/archive\z#',
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

    $controller = new AdminItemController(obterLigacao());

    $controller->arquivar($id);
}

// Encaminha a edição de um artigo para o controller da equipa.
if (
    $metodo === 'PUT'
    && preg_match(
        '#\A/api/admin/items/([1-9][0-9]*)\z#',
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

    $controller = new AdminItemController(obterLigacao());

    $controller->atualizar($id);
}

// Confirma a entrada física de um achado na receção.
if (
    $metodo === 'POST'
    && preg_match(
        '#^/api/admin/items/([1-9][0-9]*)/receive$#D',
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

    $controller = new AdminItemController(obterLigacao());

    $controller->confirmarEntrada($id);
}

// Consulta os dados de gestão de um artigo, com acesso da equipa.
if (
    $metodo === 'GET'
    && preg_match(
        '#^/api/admin/items/([1-9][0-9]*)$#D',
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
            'erro' => 'Artigo não encontrado.',
        ], 404);
    }

    $controller = new AdminItemController(obterLigacao());

    $controller->mostrar($id);
}
