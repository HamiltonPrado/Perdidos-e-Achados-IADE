-- «Telemóvel» sobrepunha-se a «Eletrónicos»: fica só «Eletrónicos».
-- Os objetos que estivessem em «Telemóvel» passam para «Eletrónicos».
-- Aplicar uma vez: docker compose exec -T galera1 bash -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" perdidos_achados' < backend/database/migracoes/2026-11-12_remover_categoria_telemovel.sql

UPDATE `items`
   SET `category_id` = (SELECT `id` FROM `categories` WHERE `nome` = 'Eletrónicos')
 WHERE `category_id` = (SELECT `id` FROM `categories` WHERE `nome` = 'Telemóvel');

DELETE FROM `categories` WHERE `nome` = 'Telemóvel';
