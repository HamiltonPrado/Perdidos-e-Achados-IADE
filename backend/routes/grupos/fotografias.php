<?php

declare(strict_types=1);



// Adiciona uma fotografia a um artigo.
if (
    $metodo === 'POST'
    && preg_match(
        '#\A/api/items/([1-9][0-9]*)/photos\z#',
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

    $controller = new PhotoController(obterLigacao());

    $controller->adicionar($id);
}

// Lista as fotografias de um artigo.
if (
    $metodo === 'GET'
    && preg_match(
        '#\A/api/items/([1-9][0-9]*)/photos\z#',
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

    $controller = new PhotoController(obterLigacao());

    $controller->listar($id);
}

// Devolve uma fotografia como imagem.
if (
    $metodo === 'GET'
    && preg_match(
        '#\A/api/photos/([1-9][0-9]*)\z#',
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
            'erro' => 'Fotografia não encontrada.'
        ], 404);
    }

    $controller = new PhotoController(obterLigacao());

    $controller->mostrar($id);
}

// Remove uma fotografia após verificar as permissões.
if (
    $metodo === 'DELETE'
    && preg_match(
        '#\A/api/photos/([1-9][0-9]*)\z#',
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
            'erro' => 'Fotografia não encontrada.'
        ], 404);
    }

    $controller = new PhotoController(obterLigacao());

    $controller->apagar($id);
}
