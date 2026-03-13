#!/bin/bash
#
# Deploy/Redeploy the Laravel RAG App
# Run from the project root as the deploy user.
#
# Usage: bash deploy/deploy.sh [--fresh]
#   --fresh: Run fresh migrations (drops all tables)
#

set -euo pipefail

DOMAIN="${DOMAIN:-}"
FRESH_MIGRATE=false

if [ "${1:-}" = "--fresh" ]; then
    FRESH_MIGRATE=true
fi

APP_CONTAINER="laravel_rag_app"
COMPOSE_FILE="docker-compose.prod.yml"

# Check .env.production exists
if [ ! -f .env.production ]; then
    echo "ERROR: .env.production not found. Copy from .env.production.example and configure it."
    exit 1
fi

# Check APP_KEY is set
if ! grep -q "^APP_KEY=base64:" .env.production; then
    echo "==> Generating APP_KEY..."
    # Generate a key temporarily to inject
    APP_KEY=$(docker run --rm -v "$(pwd)":/app -w /app php:8.5-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env.production
    echo "    APP_KEY set."
fi

echo "==> Pulling latest code..."
git pull origin main 2>/dev/null || echo "    (Skipping git pull — not a git repo or no remote)"

echo "==> Building and starting containers..."
docker compose -f "${COMPOSE_FILE}" build --no-cache
docker compose -f "${COMPOSE_FILE}" up -d

echo "==> Waiting for services to be healthy..."
sleep 10

echo "==> Running migrations..."
if [ "$FRESH_MIGRATE" = true ]; then
    docker exec "${APP_CONTAINER}" php artisan migrate:fresh --force --seed
else
    docker exec "${APP_CONTAINER}" php artisan migrate --force
fi

echo "==> Caching configuration..."
docker exec "${APP_CONTAINER}" php artisan config:cache
docker exec "${APP_CONTAINER}" php artisan route:cache
docker exec "${APP_CONTAINER}" php artisan view:cache

# SSL setup
if [ -n "${DOMAIN}" ]; then
    echo "==> Setting up SSL for ${DOMAIN}..."

    # Check if certificate already exists
    if [ ! -d "/etc/letsencrypt/live/${DOMAIN}" ]; then
        docker compose -f "${COMPOSE_FILE}" run --rm certbot certonly \
            --webroot \
            --webroot-path=/var/lib/letsencrypt \
            -d "${DOMAIN}" \
            --email "admin@${DOMAIN}" \
            --agree-tos \
            --no-eff-email

        echo "    SSL certificate obtained. Restarting nginx..."
        docker compose -f "${COMPOSE_FILE}" restart nginx
    else
        echo "    SSL certificate already exists."
    fi
fi

echo ""
echo "============================================"
echo " Deployment complete!"
echo "============================================"
echo ""

# Show running containers
docker compose -f "${COMPOSE_FILE}" ps

echo ""
if [ -n "${DOMAIN}" ]; then
    echo " App should be accessible at: https://${DOMAIN}"
else
    echo " App is accessible at: http://$(hostname -I 2>/dev/null | awk '{print $1}' || echo 'your-server-ip')"
    echo ""
    echo " To enable SSL, run:"
    echo "   DOMAIN=yourdomain.com bash deploy/deploy.sh"
fi
echo ""
