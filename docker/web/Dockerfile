# Contentor «web»: Apache + PHP 8.4 a servir o frontend e a API (/api).
# O código não é copiado para a imagem: o docker-compose.yml monta as pastas frontend/ e
# backend/, por isso as alterações aparecem sem reconstruir a imagem.
FROM php:8.4-apache

# Extensão do MySQL para o PDO; mod_headers para o frontend/.htaccess (Cache-Control);
# mod_remoteip para os logs mostrarem o IP do browser e não o do nginx.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers remoteip

COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY php.ini /usr/local/etc/php/conf.d/perdidos-achados.ini

# Healthcheck: o contentor só está «saudável» se a API conseguir falar com a base de dados.
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://localhost/api/health/database") === false ? 1 : 0);'
