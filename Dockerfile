FROM php:8.2-apache

# 1. Installation des dépendances SQLite
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# 2. Copie du code source
COPY src/ /var/www/html/

# 3. Création et préparation du dossier de données
RUN mkdir -p /var/www/data

# 4. Initialisation de la base de données pendant le build
# On lance le script pour qu'il crée le fichier .db
RUN cd /var/www/html && php -r "require 'index.php';" || true

# 5. FIX COMPLET DES PERMISSIONS
# On donne les droits à www-data sur TOUT le dossier web et le dossier data
# On met 777 sur le dossier data pour que SQLite puisse créer ses fichiers temporaires
RUN chown -R www-data:www-data /var/www/html /var/www/data && \
    chmod -R 775 /var/www/html && \
    chmod -R 777 /var/www/data

# 6. (Optionnel mais recommandé) Si votre base de données se crée dans /var/www/html
# on s'assure qu'elle appartient aussi à www-data
RUN find /var/www/html -name "*.db" -exec chown www-data:www-data {} + || true
RUN find /var/www/html -name "*.sqlite" -exec chown www-data:www-data {} + || true

EXPOSE 80
