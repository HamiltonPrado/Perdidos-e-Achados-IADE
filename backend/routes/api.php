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

// Encaminha os pedidos por funcionalidade.
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

// Responde quando nenhuma rota corresponde ao pedido.
responderJson([
    'erro' => 'Rota não encontrada.'
], 404);
