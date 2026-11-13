<?php

declare(strict_types=1);

// Encaminha o login para o controller.
if ($metodo === 'POST' && $caminho === '/api/login') {
    $controller = new AutenticacaoController(obterLigacao());

    $controller->entrar();
}

// Pede ao controller os dados da conta que tem sessão iniciada.
if ($metodo === 'GET' && $caminho === '/api/me') {
    $controller = new AutenticacaoController(obterLigacao());

    $controller->utilizadorAtual();
}

// Pede ao controller para terminar a sessão da conta (logout).
if ($metodo === 'POST' && $caminho === '/api/logout') {
    $controller = new AutenticacaoController(obterLigacao());

    $controller->sair();
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
