<?php

declare(strict_types=1);

// Edita o nome e a fotografia da conta com sessão iniciada.
if ($metodo === 'PATCH' && $caminho === '/api/me') {
    $controller = new PerfilController(obterLigacao());

    $controller->atualizar();
}

// Devolve a fotografia de perfil da conta com sessão iniciada.
if ($metodo === 'GET' && $caminho === '/api/me/photo') {
    $controller = new PerfilController(obterLigacao());

    $controller->mostrarFoto();
}
