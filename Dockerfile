FROM php:8.2-apache

RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

COPY src/ /var/www/html/

RUN mkdir -p /var/www/data && \
    chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/data

# Initialiser la base de données au démarrage
RUN cd /var/www/html && php -r "require 'index.php';"

EXPOSE 80
