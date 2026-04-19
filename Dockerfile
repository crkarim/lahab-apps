FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
      git \
      unzip \
      libpng-dev \
      libjpeg-dev \
      libfreetype6-dev \
      libzip-dev \
      libicu-dev \
      libonig-dev \
      libxml2-dev \
      default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip intl pdo_mysql mysqli bcmath mbstring exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

# Views use asset('public/...') for shared-hosting deploys. Under `php artisan serve`
# the docroot is public/, so create public/public/assets -> ../assets so those URLs resolve.
CMD ["sh", "-c", "mkdir -p public/public && ln -sfn ../assets public/public/assets && ln -sfn ../favicon.ico public/public/favicon.ico && exec php artisan serve --host=0.0.0.0 --port=8000"]
