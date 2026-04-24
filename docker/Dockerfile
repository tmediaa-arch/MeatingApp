FROM php:8.3-fpm-alpine

# نصب وابستگی‌های سیستمی
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    libxml2-dev \
    autoconf \
    g++ \
    make \
    linux-headers

# نصب PHP extensions
RUN docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        pgsql \
        mbstring \
        zip \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl

# نصب Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# نصب Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# User با UID/GID مطابق با host برای جلوگیری از مشکل permission
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} -S laravel \
    && adduser -u ${UID} -S laravel -G laravel

WORKDIR /var/www/html

# تنظیمات opcache برای production
RUN { \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

USER laravel

CMD ["php-fpm"]

EXPOSE 9000
