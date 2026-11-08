<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ReferenciaController.php';
require_once __DIR__ . '/../middleware/administrador.php';
require_once __DIR__ . '/../controllers/ItemController.php';
require_once __DIR__ . '/../controllers/AdminItemController.php';
require_once __DIR__ . '/../controllers/PhotoController.php';
require_once __DIR__ . '/../controllers/DevolucaoController.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../controllers/PedidoLevantamentoController.php';
require_once __DIR__ . '/../controllers/ReclamacaoController.php';
require_once __DIR__ . '/../controllers/EstadoBdController.php';

// Carrega os grupos de rotas: cada grupo trata uma área, como artigos ou reclamações.
require __DIR__ . '/grupos/saude.php';
require __DIR__ . '/grupos/autenticacao.php';
require __DIR__ . '/grupos/referencias.php';
require __DIR__ . '/grupos/artigos.php';
require __DIR__ . '/grupos/administracao.php';
require __DIR__ . '/grupos/fotografias.php';
require __DIR__ . '/grupos/devolucoes.php';
require __DIR__ . '/grupos/notificacoes.php';
require __DIR__ . '/grupos/levantamentos.php';
require __DIR__ . '/grupos/reclamacoes.php';

// Se o endereço e o método pedidos não existem na API, devolve o erro 404.
responderJson([
    'erro' => 'Rota não encontrada.'
], 404);
