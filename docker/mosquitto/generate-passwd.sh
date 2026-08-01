#!/bin/sh
# Génère docker/mosquitto/passwd (utilisateur MQTT_AUTH_USERNAME / MQTT_AUTH_PASSWORD)
set -eu
USER="${1:-ledserver}"
PASS="${2:-changeme}"
OUT="$(dirname "$0")/passwd"

if command -v mosquitto_passwd >/dev/null 2>&1; then
  mosquitto_passwd -b -c "$OUT" "$USER" "$PASS"
  echo "Created $OUT for user $USER"
else
  echo "mosquitto_passwd introuvable. Utilisez Docker :"
  echo "  docker run --rm -v \"\$(pwd)/docker/mosquitto:/mosquitto/config\" eclipse-mosquitto:2 \\"
  echo "    mosquitto_passwd -b -c /mosquitto/config/passwd $USER $PASS"
fi
