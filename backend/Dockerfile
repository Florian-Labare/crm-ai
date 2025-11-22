FROM php:8.3-apache

# Installer les extensions PHP nécessaires à Laravel + Python pour Whisper
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    python3 \
    python3-pip \
    python3-venv \
    ffmpeg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd mbstring exif pcntl bcmath opcache zip

# Installer l'extension Redis (phpredis)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Installer Swoole pour Laravel Octane
RUN pecl install swoole \
    && docker-php-ext-enable swoole

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copier le code Laravel
COPY . .

# Autoriser Composer à s'exécuter en root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer les dépendances PHP/Laravel sans exécuter les scripts
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Donner les permissions nécessaires
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# --- Installation de faster-whisper pour transcription locale ---
RUN pip3 install --no-cache-dir --break-system-packages -r /var/www/html/scripts/requirements.txt \
    && chmod +x /var/www/html/scripts/whisper_transcribe.py

# --- Configuration Apache personnalisée ---
COPY ./docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# --- Configuration PHP personnalisée pour upload de gros fichiers ---
COPY ./docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Copier et configurer le script d'entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
