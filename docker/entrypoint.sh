#!/bin/bash
set -e

echo "🔧 Ensuring required Laravel directories exist..."
mkdir -p bootstrap/cache
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs

echo "🔐 Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "🛠 Running migrations..."
php artisan migrate --force || true

echo "🚀 Starting PHP-FPM..."
exec php-fpm