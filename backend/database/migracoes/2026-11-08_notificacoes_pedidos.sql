-- Notificações das decisões da receção: campo `tipo` (correspondencia, pedido_aprovado, pedido_recusado, pedido_reaberto).
-- `arquivada_em` fecha o ciclo sem apagar; `invalidada_em` é usado pela IA.
-- Aplicar uma vez (MySQL 8.0.16+): mysql -u <utilizador> -p perdidos_achados < backend/database/migracoes/2026-11-08_notificacoes_pedidos.sql

SET NAMES utf8mb4;

ALTER TABLE `notifications`
  ADD COLUMN `tipo` enum('correspondencia','pedido_aprovado','pedido_recusado','pedido_reaberto')
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'correspondencia' AFTER `id`,
  MODIFY `match_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `reclamacao_id` int UNSIGNED DEFAULT NULL AFTER `match_id`,
  MODIFY `item_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `arquivada_em` datetime DEFAULT NULL AFTER `invalidada_em`,
  ADD KEY `fk_notifications_reclamacao` (`reclamacao_id`),
  ADD KEY `idx_notifications_ativas` (`user_id`,`arquivada_em`,`lida`,`criado_em`),
  ADD CONSTRAINT `fk_notifications_reclamacao`
    FOREIGN KEY (`reclamacao_id`) REFERENCES `reclamacoes` (`id`),
  ADD CONSTRAINT `chk_notifications_tipo` CHECK (
    (
      `tipo` = 'correspondencia'
      AND `match_id` IS NOT NULL
      AND `item_id` IS NOT NULL
      AND `reclamacao_id` IS NULL
    )
    OR (
      `tipo` IN ('pedido_aprovado', 'pedido_recusado', 'pedido_reaberto')
      AND `reclamacao_id` IS NOT NULL
      AND `match_id` IS NULL
      AND `item_id` IS NULL
    )
  );

-- Notificações para as decisões tomadas antes desta migração, para não desaparecerem do sino.
-- Uma aprovação ainda por levantar fica por ler; as restantes entram como lidas.
-- Se o objeto já foi entregue, a notificação entra arquivada.
INSERT INTO `notifications`
  (`tipo`, `reclamacao_id`, `user_id`, `ponto_recolha_id`, `lida`, `lida_em`, `arquivada_em`, `criado_em`)
SELECT
  CASE r.`estado`
    WHEN 'aprovada' THEN 'pedido_aprovado'
    WHEN 'recusada' THEN 'pedido_recusado'
    ELSE 'pedido_reaberto'
  END,
  r.`id`,
  r.`user_id`,
  achado.`ponto_recolha_id`,
  IF(r.`estado` = 'aprovada' AND achado.`estado` = 'ativo', 0, 1),
  IF(r.`estado` = 'aprovada' AND achado.`estado` = 'ativo', NULL, CURRENT_TIMESTAMP),
  IF(achado.`estado` = 'devolvido', CURRENT_TIMESTAMP, NULL),
  COALESCE(IF(r.`estado` = 'reaberta', r.`reaberta_em`, r.`decidido_em`), r.`atualizado_em`)
FROM `reclamacoes` AS r
INNER JOIN `items` AS achado ON achado.`id` = r.`item_achado_id`
WHERE r.`estado` IN ('aprovada', 'recusada', 'reaberta')
  AND achado.`ponto_recolha_id` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `notifications` AS n WHERE n.`reclamacao_id` = r.`id`
  );
