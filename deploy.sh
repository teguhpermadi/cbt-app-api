#!/bin/bash

set -e

echo "=== CBT App Deployment Script ==="

DOMAIN=$1

if [ -z "$DOMAIN" ]; then
    echo "Usage: ./deploy.sh your-domain.com"
    exit 1
fi

echo "Deploying to domain: $DOMAIN"

# Update nginx config with domain
sed -i "s/your-domain.com/$DOMAIN/g" docker/nginx/ssl.conf
sed -i "s/your-domain.com/$DOMAIN/g" docker/nginx/default.conf
sed -i "s/your-domain.com/$DOMAIN/g" .env.production

echo "Building Docker containers..."
docker compose up -d --build

echo "Waiting for MySQL to be ready..."
sleep 10

echo "Running migrations..."
docker compose exec -T app php artisan migrate --force

echo "Creating storage link..."
docker compose exec -T app php artisan storage:link

echo "Clearing cache..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear

echo ""
echo "=== Deployment Complete ==="
echo "App running at: http://$DOMAIN"
echo ""
echo "Next steps:"
echo "1. Setup SSL with Let's Encrypt:"
echo "   docker compose run --rm nginx certbot certonly --webroot --webroot-path=/var/www/public -d $DOMAIN"
echo ""
echo "2. Or use custom SSL certs in docker/nginx/ssl.conf"