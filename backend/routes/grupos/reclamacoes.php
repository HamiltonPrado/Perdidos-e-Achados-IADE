<?php

declare(strict_types=1);



// Envia uma reclamação de propriedade sobre um achado disponível.
if (
    $metodo === 'POST'
    && preg_match(
        '#^/api/items/([1-9][0-9]*)/claims$#D',
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

    $controller = new ReclamacaoController(obterLigacao());

    $controller->criar($id);
}

// Lista as reclamações para análise pela equipa.
if ($metodo === 'GET' && $caminho === '/api/admin/claims') {
    $controller = new ReclamacaoController(obterLigacao());

    $controller->listarTodos();
}

// Permite à equipa aprovar ou recusar uma reclamação.
if (
    $metodo === 'PATCH'
    && preg_match(
        '#^/api/admin/claims/([1-9][0-9]*)$#D',
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

    $controller = new ReclamacaoController(obterLigacao());

    $controller->decidir($id);
}

// Lista as reclamações do utilizador autenticado.
if ($metodo === 'GET' && $caminho === '/api/claims/mine') {
    $controller = new ReclamacaoController(obterLigacao());

    $controller->listarMinhas();
}

// Permite à equipa reabrir uma reclamação recusada.
if (
    $metodo === 'POST'
    && preg_match(
        '#^/api/admin/claims/([1-9][0-9]*)/reopen$#D',
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
            'erro' => 'Reclamação não encontrada.',
        ], 404);
    }

    $controller = new ReclamacaoController(obterLigacao());

    $controller->reabrir($id);
}
