FROM php:8.2-apache

# Installer SQLite
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Copier l'application
COPY src/ /var/www/html/

# Créer le dossier data et donner les permissions
RUN mkdir -p /var/www/data && \
    chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/data

EXPOSE 80
