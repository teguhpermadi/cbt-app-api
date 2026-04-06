#!/bin/bash

# Domain dan email configuration
DOMAIN="cbtmiarridlo.com"
EMAIL="admin@cbtmiarridlo.com"
STAGING=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Let's Encrypt SSL Setup Script${NC}"
echo -e "${GREEN}========================================${NC}"

# Function untuk check apakah container running
check_container() {
    docker ps --format '{{.Names}}' | grep -q "^nginx-proxy$"
    return $?
}

# Function untuk stop nginx-proxy
stop_nginx() {
    echo -e "${YELLOW}Stopping nginx-proxy...${NC}"
    docker compose -f docker-compose.prod.yml stop nginx-proxy
}

# Function untuk start nginx-proxy
start_nginx() {
    echo -e "${YELLOW}Starting nginx-proxy...${NC}"
    docker compose -f docker-compose.prod.yml up -d nginx-proxy
}

# Function untuk generate SSL
generate_ssl() {
    echo -e "${YELLOW}Generating SSL certificates for $DOMAIN...${NC}"

    # Stop nginx dulu
    stop_nginx

    # Pull certbot image
    echo -e "${YELLOW}Pulling certbot image...${NC}"
    docker pull certbot/certbot:latest

    # Create directories untuk SSL
    mkdir -p docker/ssl/live/$DOMAIN

    # Run certbot untuk generate certificates
    echo -e "${YELLOW}Requesting certificates...${NC}"
    docker run --rm \
        -v "$(pwd)/docker/ssl:/etc/letsencrypt" \
        -v "$(pwd)/docker/ssl-etc:/etc/letsencrypt-live" \
        certbot/certbot:latest \
        certonly \
        --webroot \
        --webroot-path=/usr/share/nginx/html \
        --register-unsafely-without-email \
        --domains "$DOMAIN,app.$DOMAIN,api.$DOMAIN" \
        --rsa-key-size 4096 \
        --agree-tos \
        --force-renewal

    # Jika gagal, coba dengan staging
    if [ $? -ne 0 ]; then
        echo -e "${RED}Failed to generate SSL. Trying with staging...${NC}"
        docker run --rm \
            -v "$(pwd)/docker/ssl:/etc/letsencrypt" \
            certbot/certbot:latest \
            certonly \
            --webroot \
            --webroot-path=/usr/share/nginx/html \
            --server https://acme-staging-v02.api.letsencrypt.org/directory \
            --register-unsafely-without-email \
            --domains "$DOMAIN,app.$DOMAIN,api.$DOMAIN" \
            --rsa-key-size 4096 \
            --agree-tos \
            --force-renewal
    fi

    # Start nginx lagi
    start_nginx

    # Copy certificates jika ada
    if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
        echo -e "${GREEN}SSL certificates generated successfully!${NC}"
    else
        echo -e "${YELLOW}SSL generation requires DNS propagation."
        echo -e "Make sure your domain DNS is pointing to this server.${NC}"
    fi
}

# Alternative: Manual nginx temporary untuk SSL challenge
generate_ssl_manual() {
    echo -e "${YELLOW}Generating SSL with standalone certbot...${NC}"

    # Pull certbot
    docker pull certbot/certbot:latest

    # Create temp nginx untuk challenge
    docker run -d \
        --name temp-certbot-http \
        -p 80:80 \
        -v "$(pwd)/docker/ssl:/etc/letsencrypt" \
        nginx:alpine

    # Request certificates
    docker run --rm \
        --network host \
        -v "$(pwd)/docker/ssl:/etc/letsencrypt" \
        certbot/certbot:latest \
        certonly \
        --standalone \
        --preferred-challenges http \
        --register-unsafely-without-email \
        --domains "$DOMAIN,app.$DOMAIN,api.$DOMAIN" \
        --rsa-key-size 4096 \
        --agree-tos

    # Cleanup
    docker stop temp-certbot-http 2>/dev/null
    docker rm temp-certbot-http 2>/dev/null
}

# Main menu
case "$1" in
    generate)
        generate_ssl_manual
        ;;
    renew)
        echo -e "${YELLOW}Renewing SSL certificates...${NC}"
        docker run --rm \
            -v "$(pwd)/docker/ssl:/etc/letsencrypt" \
            certbot/certbot:latest \
            renew \
            --dry-run
        ;;
    *)
        echo "Usage: $0 {generate|renew}"
        echo ""
        echo "  generate - Generate new SSL certificates"
        echo "  renew    - Renew existing certificates"
        exit 1
        ;;
esac

echo -e "${GREEN}Done!${NC}"