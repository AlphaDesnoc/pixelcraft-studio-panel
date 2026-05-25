#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for database..."
until php -r "
\$host = getenv('DB_HOST') ?: 'db';
\$port = getenv('DB_PORT') ?: '3306';
\$db = getenv('DB_DATABASE') ?: 'pixelcraft';
\$user = getenv('DB_USERNAME') ?: 'pixelcraft';
\$pass = getenv('DB_PASSWORD') ?: '';
try {
    new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
    exit(0);
} catch (Throwable \$e) {
    exit(1);
}
"; do
    echo "  database not ready, retrying..."
    sleep 2
done

case "$1" in
    php-fpm*)
        echo "Running migrations..."
        php artisan migrate --force

        echo "Linking public storage..."
        php artisan storage:link --force 2>/dev/null || true

        echo "Caching configuration..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache

        chown -R www-data:www-data storage bootstrap/cache
        exec "$@"
        ;;
    *)
        exec gosu www-data "$@"
        ;;
esac
