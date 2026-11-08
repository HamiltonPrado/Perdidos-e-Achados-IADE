<?php

// Router para o servidor embutido do PHP (só para desenvolvimento):
//   php -S localhost:8000 -t frontend backend/router-dev.php
// Pedidos /api/... vão para a API; o resto é servido a partir de frontend/.
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($caminho === '/api' || str_starts_with($caminho, '/api/')) {
    require __DIR__ . '/public/index.php';
    return true;
}

return false;
