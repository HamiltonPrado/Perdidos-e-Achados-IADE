<?php

declare(strict_types=1);



// Regista um objeto perdido associado ao utilizador autenticado.
if ($metodo === 'POST' && $caminho === '/api/items/lost') {
    $controller = new ItemController(obterLigacao());

    $controller->criarPerdido();
}

// Regista um achado do aluno, ainda por entregar na receção.
if ($metodo === 'POST' && $caminho === '/api/items/found') {
    $controller = new ItemController(obterLigacao());

    $controller->criarAchado();
}

// Lista os achados disponíveis para os alunos autenticados.
if ($metodo === 'GET' && $caminho === '/api/items/found') {
    $controller = new ItemController(obterLigacao());

    $controller->listarAchados();
}


// Lista os artigos do utilizador autenticado.
if ($metodo === 'GET' && $caminho === '/api/items/mine') {
    $controller = new ItemController(obterLigacao());

    $controller->listarMeus();
}

// Consulta o detalhe de um artigo através do seu identificador.
if (
    $metodo === 'GET'
    && preg_match(
        '#\A/api/items/([1-9][0-9]*)\z#',
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

    $controller = new ItemController(obterLigacao());

    $controller->mostrar($id);
}

// Permite ao autor marcar o seu perdido como resolvido.
if (
    $metodo === 'PATCH'
    && preg_match(
        '#\A/api/items/([1-9][0-9]*)/resolve\z#',
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

    $controller = new ItemController(obterLigacao());

    $controller->resolver($id);
}

// Lista os perdidos ativos para todos os utilizadores autenticados.
if ($metodo === 'GET' && $caminho === '/api/items/lost') {
    $controller = new ItemController(obterLigacao());

    $controller->listarPerdidos();
}
