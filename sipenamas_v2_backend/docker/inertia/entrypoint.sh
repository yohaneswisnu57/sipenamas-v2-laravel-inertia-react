#!/bin/sh
set -e

cd /var/www/app

# Volume storage bisa kosong saat pertama dipasang: siapkan folder framework.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage/framework storage/logs bootstrap/cache

# Konfigurasi dibaca dari environment container, jangan pakai cache lama.
php artisan config:clear >/dev/null
php artisan view:cache >/dev/null

exec "$@"
