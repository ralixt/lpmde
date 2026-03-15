# Stage 1: deps Composer avec ext-amqp disponible
FROM composer:latest AS composer
WORKDIR /app

# Installer les libs nécessaires à l'extension amqp + l'extension elle-même
RUN apk add --no-cache rabbitmq-c-dev autoconf g++ make linux-headers \
    && pecl install amqp \
    && docker-php-ext-enable amqp

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-scripts --no-progress --no-interaction --optimize-autoloader

# Stage 2: runtime PHP Apache avec ext-amqp
FROM php:8.4-apache
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    librabbitmq-dev \
    git \
    unzip \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    gd \
    opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configuration Apache pour Symfony
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

# Configuration PHP pour la production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.memory_consumption=128" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.max_accelerated_files=10000" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/opcache.ini"

# Copie des fichiers de l'application
WORKDIR /var/www/html
COPY --from=composer /app/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 80
CMD ["apache2-foreground"]
