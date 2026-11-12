<?php

declare(strict_types=1);

// Copia para credenciais.php e preenche os dados do teu MySQL local.
return [
    'host' => '127.0.0.1',
    'port' => 8889,
    'database' => 'perdidos_achados',
    'username' => 'UTILIZADOR_MYSQL',
    'password' => 'PALAVRA_PASSE_MYSQL',
    // Chave secreta dos códigos de levantamento (pelo menos 16 caracteres).
    'codigo_segredo' => 'MUDA_ESTA_CHAVE_SECRETA',
];
