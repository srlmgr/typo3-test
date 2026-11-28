
ARG PHP_IMAGE=php:8.4.25-apache
ARG COMPOSER_IMAGE=composer:2

FROM ${COMPOSER_IMAGE} AS composer-bin

FROM ${PHP_IMAGE}

# --------------------------------------------------
# System dependencies
# --------------------------------------------------

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl git unzip \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        libicu-dev libzip-dev libxml2-dev libonig-dev \
        locales \
    && sed -i 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
    && locale-gen \
    && rm -rf /var/lib/apt/lists/*

# --------------------------------------------------
# PHP extensions
# --------------------------------------------------

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_mysql \
        mysqli \
        zip \
        xml \
        intl \
        mbstring \
        opcache

# --------------------------------------------------
# Apache
# --------------------------------------------------

RUN a2enmod rewrite headers

COPY typo3.conf /etc/apache2/sites-available/000-default.conf

# --------------------------------------------------
# PHP configuration
# --------------------------------------------------

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

# --------------------------------------------------
# Composer
# --------------------------------------------------

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy Composer definitions and local packages first.
# This allows Docker to cache the dependency installation.
COPY composer.json composer.lock ./
COPY packages/ ./packages/

# Install production dependencies during image build.
RUN COMPOSER_ALLOW_SUPERUSER=1 \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress

# --------------------------------------------------
# TYPO3 application
# --------------------------------------------------

COPY --chown=root:root . .


# --------------------------------------------------
# Runtime directories and permissions
# --------------------------------------------------

RUN mkdir -p \
        var \
        public/fileadmin \
        public/uploads \
        public/typo3temp \
        config/system \
    && chown -R www-data:www-data \
        var \
        public/fileadmin \
        public/uploads \
        public/typo3temp \
        config/system

# --------------------------------------------------
# Entrypoint
# --------------------------------------------------

COPY docker-init-typo3.sh /usr/local/bin/docker-init-typo3.sh

RUN chmod +x /usr/local/bin/docker-init-typo3.sh

RUN cp vendor/typo3/cms-install/Resources/Private/FolderStructureTemplateFiles/root-htaccess public/.htaccess

CMD ["apache2-foreground"]
