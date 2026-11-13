-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Tempo de geração: 30-Out-2026 às 19:04
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
(5, 'Roupa');

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
  `versao` int UNSIGNED NOT NULL DEFAULT '1',
  `chave_pedido` varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `pedido_hash` binary(32) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado_recepcao` enum('por_entregar','na_recepcao') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `localizacao_stock` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes_internas` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recebido_por` int UNSIGNED DEFAULT NULL,
  `recebido_em` datetime DEFAULT NULL,
  `pergunta_secreta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resposta_esperada` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `item_photos`
--

CREATE TABLE `item_photos` (
  `id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `mime` enum('image/jpeg','image/png') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dados` mediumblob NOT NULL,
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
  `modelo_ia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versao_perdido` int UNSIGNED NOT NULL,
  `versao_achado` int UNSIGNED NOT NULL,
  `estado` enum('sugerido','descartado','concluido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sugerido',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL,
  `tipo` enum('correspondencia','pedido_aprovado','pedido_recusado','pedido_reaberto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'correspondencia',
  `match_id` int UNSIGNED DEFAULT NULL,
  `reclamacao_id` int UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED DEFAULT NULL,
  `ponto_recolha_id` int UNSIGNED NOT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT '0',
  `lida_em` datetime DEFAULT NULL,
  `invalidada_em` datetime DEFAULT NULL,
  `arquivada_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_notifications_tipo` CHECK (((`tipo` = _utf8mb4'correspondencia') and (`match_id` is not null) and (`item_id` is not null) and (`reclamacao_id` is null)) or ((`tipo` in (_utf8mb4'pedido_aprovado',_utf8mb4'pedido_recusado',_utf8mb4'pedido_reaberto')) and (`reclamacao_id` is not null) and (`match_id` is null) and (`item_id` is null)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `codigo_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `estado` enum('pendente','utilizado','cancelado','expirado') NOT NULL DEFAULT 'pendente',
  `expira_em` datetime NOT NULL,
  `utilizado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reclamacao_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Estrutura da tabela `reclamacoes`
--

CREATE TABLE `reclamacoes` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `item_achado_id` int UNSIGNED NOT NULL,
  `item_perdido_id` int UNSIGNED DEFAULT NULL,
  `match_id` int UNSIGNED DEFAULT NULL,
  `versao_item` int UNSIGNED NOT NULL,
  `pergunta_secreta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `informacao_adicional` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('pendente','aprovada','recusada','cancelada','reaberta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `decidido_por` int UNSIGNED DEFAULT NULL,
  `motivo_decisao` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decidido_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reaberta_por` int UNSIGNED DEFAULT NULL,
  `reaberta_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Estrutura da tabela `tentativas_login`
-- Falhas de login recentes de cada email (5 em 15 minutos bloqueiam o email).
--

CREATE TABLE `tentativas_login` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `falhou_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tentativas_email` (`email`, `falhou_em`),
  KEY `idx_tentativas_data` (`falhou_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(2, 'Aluno 1', '20202600@iade.pt', '$2y$10$qAVSU7U5oGvlHny15.h6MOVL19FgrrHjv7wcO.Kbbw.3ySm3x6jhK', 'user', 1, '2026-10-27 10:38:09'),
(3, 'Aluno 2', '2020@iade.pt', '$2y$10$qAVSU7U5oGvlHny15.h6MOVL19FgrrHjv7wcO.Kbbw.3ySm3x6jhK', 'user', 1, '2026-10-27 10:38:09');

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
  ADD KEY `fk_notifications_reclamacao` (`reclamacao_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`invalidada_em`,`lida`,`criado_em`),
  ADD KEY `idx_notifications_ativas` (`user_id`,`arquivada_em`,`lida`,`criado_em`);

--
-- Índices para tabela `pedidos_levantamento`
--
ALTER TABLE `pedidos_levantamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_levantamento_codigo` (`codigo_hash`),
  ADD UNIQUE KEY `uq_levantamento_aluno_achado` (`user_id`,`item_achado_id`),
  ADD UNIQUE KEY `uq_levantamento_reclamacao` (`reclamacao_id`),
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
-- Índices para tabela `reclamacoes`
--
ALTER TABLE `reclamacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reclamacao_aluno_achado` (`user_id`,`item_achado_id`),
  ADD KEY `idx_reclamacoes_estado` (`estado`,`criado_em`),
  ADD KEY `fk_reclamacoes_achado` (`item_achado_id`),
  ADD KEY `fk_reclamacoes_perdido` (`item_perdido_id`),
  ADD KEY `fk_reclamacoes_match` (`match_id`),
  ADD KEY `fk_reclamacoes_decisor` (`decidido_por`),
  ADD KEY `fk_reclamacoes_reaberta_por` (`reaberta_por`);

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ia_jobs`
--
ALTER TABLE `ia_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `items`
--
ALTER TABLE `items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pedidos_levantamento`
--
ALTER TABLE `pedidos_levantamento`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pontos_recolha`
--
ALTER TABLE `pontos_recolha`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `reclamacoes`
--
ALTER TABLE `reclamacoes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `fk_notifications_reclamacao` FOREIGN KEY (`reclamacao_id`) REFERENCES `reclamacoes` (`id`),
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Limitadores para a tabela `pedidos_levantamento`
--
ALTER TABLE `pedidos_levantamento`
  ADD CONSTRAINT `fk_levantamento_achado` FOREIGN KEY (`item_achado_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_levantamento_aluno` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_levantamento_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`),
  ADD CONSTRAINT `fk_levantamento_perdido` FOREIGN KEY (`item_perdido_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_levantamento_reclamacao` FOREIGN KEY (`reclamacao_id`) REFERENCES `reclamacoes` (`id`);

--
-- Limitadores para a tabela `reclamacoes`
--
ALTER TABLE `reclamacoes`
  ADD CONSTRAINT `fk_reclamacoes_achado` FOREIGN KEY (`item_achado_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_reclamacoes_aluno` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reclamacoes_decisor` FOREIGN KEY (`decidido_por`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reclamacoes_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`),
  ADD CONSTRAINT `fk_reclamacoes_perdido` FOREIGN KEY (`item_perdido_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `fk_reclamacoes_reaberta_por` FOREIGN KEY (`reaberta_por`) REFERENCES `users` (`id`);

--
-- Limitadores para a tabela `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
