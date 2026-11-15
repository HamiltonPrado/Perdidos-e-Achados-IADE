-- Perfil editável: fotografia de perfil guardada na própria conta (como as fotos dos artigos, na BD).
-- foto_atualizada_em serve para o browser saber quando a foto mudou (vai no endereço da foto).
-- Aplicar uma vez: docker compose exec -T galera1 bash -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" perdidos_achados' < backend/database/migracoes/2026-11-14_foto_perfil.sql

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `foto` mediumblob DEFAULT NULL AFTER `ativo`,
  ADD COLUMN IF NOT EXISTS `foto_mime` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `foto`,
  ADD COLUMN IF NOT EXISTS `foto_atualizada_em` datetime DEFAULT NULL AFTER `foto_mime`;
