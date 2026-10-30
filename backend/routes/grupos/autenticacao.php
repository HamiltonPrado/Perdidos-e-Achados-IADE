<?php

declare(strict_types=1);



// Encaminha o login para o controller.
if ($metodo === 'POST' && $caminho === '/api/login') {
    $controller = new AuthController(obterLigacao());

    $controller->login();
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
