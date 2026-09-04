#!/bin/sh
set -e

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "$1" = "php-fpm" ]; then
    exec docker-php-entrypoint "$@"
fi

exec gosu www-data docker-php-entrypoint "$@"
