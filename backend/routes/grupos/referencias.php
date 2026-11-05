<?php

declare(strict_types=1);



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
