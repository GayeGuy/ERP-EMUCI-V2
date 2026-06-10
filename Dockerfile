FROM php:8.2-cli

# Extensions PHP requises
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev \
    unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd zip pdo pdo_mysql mysqli mbstring \
        exif fileinfo opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer les variables d'environnement en PHP
RUN echo "variables_order = EGPCS" >> /usr/local/etc/php/php.ini \
    && echo "auto_prepend_file =" >> /usr/local/etc/php/php.ini

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier le projet
WORKDIR /var/www/html
COPY . .

# Installer les dépendances PHP
RUN composer install --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs

EXPOSE 8080

# Utiliser un script de démarrage qui exporte les variables
CMD ["/bin/sh", "-c", "php -d variables_order=EGPCS -S 0.0.0.0:8080 -t /var/www/html"]
