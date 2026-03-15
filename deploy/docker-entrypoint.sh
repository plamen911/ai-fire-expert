#!/bin/bash
set -e

# Ensure required directories exist (bind mount may not have them)
mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/bootstrap \
    /var/www/html/storage/app \
    /var/www/html/bootstrap/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Install vendor if missing (first deploy or after composer.json change)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/ not found — running composer install..."
    composer install --no-dev --optimize-autoloader --no-interaction --working-dir=/var/www/html
fi

php artisan optimize:clear

exec supervisord -c /etc/supervisord.conf
