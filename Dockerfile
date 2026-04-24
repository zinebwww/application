FROM php:8.2-apache

# Installer SQLite
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Copier l'application
COPY src/ /var/www/html/
COPY data/ /var/www/data/

# Permissions
RUN chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 777 /var/www/data

EXPOSE 80
