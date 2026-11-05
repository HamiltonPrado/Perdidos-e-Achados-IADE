<?php

declare(strict_types=1);



// Verifica se o PHP está a responder, sem abrir ligação à BD.
if ($metodo === 'GET' && $caminho === '/api/health') {
    responderJson([
        'estado' => 'ok',
        'mensagem' => 'O backend está a funcionar.'
    ]);
}

// Verifica a ligação à base de dados.
if ($metodo === 'GET' && $caminho === '/api/health/database') {
    obterLigacao()->query('SELECT 1')->fetchColumn();

    responderJson([
        'estado' => 'ok',
        'mensagem' => 'Ligação à base de dados estabelecida.'
    ]);
}
