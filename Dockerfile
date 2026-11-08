FROM php:8.2-cli

# Extensions PHP requises (PostgreSQL / Neon)
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev libpq-dev \
    unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd zip pdo pdo_pgsql pgsql mbstring \
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
# Les archives des paquets viennent de api.github.com, qui coupe parfois les
# téléchargements anonymes depuis les IP partagées de Render (curl error 28,
# build en échec). Une seule tentative (Composer réessaie déjà 5 fois par
# fichier), puis clone git (github.com), qui n'a pas la même limite.
ENV COMPOSER_MAX_PARALLEL_HTTP=4
RUN composer install --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs \
    || { echo "composer install : échec des archives, passage par git"; \
         rm -rf vendor; \
         composer install --prefer-source --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs; }

EXPOSE 8080

# Render fournit le port via $PORT (8080 par défaut en local)
CMD ["/bin/sh", "-c", "php -d variables_order=EGPCS -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
