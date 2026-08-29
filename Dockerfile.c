ARG PHP_IMAGE=php:8.4-apache
ARG COMPOSER_IMAGE=composer:2
FROM ${COMPOSER_IMAGE} AS composer-bin
FROM ${PHP_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl git unzip \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        libicu-dev libzip-dev libxml2-dev libonig-dev libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd pdo_mysql mysqli zip xml intl mbstring opcache

RUN a2enmod rewrite headers

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'max_execution_time=240'; \
    echo 'max_input_vars=1500'; \
    echo 'upload_max_filesize=32M'; \
    echo 'post_max_size=32M'; \
    echo 'memory_limit=256M'; \
} > /usr/local/etc/php/conf.d/typo3.ini

COPY typo3.conf /etc/apache2/sites-available/000-default.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

# Composer binary — used by the 'composer' service in docker-compose
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app files into the image (for production builds).
# Run 'docker compose run --rm composer create-project ...' before building for production.
COPY --chown=www-data:www-data . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader
# Create runtime directories excluded from the build context so mounted volumes
# inherit www-data ownership instead of being initialised as root by Docker.
RUN mkdir -p var public/fileadmin public/uploads public/typo3temp \
    && chown -R www-data:www-data var public/fileadmin public/uploads public/typo3temp
