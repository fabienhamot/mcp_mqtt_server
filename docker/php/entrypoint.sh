#!/bin/sh
set -eu

cd /var/www/html

if [ -n "${DB_HOST:-}" ]; then
  echo "Waiting for database ${DB_HOST}:${DB_PORT:-5432}..."
  i=0
  until php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Throwable \$e) { fwrite(STDERR, \$e->getMessage().PHP_EOL); exit(1);}"; do
    i=$((i+1))
    if [ "$i" -gt 60 ]; then
      echo "Database unavailable after 60s"
      exit 1
    fi
    sleep 1
  done
fi

if [ ! -f storage/oauth-private.key ] && [ -z "${PASSPORT_PRIVATE_KEY:-}" ]; then
  echo "Generating Passport keys..."
  php artisan passport:keys --no-interaction || true
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force --no-interaction || true
fi

if [ "${CACHE_CONFIG:-true}" = "true" ] && [ "${APP_ENV:-production}" = "production" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

exec "$@"
