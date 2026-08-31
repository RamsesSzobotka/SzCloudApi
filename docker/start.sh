#!/bin/bash
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Seeding database..."
php artisan db:seed --force

echo "==> Ensuring MinIO bucket exists..."
php artisan storage:ensure-bucket

echo "==> Starting server..."
exec php artisan serve --host=0.0.0.0 --port=8000
