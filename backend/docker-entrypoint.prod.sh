#!/bin/bash
set -e

# Ensure storage directories exist with proper permissions
mkdir -p storage/framework/{sessions,views,cache} storage/logs storage/app/public storage/app/temp storage/app/recordings bootstrap/cache

# Run migrations (--force required in production)
php artisan migrate --force

# Cache configuration, routes, and views for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Execute the main command (Octane start, queue:work, etc.)
exec "$@"
