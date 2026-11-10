-- Remove as colunas da IA que nunca chegaram a ser usadas.
--
-- A IA (ia/worker.py) só compara texto e calcula os vetores de cada vez que analisa, por isso:
--   items.embedding_texto / items.embedding_modelo          → nunca eram preenchidas
--   item_photos.embedding / item_photos.embedding_modelo    → nunca eram preenchidas
--   matches.score_imagem                                    → ficava sempre NULL
-- Se um dia a IA comparar fotos, voltam a ser criadas com o formato que essa versão precisar.
--
-- O código que as limpava (backend/models/item/ItemGestao.php e ia/persistencia.py) já não as
-- refere: atualizem o código ANTES de aplicar esta migração, senão as edições de artigos falham.
--
-- Aplicar UMA vez em cada base de dados:
--   Docker:  docker compose exec -T galera1 bash -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" perdidos_achados' < backend/database/migracoes/2026-11-10_remover_colunas_ia_nao_usadas.sql
--   MAMP:    phpMyAdmin → base de dados perdidos_achados → separador SQL → colar e executar
-- (O Galera replica a alteração para os outros nós.)

SET NAMES utf8mb4;

ALTER TABLE `items`
  DROP COLUMN `embedding_texto`,
  DROP COLUMN `embedding_modelo`;

ALTER TABLE `item_photos`
  DROP COLUMN `embedding`,
  DROP COLUMN `embedding_modelo`;

ALTER TABLE `matches`
  DROP COLUMN `score_imagem`;
