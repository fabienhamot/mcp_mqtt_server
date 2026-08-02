#!/usr/bin/env bash
# Génère un certificat TLS (auto-signé) pour Mosquitto MQTTS :8883.
# Usage :
#   ./docker/mosquitto/generate-certs.sh command.lha.run
#   MQTT_TLS_CN=mqtt.example.com ./docker/mosquitto/generate-certs.sh
#
# Pour la production, préférez Let's Encrypt puis copiez :
#   fullchain.pem → server.crt (+ ca.crt = chaîne / ISRG Root X1)
#   privkey.pem   → server.key
set -euo pipefail

CN="${1:-${MQTT_TLS_CN:-command.lha.run}}"
OUT="$(cd "$(dirname "$0")/../.." && pwd)/storage/mosquitto/certs"
DAYS="${MQTT_TLS_DAYS:-825}"

mkdir -p "$OUT"
cd "$OUT"

if [[ -f server.crt && -f server.key && "${MQTT_TLS_FORCE:-}" != "1" ]]; then
  echo "Certs already exist in $OUT (set MQTT_TLS_FORCE=1 to overwrite)"
  ls -la server.crt server.key ca.crt 2>/dev/null || true
  exit 0
fi

echo "Generating self-signed cert for CN/SAN=$CN ($DAYS days) → $OUT"

openssl req -x509 -newkey rsa:2048 -sha256 -days "$DAYS" -nodes \
  -keyout server.key \
  -out server.crt \
  -subj "/CN=${CN}" \
  -addext "subjectAltName=DNS:${CN}"

# Pour un auto-signé, la CA présentée aux clients = le cert serveur.
cp -f server.crt ca.crt

chmod 644 server.crt ca.crt
chmod 600 server.key

echo "OK:"
echo "  $OUT/server.crt"
echo "  $OUT/server.key"
echo "  $OUT/ca.crt"
echo
echo "Next:"
echo "  1. docker compose up -d --force-recreate mqtt"
echo "  2. Open firewall TCP ${MQTT_TLS_PUBLIC_PORT:-8883}"
echo "  3. Tasmota: port 8883, TLS enabled, host=${CN}"
echo "  4. Laravel (Docker interne) can keep MQTT_HOST=mqtt MQTT_PORT=1883 MQTT_TLS_ENABLED=false"
echo
echo "Self-signed: on Tasmota you may need TLS fingerprint or set SetOption132 / disable verify (less secure)."
echo "Prefer Let's Encrypt for production."
