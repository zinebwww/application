FROM php:8.2-apache

# 1. Installation des dépendances SQLite
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# 2. Copie du code source dans /var/www/html
COPY src/ /var/www/html/

# 3. Préparation des dossiers
# Votre PHP cherche ../data, donc /var/www/data
RUN mkdir -p /var/www/data

# 4. Initialisation de la DB (en ROOT) pour créer le fichier absences.db
RUN php /var/www/html/index.php || true

# 5. FIX FINAL DES DROITS (Obligatoire pour SQLite)
# On donne la propriété à l'utilisateur web (www-data) sur le code ET la database
RUN chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 775 /var/www/html /var/www/data

EXPOSE 80
