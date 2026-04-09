#!/bin/bash
set -e

echo "==> Checking Laravel setup..."

# Fix git dubious ownership issue
git config --global --add safe.directory /var/www

# Create .env from environment variables if not exists
if [ ! -f "/var/www/.env" ]; then
    echo "==> Creating .env from environment variables..."
    
    cat > /var/www/.env << EOF
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.cbtmiarridlo.com

APP_KEY=${APP_KEY:-base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-mysql}
DB_PORT=3306
DB_DATABASE=${DB_DATABASE:-cbt_app}
DB_USERNAME=${DB_APP_USERNAME:-cbt_user}
DB_PASSWORD=${DB_APP_PASSWORD:-cbt_password}

REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PASSWORD=${REDIS_PASSWORD:-redis_pass_2024}
REDIS_PORT=6379

MONGO_DB_HOST=${DB_MONGO_HOST:-mongodb}
MONGO_DB_DATABASE=${DB_MONGO_DATABASE:-cbt_app}
MONGO_DB_USERNAME=${MONGO_USERNAME:-mongo_user}
MONGO_DB_PASSWORD=${MONGO_PASSWORD:-mongo_pass_2024}
MONGO_DB_PORT=27017

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null

REVERB_HOST=0.0.0.0
REVERB_PORT=6001
REVERB_SCHEME=https
REVERB_APP_KEY=${REVERB_APP_KEY:-}
PUSHER_APP_ID=${PUSHER_APP_ID:-}
PUSHER_APP_KEY=${PUSHER_APP_KEY:-}
PUSHER_APP_SECRET=${PUSHER_APP_SECRET:-}
EOF
    echo "==> .env created successfully!"
fi

# Fix ownership dan permission untuk volume /var/www
chown -R www-data:www-data /var/www
chmod -R u+rwX,go+rX /var/www

# Install composer dependencies if vendor doesn't exist or composer.lock changed
if [ ! -d "vendor" ] || [ "composer.lock" -nt "vendor" ]; then
    echo "==> Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate application key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=" ]; then
    echo "==> Generating Application Key..."
    php artisan key:generate --force
fi

# Clear and cache config
echo "==> Caching configuration..."
php artisan config:cache 2>/dev/null || true

# Run migrations if needed
echo "==> Running migrations..."
php artisan migrate --force --no-interaction

# Create cache table if not exists
echo "==> Creating cache table..."
php artisan cache:table --no-interaction 2>/dev/null || true
php artisan migrate --force --no-interaction

echo "==> Laravel setup complete!"

# Execute the main command
exec "$@"
