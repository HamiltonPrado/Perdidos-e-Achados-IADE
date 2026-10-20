<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ReferenciaController.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../controllers/ItemController.php';

// Verifica se o PHP está a responder, sem abrir ligação à BD.
if ($metodo === 'GET' && $caminho === '/api/health') {
    responderJson([
        'estado' => 'ok',
        'mensagem' => 'O backend está a funcionar.'
    ]);
}

// Encaminha o login para o controller.
if ($metodo === 'POST' && $caminho === '/api/login') {
    $controller = new AuthController(obterLigacao());

    $controller->login();
}

// Verifica a ligação à base de dados.
if ($metodo === 'GET' && $caminho === '/api/health/database') {
    obterLigacao()->query('SELECT 1')->fetchColumn();

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Ligação à base de dados estabelecida.'
    ]);
}

// Encaminha a consulta da conta autenticada para o controller.
if ($metodo === 'GET' && $caminho === '/api/me') {
    $controller = new AuthController(obterLigacao());

    $controller->me();
}

// Encaminha o encerramento da sessão para o controller.
if ($metodo === 'POST' && $caminho === '/api/logout') {
    $controller = new AuthController(obterLigacao());

    $controller->logout();
}

// Testa as permissões de acesso da equipa.
if ($metodo === 'GET' && $caminho === '/api/admin/teste') {
    $utilizador = exigirAdministrador(obterLigacao());

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Acesso da equipa autorizado.',
        'utilizador' => $utilizador
    ]);
}

// Encaminha a listagem das categorias para o controller.
if ($metodo === 'GET' && $caminho === '/api/categories') {
    $controller = new ReferenciaController(obterLigacao());

    $controller->listarCategorias();
}

// Encaminha a listagem dos pontos de recolha para o controller.
if ($metodo === 'GET' && $caminho === '/api/collection-points') {
    $controller = new ReferenciaController(obterLigacao());

    $controller->listarPontosRecolha();
}

// Regista um objeto perdido associado ao utilizador autenticado.
if ($metodo === 'POST' && $caminho === '/api/items/lost') {
    $controller = new ItemController(obterLigacao());

    $controller->criarPerdido();
}


// Lista os artigos do utilizador autenticado.
if ($metodo === 'GET' && $caminho === '/api/items/mine') {
    $controller = new ItemController(obterLigacao());

    $controller->listarMeus();
}

// Lista todos os artigos, com acesso reservado à equipa administradora.
if ($metodo === 'GET' && $caminho === '/api/admin/items') {
    $controller = new ItemController(obterLigacao());

    $controller->listarTodos();
}

// Regista um objeto achado, com acesso reservado à equipa administradora.
if ($metodo === 'POST' && $caminho === '/api/admin/items/found') {
    $controller = new ItemController(obterLigacao());

    $controller->criarAchado();
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

// Responde quando nenhuma rota corresponde ao pedido.
responderJson([
    'erro' => 'Rota não encontrada.'
], 404);