#!/bin/bash
#
# SSL Setup Script
# Run after the initial deployment to obtain a Let's Encrypt certificate
# and switch nginx to HTTPS.
#
# Usage: bash deploy/setup-ssl.sh yourdomain.com
#

set -euo pipefail

DOMAIN="${1:-}"
COMPOSE_FILE="docker-compose.prod.yml"

if [ -z "${DOMAIN}" ]; then
    echo "Usage: bash deploy/setup-ssl.sh yourdomain.com"
    exit 1
fi

echo "==> Obtaining SSL certificate for ${DOMAIN}..."
docker compose -f "${COMPOSE_FILE}" run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/lib/letsencrypt \
    -d "${DOMAIN}" \
    --email "admin@${DOMAIN}" \
    --agree-tos \
    --no-eff-email

echo "==> Generating SSL nginx config..."
cat > deploy/nginx/default.prod.conf << NGINXEOF
server {
    listen 80 default_server;
    server_name _;

    # Let's Encrypt challenge
    location /.well-known/acme-challenge/ {
        root /var/lib/letsencrypt;
    }

    # Redirect all HTTP to HTTPS
    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl;
    server_name ${DOMAIN};

    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    add_header Strict-Transport-Security "max-age=63072000" always;

    root /var/www/html/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    fastcgi_buffers 4 8k;
    fastcgi_buffer_size 8k;
    client_max_body_size 24M;
    client_body_buffer_size 128k;
    client_header_buffer_size 5120k;
    large_client_header_buffers 16 5120k;

    gzip on;
    gzip_http_version 1.1;
    gzip_comp_level 5;
    gzip_min_length 256;
    gzip_proxied any;
    gzip_vary on;
    gzip_types
        application/atom+xml
        application/javascript
        application/json
        application/rss+xml
        application/vnd.ms-fontobject
        application/x-font-ttf
        application/x-web-app-manifest+json
        application/xhtml+xml
        application/xml
        font/opentype
        image/svg+xml
        image/x-icon
        text/css
        text/plain
        text/x-component;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 60s;
        include fastcgi_params;
    }

    location ~* \.(jpg|jpeg|png|gif|svg|ico|css|js|eot|ttf|woff|woff2)\$ {
        expires max;
        add_header Cache-Control public;
        add_header Access-Control-Allow-Origin *;
        access_log off;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
NGINXEOF

echo "==> Rebuilding and restarting nginx with SSL..."
docker compose -f "${COMPOSE_FILE}" build nginx
docker compose -f "${COMPOSE_FILE}" up -d nginx

echo "==> Updating APP_URL in .env.production..."
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env.production

echo "==> Recaching config..."
docker exec laravel_rag_app php artisan config:cache

echo ""
echo "============================================"
echo " SSL setup complete!"
echo "============================================"
echo " App is now accessible at: https://${DOMAIN}"
echo ""
echo " Certificate auto-renewal is handled by the certbot container."
echo ""
