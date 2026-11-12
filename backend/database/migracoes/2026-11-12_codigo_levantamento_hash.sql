-- O código de levantamento deixa de ser guardado em texto: guarda-se só o HMAC-SHA256 (codigo_hash).
-- Os códigos pendentes deixam de valer; o aluno recebe um novo ao reabrir a página de levantamento.
-- Aplicar uma vez: docker compose exec -T galera1 bash -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" perdidos_achados' < backend/database/migracoes/2026-11-12_codigo_levantamento_hash.sql

ALTER TABLE `pedidos_levantamento`
  ADD COLUMN `codigo_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `codigo`;

UPDATE `pedidos_levantamento`
   SET `codigo_hash` = SHA2(CONCAT(`id`, '-', UUID()), 256),
       `estado` = IF(`estado` = 'pendente', 'expirado', `estado`);

ALTER TABLE `pedidos_levantamento`
  DROP INDEX `uq_levantamento_codigo`,
  DROP COLUMN `codigo`,
  MODIFY `codigo_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ADD UNIQUE KEY `uq_levantamento_codigo` (`codigo_hash`);
