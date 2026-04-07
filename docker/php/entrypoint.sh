#!/bin/bash
set -e

echo "==> Checking Laravel setup..."

# Fix git dubious ownership issue
git config --global --add safe.directory /var/www

# Fix ownership dan permission untuk volume /var/www
chown -R www-data:www-data /var/www
chmod -R u+rwX,go+rX /var/www

# Install composer dependencies if vendor doesn't exist or composer.lock changed
if [ ! -d "vendor" ] || [ "composer.lock" -nt "vendor" ]; then
    echo "==> Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --fallback
fi

# Generate application key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=" ]; then
    echo "==> Generating Application Key..."
    php artisan key:generate --force
fi

# Clear and cache config
echo "==> Caching configuration..."
php artisan config:cache 2>/dev/null || true

# Run migrations if needed (uncomment if desired)
# echo "==> Running migrations..."
# php artisan migrate --force

echo "==> Laravel setup complete!"

# Execute the main command
exec "$@"
