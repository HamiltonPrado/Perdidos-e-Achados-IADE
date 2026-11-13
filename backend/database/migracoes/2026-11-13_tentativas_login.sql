-- Limite de tentativas de login: guarda as falhas recentes de cada email.
-- Com 5 falhas em 15 minutos, o login desse email fica bloqueado até a mais antiga sair da janela.
-- Aplicar uma vez: docker compose exec -T galera1 bash -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" perdidos_achados' < backend/database/migracoes/2026-11-13_tentativas_login.sql

CREATE TABLE IF NOT EXISTS `tentativas_login` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `falhou_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tentativas_email` (`email`, `falhou_em`),
  KEY `idx_tentativas_data` (`falhou_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
