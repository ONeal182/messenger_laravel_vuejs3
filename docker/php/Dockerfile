FROM php:8.3-fpm

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev

# внутри контейнера
RUN apt-get update \
  && apt-get install -y libjpeg62-turbo-dev libpng-dev libfreetype6-dev libwebp-dev \
  && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
  && docker-php-ext-install gd \
  && docker-php-ext-enable gd

# PHP расширения
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    pcntl

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# ---- Node.js & npm ----
    RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN ln -snf /usr/share/zoneinfo/Europe/Moscow /etc/localtime \
    && echo "Europe/Moscow" > /etc/timezone \
    && echo "date.timezone=Europe/Moscow" > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /var/www
