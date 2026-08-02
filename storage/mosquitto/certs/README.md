# Certificats Mosquitto MQTTS (ne pas committer les clés)
#
# Générer un auto-signé de test :
#   ./docker/mosquitto/generate-certs.sh command.lha.run
#
# Production (Let's Encrypt), exemple :
#   cp /etc/letsencrypt/live/HOSTNAME/fullchain.pem server.crt
#   cp /etc/letsencrypt/live/HOSTNAME/privkey.pem server.key
#   # ca.crt = racine/intermédiaire (souvent fullchain ou isrgrootx1.pem)
#   cp server.crt ca.crt   # si fullchain suffit pour cafile Mosquitto
#
# Fichiers attendus par mosquitto.conf :
#   ca.crt  server.crt  server.key
