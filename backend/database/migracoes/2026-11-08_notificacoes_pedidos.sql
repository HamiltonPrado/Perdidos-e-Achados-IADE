-- Notificações persistentes para as decisões da receção sobre reclamações.
--
-- Antes: a tabela `notifications` só guardava correspondências da IA
-- («encontrámos um achado parecido com o teu perdido»), com `match_id` obrigatório.
--
-- Depois: cada notificação tem um `tipo`:
--   correspondencia  → como até agora: match_id + item_id (o perdido do aluno) obrigatórios
--   pedido_aprovado  → a receção validou o proprietário
--   pedido_recusado  → a receção recusou
--   pedido_reaberto  → a receção reabriu um pedido recusado; o aluno pode responder outra vez
-- As de pedido ligam à reclamação (`reclamacao_id`) e deixam `item_id` vazio: a chave
-- `fk_notifications_dono` exige que o artigo seja do utilizador, e o achado reclamado é de
-- quem o encontrou. O achado chega-se pela reclamação (`reclamacoes.item_achado_id`).
--
-- `arquivada_em` marca o fim do ciclo sem apagar (fica no histórico):
--   - uma nova decisão sobre a mesma reclamação arquiva a anterior;
--   - o aluno reenviar a resposta arquiva o «pedido reaberto»;
--   - a entrega do objeto (por código ou manual) arquiva as notificações desse achado.
-- É diferente de `invalidada_em`, que a IA usa para esconder correspondências inválidas.
--
-- As inserções já existentes (ia/persistencia.py) continuam a funcionar sem alterações:
-- não indicam o tipo e recebem o valor por defeito `correspondencia`.
--
-- Aplicar UMA vez em cada base de dados (MySQL 8.0.16 ou mais recente, por causa do CHECK):
--   phpMyAdmin → base de dados perdidos_achados → separador SQL → colar e executar
--   ou: mysql -u <utilizador> -p perdidos_achados < backend/database/migracoes/2026-11-08_notificacoes_pedidos.sql

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
