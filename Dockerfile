FROM php:8.3-fpm

ARG user=laravel
ARG uid=1000
ARG REPO_URL=https://github.com/teguhpermadi/cbt-app-api.git
ARG BRANCH=main

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && pecl install mongodb \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable mongodb \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

WORKDIR /var/www

# Clone repository
RUN git clone --depth 1 --branch $BRANCH $REPO_URL /var/www

# Fix git dubious ownership
RUN git config --global --add safe.directory /var/www

# Install Composer dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copy entrypoint script (dari hasil clone)
RUN cp /var/www/docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# USER $user

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
