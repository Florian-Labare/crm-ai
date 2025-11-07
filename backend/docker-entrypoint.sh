#!/bin/bash
set -e

# Fix permissions for storage and bootstrap/cache
mkdir -p storage/framework/{sessions,views,cache} storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Execute the main command
exec "$@"
