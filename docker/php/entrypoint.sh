#!/bin/sh
set -eu

cd /var/www/html

# Exporter public/ vers le volume partagé avec nginx (css/js Filament, etc.)
if [ -d /shared/public ]; then
  echo "Syncing public assets for nginx..."
  rm -rf /shared/public/*
  cp -a /var/www/html/public/. /shared/public/
fi

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

# php-mqtt rejects empty-string TLS paths; strip blanks so config:cache never stores "".
for _mqtt_tls_var in \
  MQTT_TLS_CA_FILE MQTT_TLS_CA_PATH \
  MQTT_TLS_CLIENT_CERT_FILE MQTT_TLS_CLIENT_CERT_KEY_FILE \
  MQTT_TLS_CLIENT_CERT_KEY_PASSPHRASE MQTT_TLS_ALPN
do
  eval "_mqtt_tls_val=\${${_mqtt_tls_var}-}"
  if [ -z "${_mqtt_tls_val}" ]; then
    unset "${_mqtt_tls_var}" 2>/dev/null || true
  fi
done
if [ -f .env ]; then
  sed -i \
    -e '/^MQTT_TLS_CA_FILE=$/d' \
    -e '/^MQTT_TLS_CA_PATH=$/d' \
    -e '/^MQTT_TLS_CLIENT_CERT_FILE=$/d' \
    -e '/^MQTT_TLS_CLIENT_CERT_KEY_FILE=$/d' \
    .env 2>/dev/null || true
fi

if [ "${CACHE_CONFIG:-true}" = "true" ] && [ "${APP_ENV:-production}" = "production" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

exec "$@"
