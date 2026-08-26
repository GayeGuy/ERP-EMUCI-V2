FROM php:8.2-cli

# Extensions PHP requises (MySQL — variante VPS, cf. DEPLOY-VPS-MYSQL.md)
# Ce Dockerfile n'est pas utilise par le deploiement VPS reel (Apache +
# PHP-FPM installes directement sur la machine, cf. le guide) — il sert
# uniquement a tester localement cette branche contre un vrai MySQL.
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev default-mysql-client \
    unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd zip pdo pdo_mysql mysqli mbstring \
        exif fileinfo opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer les variables d'environnement en PHP
#
# display_errors=Off : l'image de base php:8.2-cli n'embarque aucun
# php.ini (confirmé : "Loaded Configuration File => (none)"), et son
# défaut compilé est display_errors=STDOUT — chaque erreur, avertissement
# ou trace de pile PHP s'affichait donc en clair dans la réponse HTTP,
# avec chemins serveur complets, sur n'importe quelle page. Les erreurs
# restent journalisées (log_errors=On, visibles via `docker logs`/Render
# Logs) — rien n'est perdu pour le débogage, juste plus montré au visiteur.
RUN echo "variables_order = EGPCS" >> /usr/local/etc/php/php.ini \
    && echo "auto_prepend_file =" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "display_startup_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/php.ini

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier le projet
WORKDIR /var/www/html
COPY . .

# Installer les dépendances PHP
RUN composer install --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs

EXPOSE 8080

# Render fournit le port via $PORT (8080 par défaut en local)
CMD ["/bin/sh", "-c", "php -d variables_order=EGPCS -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
