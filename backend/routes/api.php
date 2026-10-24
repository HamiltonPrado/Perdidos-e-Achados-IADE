<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ReferenciaController.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../controllers/ItemController.php';
require_once __DIR__ . '/../controllers/AdminItemController.php';
require_once __DIR__ . '/../controllers/PhotoController.php';
require_once __DIR__ . '/../controllers/DevolucaoController.php';

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

// Responde quando nenhuma rota corresponde ao pedido.
responderJson([
    'erro' => 'Rota não encontrada.'
], 404);