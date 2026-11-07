<?php

declare(strict_types=1);

// Pede ao controller da equipa a lista completa dos artigos.
if ($metodo === 'GET' && $caminho === '/api/admin/items') {
    $controller = new AdminItemController(obterLigacao());

    $controller->listarTodos();
}

// Pede ao controller para registar um achado recebido diretamente pela equipa.
if ($metodo === 'POST' && $caminho === '/api/admin/items/found') {
    $controller = new AdminItemController(obterLigacao());

    $controller->criarAchado();
}

// Pede ao controller da equipa para arquivar o artigo indicado pelo ID.
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

// Pede ao controller da equipa para guardar as alterações ao artigo indicado.
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

// Permite à equipa consultar o artigo, incluindo os dados internos da receção.
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
