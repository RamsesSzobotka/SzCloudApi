#!/bin/bash
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Seeding database..."
if php artisan tinker --execute="echo App\\Models\\Expansion::count();" 2>/dev/null | grep -q '^0$'; then
    php artisan db:seed --force
else
    echo "==> Seed data already exists, skipping..."
fi

echo "==> Ensuring MinIO bucket exists..."
php artisan storage:ensure-bucket

echo "==> Starting server..."
exec php artisan serve --host=0.0.0.0 --port=8000
