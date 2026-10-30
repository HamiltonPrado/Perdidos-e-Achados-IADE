-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Tempo de geração: 30-Out-2026 às 01:36
-- Versão do servidor: 8.0.44
-- versão do PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de dados: `perdidos_achados`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `categories`
--

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `categories`
--

INSERT INTO `categories` (`id`, `nome`) VALUES
(3, 'Carteira'),
(1, 'Chaves'),
(7, 'Documentos'),
(6, 'Eletrónicos'),
(8, 'Material escolar'),
(4, 'Mochila'),
(9, 'Outro'),
(5, 'Roupa'),
(2, 'Telemóvel');

-- --------------------------------------------------------

--
-- Estrutura da tabela `devolucoes`
--

CREATE TABLE `devolucoes` (
  `id` int UNSIGNED NOT NULL,
  `match_id` int UNSIGNED DEFAULT NULL,
  `item_perdido_id` int UNSIGNED DEFAULT NULL,
  `item_achado_id` int UNSIGNED NOT NULL,
  `recebido_por` int UNSIGNED NOT NULL,
  `confirmado_por` int UNSIGNED NOT NULL,
  `ponto_recolha_id` int UNSIGNED NOT NULL,
  `devolvido_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `devolucoes`
--

INSERT INTO `devolucoes` (`id`, `match_id`, `item_perdido_id`, `item_achado_id`, `recebido_por`, `confirmado_por`, `ponto_recolha_id`, `devolvido_em`) VALUES
(1, 2, 2, 3, 2, 1, 1, '2026-10-29 17:36:09');

-- --------------------------------------------------------

--
-- Estrutura da tabela `ia_jobs`
--

CREATE TABLE `ia_jobs` (
  `id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `versao_item` int UNSIGNED NOT NULL DEFAULT '1',
  `estado` enum('pendente','em_processamento','concluido','erro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `tentativas` int UNSIGNED NOT NULL DEFAULT '0',
  `disponivel_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reservado_ate` datetime DEFAULT NULL,
  `token_reserva` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `ultimo_erro` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `ia_jobs`
--

INSERT INTO `ia_jobs` (`id`, `item_id`, `versao_item`, `estado`, `tentativas`, `disponivel_em`, `reservado_ate`, `token_reserva`, `ultimo_erro`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 1, 'concluido', 1, '2026-10-27 16:32:50', NULL, NULL, NULL, '2026-10-27 16:32:50', '2026-10-29 16:28:38'),
(2, 2, 1, 'concluido', 1, '2026-10-28 20:52:29', NULL, NULL, NULL, '2026-10-28 20:52:29', '2026-10-29 16:28:38'),
(3, 3, 1, 'concluido', 1, '2026-10-29 16:34:58', NULL, NULL, NULL, '2026-10-29 16:34:58', '2026-10-29 16:35:01'),
(4, 4, 1, 'concluido', 1, '2026-10-30 00:57:07', NULL, NULL, NULL, '2026-10-30 00:57:07', '2026-10-30 00:57:10'),
(5, 5, 2, 'concluido', 1, '2026-10-30 01:20:12', NULL, NULL, NULL, '2026-10-30 01:20:12', '2026-10-30 01:20:14');

-- --------------------------------------------------------

--
-- Estrutura da tabela `items`
--

CREATE TABLE `items` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `tipo` enum('lost','found') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int UNSIGNED NOT NULL,
  `location_id` int UNSIGNED NOT NULL,
  `ponto_recolha_id` int UNSIGNED DEFAULT NULL,
  `data_ocorrencia` date NOT NULL,
  `estado` enum('ativo','resolvido','devolvido','arquivado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `ia_estado` enum('pendente','analisado','erro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `ia_erro` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `embedding_texto` blob,
  `embedding_modelo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `versao` int UNSIGNED NOT NULL DEFAULT '1',
  `chave_pedido` varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `pedido_hash` binary(32) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado_recepcao` enum('por_entregar','na_recepcao') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localizacao_stock` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes_internas` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recebido_por` int UNSIGNED DEFAULT NULL,
  `recebido_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `items`
--

INSERT INTO `items` (`id`, `user_id`, `tipo`, `titulo`, `descricao`, `category_id`, `location_id`, `ponto_recolha_id`, `data_ocorrencia`, `estado`, `ia_estado`, `ia_erro`, `embedding_texto`, `embedding_modelo`, `versao`, `chave_pedido`, `pedido_hash`, `criado_em`, `atualizado_em`, `estado_recepcao`, `localizacao_stock`, `observacoes_internas`, `recebido_por`, `recebido_em`) VALUES
(1, 2, 'lost', 'Carteira preta — teste Postman', 'Perdi uma carteira preta com um pequeno fecho vermelho na biblioteca.', 3, 2, NULL, '2026-10-27', 'ativo', 'analisado', NULL, NULL, NULL, 1, NULL, NULL, '2026-10-27 16:32:50', '2026-10-29 16:28:38', NULL, NULL, NULL, NULL, NULL),
(2, 2, 'lost', 'Carteira preta — teste Postman', 'Perdi uma carteira preta com um pequeno fecho vermelho na biblioteca.', 3, 2, NULL, '2026-10-28', 'devolvido', 'analisado', NULL, NULL, NULL, 1, NULL, NULL, '2026-10-28 20:52:29', '2026-10-29 17:36:09', NULL, NULL, NULL, NULL, NULL),
(3, 1, 'found', 'Carteira preta com fecho vermelho', 'Carteira preta com um pequeno fecho vermelho encontrada na biblioteca.', 3, 2, 1, '2026-10-29', 'devolvido', 'analisado', NULL, NULL, NULL, 1, NULL, NULL, '2026-10-29 16:34:58', '2026-10-29 17:36:09', 'na_recepcao', NULL, NULL, NULL, NULL),
(4, 2, 'lost', 'Carteira preta perdida', 'Perdi uma carteira preta com fecho vermelho na biblioteca.', 3, 2, NULL, '2026-10-30', 'ativo', 'analisado', NULL, NULL, NULL, 1, NULL, NULL, '2026-10-30 00:57:07', '2026-10-30 00:57:10', NULL, NULL, NULL, NULL, NULL),
(5, 2, 'found', 'Carteira encontrada', 'Carteira preta com fecho vermelho encontrada na biblioteca.', 3, 2, 1, '2026-10-30', 'ativo', 'analisado', NULL, NULL, NULL, 2, NULL, NULL, '2026-10-30 00:57:29', '2026-10-30 01:20:14', 'na_recepcao', 'Prateleira A2', 'Carteira recebida e conferida pela equipa.', 1, '2026-10-30 01:20:12');

-- --------------------------------------------------------

--
-- Estrutura da tabela `item_photos`
--

CREATE TABLE `item_photos` (
  `id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `mime` enum('image/jpeg','image/png') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dados` mediumblob NOT NULL,
  `embedding` blob,
  `embedding_modelo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `locations`
--

CREATE TABLE `locations` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `locations`
--

INSERT INTO `locations` (`id`, `nome`) VALUES
(7, 'Auditório'),
(4, 'Bar'),
(2, 'Biblioteca'),
(3, 'Cantina'),
(8, 'Casas de banho'),
(9, 'Exterior / jardim'),
(6, 'Laboratório'),
(10, 'Outro'),
(1, 'Receção'),
(5, 'Sala de aula');

-- --------------------------------------------------------

--
-- Estrutura da tabela `matches`
--

CREATE TABLE `matches` (
  `id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `matched_item_id` int UNSIGNED NOT NULL,
  `score` decimal(5,4) NOT NULL,
  `score_texto` decimal(5,4) DEFAULT NULL,
  `score_imagem` decimal(5,4) DEFAULT NULL,
  `modelo_ia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versao_perdido` int UNSIGNED NOT NULL,
  `versao_achado` int UNSIGNED NOT NULL,
  `estado` enum('sugerido','descartado','concluido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sugerido',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `matches`
--

INSERT INTO `matches` (`id`, `item_id`, `matched_item_id`, `score`, `score_texto`, `score_imagem`, `modelo_ia`, `versao_perdido`, `versao_achado`, `estado`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 3, 0.8229, 0.8229, NULL, 'texto-v1:minilm-multilingual-l12-v2@e8f8c211226b894fcb81acc59f3b34ba3efd5f42', 1, 1, 'sugerido', '2026-10-29 16:35:01', '2026-10-29 16:35:01'),
(2, 2, 3, 0.8229, 0.8229, NULL, 'texto-v1:minilm-multilingual-l12-v2@e8f8c211226b894fcb81acc59f3b34ba3efd5f42', 1, 1, 'concluido', '2026-10-29 16:35:01', '2026-10-29 17:36:09'),
(3, 1, 5, 0.8081, 0.8081, NULL, 'texto-v1:minilm-multilingual-l12-v2@e8f8c211226b894fcb81acc59f3b34ba3efd5f42', 1, 2, 'sugerido', '2026-10-30 01:20:14', '2026-10-30 01:20:14'),
(4, 4, 5, 0.7639, 0.7639, NULL, 'texto-v1:minilm-multilingual-l12-v2@e8f8c211226b894fcb81acc59f3b34ba3efd5f42', 1, 2, 'sugerido', '2026-10-30 01:20:14', '2026-10-30 01:20:14');

-- --------------------------------------------------------

--
-- Estrutura da tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL,
  `match_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `ponto_recolha_id` int UNSIGNED NOT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT '0',
  `lida_em` datetime DEFAULT NULL,
  `invalidada_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `notifications`
--

INSERT INTO `notifications` (`id`, `match_id`, `user_id`, `item_id`, `ponto_recolha_id`, `lida`, `lida_em`, `invalidada_em`, `criado_em`) VALUES
(1, 1, 2, 1, 1, 0, NULL, NULL, '2026-10-29 16:35:01'),
(2, 2, 2, 2, 1, 0, NULL, NULL, '2026-10-29 16:35:01'),
(3, 3, 2, 1, 1, 0, NULL, NULL, '2026-10-30 01:20:14'),
(4, 4, 2, 4, 1, 0, NULL, NULL, '2026-10-30 01:20:14');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pedidos_levantamento`
--

CREATE TABLE `pedidos_levantamento` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `item_achado_id` int UNSIGNED NOT NULL,
  `item_perdido_id` int UNSIGNED DEFAULT NULL,
  `match_id` int UNSIGNED DEFAULT NULL,
  `codigo` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `estado` enum('pendente','utilizado','cancelado','expirado') NOT NULL DEFAULT 'pendente',
  `expira_em` datetime NOT NULL,
  `utilizado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Extraindo dados da tabela `pedidos_levantamento`
--

INSERT INTO `pedidos_levantamento` (`id`, `user_id`, `item_achado_id`, `item_perdido_id`, `match_id`, `codigo`, `estado`, `expira_em`, `utilizado_em`, `criado_em`, `atualizado_em`) VALUES
(1, 2, 3, 2, 2, '3G236BVX', 'utilizado', '2026-10-30 16:48:47', '2026-10-29 17:36:09', '2026-10-29 16:48:47', '2026-10-29 17:36:09');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pontos_recolha`
--

CREATE TABLE `pontos_recolha` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `pontos_recolha`
--

INSERT INTO `pontos_recolha` (`id`, `nome`) VALUES
(2, 'Biblioteca'),
(1, 'Receção');

-- --------------------------------------------------------

--
-- Estrutura da tabela `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `dados` mediumblob NOT NULL,
  `expira_em` datetime NOT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `dados`, `expira_em`, `atualizado_em`) VALUES
('1votfhrha61egcj7lgfub6r877', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2237396264396139396536306131633533306434313466376236633532323961363862303132383762666562346332646261303538666134343865613330313465223b, '2026-10-29 18:29:45', '2026-10-29 17:29:45'),
('2uvammnn4hbm14ug29bus76fck', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2233636132363165343032663433616332636633323336633937303266306437303439353664656236323064373134663836663937386630346330633333373830223b, '2026-10-29 18:30:05', '2026-10-29 17:30:05'),
('3ntr8b87k5nh3hjkt30buqq0dt', 1, 0x757365725f69647c693a313b637372665f746f6b656e7c733a36343a2263633937326136633338666563356334396462306562346162393730316433386137373630623462323465333432336164343138393363383434666664626438223b, '2026-10-29 18:41:49', '2026-10-29 17:41:49'),
('4mba4c4v5hip6huvbso24r40o7', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2238653632663330313238356165326565343532636531626661326538633234613762373565316662653536346364326538636135363730646462623239376438223b, '2026-10-27 23:51:10', '2026-10-27 22:51:10'),
('aki7np1c940fljh69clhqej8e5', 3, 0x757365725f69647c693a333b637372665f746f6b656e7c733a36343a2237313762646266666437626164316566313137313839323064353430373531343866316362326336316261613831336437313461323432616132316134386463223b, '2026-10-28 22:21:37', '2026-10-28 21:21:37'),
('ce44oqceht12f61beno68np9uh', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2264636161653130333934356261333530386135323839346538366562353039323464326537383134323034666232626138633339323435633130643066633930223b, '2026-10-27 22:58:41', '2026-10-27 21:58:41'),
('cofk7pdh6bb3la2dojr3m7dj5r', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2265643630316534633566396466646162336334393761346235326638613333363862396266306563666135666462653561623234316566643739636361396533223b, '2026-10-27 22:58:46', '2026-10-27 21:58:46'),
('gu2iduk3tltc4oictnvrrn2t2e', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2239366264323534636139666433646261663533643031303965313835373734626638376231633130396636623434653930383235313062653466343635633135223b, '2026-10-27 22:45:31', '2026-10-27 21:45:31'),
('i6t2jh8if8c206qe9up1lf6pcf', 2, 0x757365725f69647c693a323b637372665f746f6b656e7c733a36343a2239343465646636303132323061383664343438653364613233643337346266333634393066653933373939663664633163363763666663643332363233663039223b, '2026-10-29 17:48:47', '2026-10-29 16:48:47');

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `nome`, `email`, `password_hash`, `role`, `ativo`, `criado_em`) VALUES
(1, 'Administradora', 'admin@iade.pt', '$2y$10$4T9zhSz/fPY0sKqXoXIZZOll4fNsOIeu8KYCk3VpjeENH7HjGvtFm', 'admin', 1, '2026-10-27 10:38:09'),
(2, 'Aluno 1', 'aluno1@iade.pt', '$2y$10$qAVSU7U5oGvlHny15.h6MOVL19FgrrHjv7wcO.Kbbw.3ySm3x6jhK', 'user', 1, '2026-10-27 10:38:09'),
(3, 'Aluno 2', 'aluno2@iade.pt', '$2y$10$qAVSU7U5oGvlHny15.h6MOVL19FgrrHjv7wcO.Kbbw.3ySm3x6jhK', 'user', 1, '2026-10-27 10:38:09');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_nome` (`nome`);

--
-- Índices para tabela `devolucoes`
--
ALTER TABLE `devolucoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_devolucoes_achado` (`item_achado_id`),
  ADD UNIQUE KEY `uq_devolucoes_match` (`match_id`),
  ADD UNIQUE KEY `uq_devolucoes_perdido` (`item_perdido_id`),
  ADD KEY `fk_devolucoes_match` (`match_id`,`item_perdido_id`,`item_achado_id`),
  ADD KEY `fk_devolucoes_destinatario` (`recebido_por`),
  ADD KEY `fk_devolucoes_admin` (`confirmado_por`),
  ADD KEY `fk_devolucoes_ponto` (`ponto_recolha_id`),
  ADD KEY `idx_devolucoes_data` (`devolvido_em`);

--
-- Índices para tabela `ia_jobs`
--
ALTER TABLE `ia_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ia_jobs_item` (`item_id`),
  ADD KEY `idx_ia_jobs_fila` (`estado`,`disponivel_em`),
  ADD KEY `idx_ia_jobs_reserva` (`estado`,`reservado_ate`);

--
-- Índices para tabela `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_items_id_user` (`id`,`user_id`),
  ADD UNIQUE KEY `uq_items_pedido` (`user_id`,`chave_pedido`),
  ADD KEY `fk_items_category` (`category_id`),
  ADD KEY `fk_items_location` (`location_id`),
  ADD KEY `fk_items_ponto_recolha` (`ponto_recolha_id`),
  ADD KEY `idx_items_user` (`user_id`,`estado`),
  ADD KEY `idx_items_filtros` (`tipo`,`estado`,`category_id`,`location_id`,`data_ocorrencia`),
  ADD KEY `idx_items_ia` (`ia_estado`,`estado`),
  ADD KEY `fk_items_recebido_por` (`recebido_por`),
  ADD KEY `idx_items_recepcao` (`tipo`,`estado`,`estado_recepcao`);

--
-- Índices para tabela `item_photos`
--
ALTER TABLE `item_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_photos_item` (`item_id`);

--
-- Índices para tabela `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_nome` (`nome`);

--
-- Índices para tabela `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_matches_par` (`item_id`,`matched_item_id`),
  ADD UNIQUE KEY `uq_matches_id_perdido` (`id`,`item_id`),
  ADD UNIQUE KEY `uq_matches_relacao` (`id`,`item_id`,`matched_item_id`),
  ADD KEY `idx_matches_perdido_estado` (`item_id`,`estado`,`score`),
  ADD KEY `idx_matches_achado_estado` (`matched_item_id`,`estado`);

--
-- Índices para tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notifications_match` (`match_id`),
  ADD KEY `fk_notifications_dono` (`item_id`,`user_id`),
  ADD KEY `fk_notifications_match` (`match_id`,`item_id`),
  ADD KEY `fk_notifications_ponto` (`ponto_recolha_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`invalidada_em`,`lida`,`criado_em`);

--
-- Índices para tabela `pedidos_levantamento`
--
ALTER TABLE `pedidos_levantamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_levantamento_codigo` (`codigo`),
  ADD UNIQUE KEY `uq_levantamento_aluno_achado` (`user_id`,`item_achado_id`),
  ADD KEY `fk_levantamento_achado` (`item_achado_id`),
  ADD KEY `fk_levantamento_perdido` (`item_perdido_id`),
  ADD KEY `fk_levantamento_match` (`match_id`);

--
-- Índices para tabela `pontos_recolha`
--
ALTER TABLE `pontos_recolha`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pontos_recolha_nome` (`nome`);

--
-- Índices para tabela `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_expira` (`expira_em`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `devolucoes`
--
ALTER TABLE `devolucoes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `ia_jobs`
--
ALTER TABLE `ia_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `items`
--
ALTER TABLE `items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `item_photos`
--
ALTER TABLE `item_photos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `pedidos_levantamento`
--
ALTER TABLE `pedidos_levantamento`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `pontos_recolha`
--
ALTER TABLE `pontos_recolha`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `devolucoes`
--
ALTER TABLE `devolucoes`
  ADD CONSTRAINT `fk_devolucoes_achado` FOREIGN KEY (`item_achado_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_devolucoes_admin` FOREIGN KEY (`confirmado_por`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_devolucoes_destinatario` FOREIGN KEY (`recebido_por`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_devolucoes_match` FOREIGN KEY (`match_id`,`item_perdido_id`,`item_achado_id`) REFERENCES `matches` (`id`, `item_id`, `matched_item_id`),
  ADD CONSTRAINT `fk_devolucoes_perdido` FOREIGN KEY (`item_perdido_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_devolucoes_ponto` FOREIGN KEY (`ponto_recolha_id`) REFERENCES `pontos_recolha` (`id`);

--
-- Limitadores para a tabela `ia_jobs`
--
ALTER TABLE `ia_jobs`
  ADD CONSTRAINT `fk_ia_jobs_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_items_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `fk_items_ponto_recolha` FOREIGN KEY (`ponto_recolha_id`) REFERENCES `pontos_recolha` (`id`),
  ADD CONSTRAINT `fk_items_recebido_por` FOREIGN KEY (`recebido_por`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Limitadores para a tabela `item_photos`
--
ALTER TABLE `item_photos`
  ADD CONSTRAINT `fk_photos_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_matches_achado` FOREIGN KEY (`matched_item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_matches_perdido` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Limitadores para a tabela `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_dono` FOREIGN KEY (`item_id`,`user_id`) REFERENCES `items` (`id`, `user_id`),
  ADD CONSTRAINT `fk_notifications_match` FOREIGN KEY (`match_id`,`item_id`) REFERENCES `matches` (`id`, `item_id`),
  ADD CONSTRAINT `fk_notifications_ponto` FOREIGN KEY (`ponto_recolha_id`) REFERENCES `pontos_recolha` (`id`),
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Limitadores para a tabela `pedidos_levantamento`
--
ALTER TABLE `pedidos_levantamento`
  ADD CONSTRAINT `fk_levantamento_achado` FOREIGN KEY (`item_achado_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_levantamento_aluno` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_levantamento_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`),
  ADD CONSTRAINT `fk_levantamento_perdido` FOREIGN KEY (`item_perdido_id`) REFERENCES `items` (`id`);

--
-- Limitadores para a tabela `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
