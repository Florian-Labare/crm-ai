#!/bin/bash
set -e

# Fix permissions for storage and bootstrap/cache
mkdir -p storage/framework/{sessions,views,cache} storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Cache config and routes for production performance
if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Execute the main command
exec "$@"
