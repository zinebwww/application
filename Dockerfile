FROM php:8.2-apache

# 1. Installation des dépendances SQLite
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# 2. Activation du module rewrite d'Apache (Utile pour les APIs)
RUN a2enmod rewrite

# 3. Copie du code source
COPY src/ /var/www/html/

# 4. Création du dossier pour la base de données
RUN mkdir -p /var/www/data

# 5. Initialisation de la base de données
# On force l'exécution d'index.php une fois pour créer le fichier sqlite
RUN cd /var/www/html && php -r "require 'index.php';" || true

# 6. FIX PERMISSIONS (Très important pour l'erreur "readonly database")
# Apache tourne avec l'utilisateur www-data. Il doit posséder le dossier data.
RUN chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 775 /var/www/data

EXPOSE 80
