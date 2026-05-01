FROM php:8.2-apache
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite
COPY src/ /var/www/html/
RUN mkdir -p /var/www/data && \
    php /var/www/html/index.php || true && \
    chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 775 /var/www/html /var/www/data
EXPOSE 80
