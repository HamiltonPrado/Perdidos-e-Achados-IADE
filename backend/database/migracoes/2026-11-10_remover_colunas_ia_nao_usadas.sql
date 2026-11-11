-- Remove colunas da IA nunca usadas (embeddings dos artigos e das fotos, score_imagem).
-- Aplicar uma vez: docker compose exec -T galera1 bash -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" perdidos_achados' < backend/database/migracoes/2026-11-10_remover_colunas_ia_nao_usadas.sql
-- (MAMP: colar no separador SQL do phpMyAdmin.)

SET NAMES utf8mb4;

ALTER TABLE `items`
  DROP COLUMN `embedding_texto`,
  DROP COLUMN `embedding_modelo`;

ALTER TABLE `item_photos`
  DROP COLUMN `embedding`,
  DROP COLUMN `embedding_modelo`;

ALTER TABLE `matches`
  DROP COLUMN `score_imagem`;
