# LED Display Server

Serveur central Laravel d'une plateforme domotique pilotable par IA.

```
Agent IA (Claude) → [MCP/HTTP + OAuth Passport] → ce serveur → [MQTT] → Mosquitto → Raspberry Pi (écran LED)
```

Le serveur ne fait **aucun rendu**. Il authentifie, vérifie les permissions, publie des payloads MQTT stricts, et journalise.

## Stack

- Laravel 11 / PHP 8.3
- [`laravel/mcp`](https://laravel.com/docs/mcp) — tools MCP
- [`laravel/passport`](https://laravel.com/docs/passport) — OAuth 2.1 pour les agents
- [`php-mqtt/laravel-client`](https://github.com/php-mqtt/laravel-client) — publication MQTT
- PostgreSQL 16
- Docker Compose (app PHP-FPM + nginx + Postgres + Mosquitto)
- GitHub Actions → build/push `ghcr.io`

## Contrat MQTT (Raspberry Pi)

Topic : `display/led/#` (le `mqtt_topic` du device en base, ex. `display/led`)

Payload JSON **exact** :

```json
{
  "type": "text|image|color|clear",
  "content": "...",
  "duration": 10,
  "priority": "normal|high"
}
```

| type | content |
|------|---------|
| `text` | texte à afficher |
| `image` | URL `http(s)` uniquement (jamais de binaire) |
| `color` | hex `#RRGGBB` / `#RGB` ou `r,g,b` |
| `clear` | ignoré |

`duration` et `priority` sont optionnels (`priority` défaut `normal`).

## Tools MCP

Endpoint : `POST /mcp/led-display` (middleware `auth:api`)

| Tool | Rôle |
|------|------|
| `ListDevices` | devices accessibles à l'utilisateur |
| `DisplaySendText` | publie `type=text` |
| `DisplaySendImage` | publie `type=image` |
| `DisplaySetColor` | publie `type=color` |
| `DisplayClear` | publie `type=clear` |
| `DisplayGetStatus` | dernier `status` / `last_seen_at` connus |

Chaque tool vérifie les permissions (`device_user_permissions.allowed_actions`), récupère `mqtt_topic`, publie, écrit un `display_logs`.

> **Note API laravel/mcp** : structure basée sur la doc officielle (`Server` + `$tools`, `Tool::handle(Request): Response`, `schema(JsonSchema)`, enregistrement dans `routes/ai.php` via `Mcp::web` + `Mcp::oauthRoutes`). Si un attribut / namespace change dans une version future du package, vérifier https://laravel.com/docs/mcp.

## Installation locale (Docker)

Prérequis VPS : réseau Docker partagé avec Caddy :

```bash
docker network create proxy   # une seule fois si absent
```

```bash
cp .env.example .env
# APP_URL=https://led.ton-domaine.fr

# Mot de passe Mosquitto
chmod +x docker/mosquitto/generate-passwd.sh
./docker/mosquitto/generate-passwd.sh ledserver changeme
# ou :
docker run --rm -v "$PWD/docker/mosquitto:/mosquitto/config" eclipse-mosquitto:2 \
  mosquitto_passwd -b -c /mosquitto/config/passwd ledserver changeme

# Aligner MQTT_AUTH_* dans .env
# MQTT_AUTH_USERNAME=ledserver
# MQTT_AUTH_PASSWORD=changeme

docker compose build
docker compose up -d

docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan passport:keys --force
docker compose exec app php artisan db:seed --force
```

### Reverse-proxy Caddy

Le service `web` (`container_name: led-display-web`) n'expose plus de port public : Caddy termine le TLS et reverse-proxifie sur le réseau `proxy`.

1. Ajoute le snippet `docker/caddy/Caddyfile.snippet` à ton `Caddyfile` (adapte le domaine).
2. Vérifie que le compose Caddy est bien sur `networks: proxy: external: true`.
3. Recharge Caddy : `docker exec caddy caddy reload --config /etc/caddy/Caddyfile`

```
Internet → Caddy:443 → led-display-web:80 → app:9000
Pi LED ────────────── MQTT :1883 ─────────→ mqtt
```

MQTT reste exposé sur l'hôte (`MQTT_PUBLIC_PORT`, défaut 1883) : ce n'est **pas** routé via Caddy HTTP.

> **Assets CSS/JS** : nginx sert `public/` via le volume Docker `public_assets` (sync au démarrage de `app`). Sans ça, Filament apparaît sans styles. Vérifie aussi `APP_URL=https://…` dans `.env`.
>
> **Attention VPS / Portainer** : si `compose.yaml` et `docker-compose.yml` coexistent, Compose utilise **`compose.yaml` en priorité**. Garde les deux identiques (le repo versionne les deux) ou n’en garde qu’un.

## Back-office Filament (`/admin`)

Accessible uniquement aux users `is_admin=true` (compte seed : `admin@led-display.local` / `password`).

| Section | Contenu |
|---------|---------|
| Dashboard | Stats devices (online / offline / never seen) + tableau de statut |
| Dispositifs | CRUD + capabilities JSON + commande générique MQTT + permissions |
| Utilisateurs | CRUD + flag admin |
| Tokens MCP | Créer / révoquer des personal access tokens Passport |
| Logs | Historique des commandes |

### Gateway MQTT générique

Chaque device a un catalogue `capabilities.commands` (params + template payload). L'agent MCP :

1. `ListDevices` → `capabilities` / `commands`
2. `InvokeDeviceCommand(device_id, command, params)` → publish MQTT

Types préremplis : `led_display` (text/image/color/clear), `relay` (power/toggle). Les tools `Display*` restent disponibles pour les écrans.

Après déploiement image :

```bash
docker compose exec app php artisan migrate --force
# Se connecter sur https://domaine/admin
```

Si la création de token échoue, créer un client Passport personal access :

```bash
docker compose exec app php artisan passport:client --personal --name="LED Personal" --no-interaction
```

Compte démo (seeder) :

- `admin@led-display.local` / `password` (admin)
- `agent@led-display.local` / `password` (permissions LED complètes)
- Device `#1` — topic `display/led`

### Sans Docker (dev)

```bash
cp .env.example .env
# Adapter DB_* (pgsql ou sqlite)
composer install
php artisan key:generate
php artisan migrate
php artisan passport:install
php artisan db:seed
php artisan serve
```

## Enregistrer un device / permissions

```bash
php artisan device:create "LED Cuisine" display/led/cuisine
php artisan device:grant agent@led-display.local 1 --actions=text,image,color,clear
```

API REST (Bearer Passport) :

- `CRUD /api/devices`
- `CRUD /api/users` (admin)
- `POST /api/devices/{id}/permissions` `{ "user_id": 2, "allowed_actions": ["text","clear"] }`
- `GET /api/display-logs`

## Connecter un agent MCP

1. Créer un client OAuth Passport (ou personal access token pour tests) :

```bash
php artisan passport:client --name="Claude Agent" --no-interaction
# ou token personnel :
php artisan tinker
>>> $u = App\Models\User::where('email','agent@led-display.local')->first();
>>> $u->createToken('mcp')->accessToken;
```

2. Configurer le client MCP (ex. Claude / Cursor) :

- URL : `https://VOTRE_HOST/mcp/led-display`
- Auth : Bearer token Passport (`Authorization: Bearer …`)
- OAuth discovery : routes enregistrées via `Mcp::oauthRoutes()` (voir [doc MCP OAuth](https://laravel.com/docs/mcp#oauth))

3. L'agent appelle `ListDevices` puis `DisplaySendText`.

## MQTTS (port 8883)

Le broker expose **1883** (clair, transition / Docker) et **8883** (TLS).
Laravel (`app`, `mqtt-listener`) reste en **clair interne** : `MQTT_HOST=mqtt` + `MQTT_PORT=1883`.

### 1. Certificats

```bash
chmod +x docker/mosquitto/generate-certs.sh
./docker/mosquitto/generate-certs.sh command.lha.run   # hostname = SAN du cert
```

Production : copier un fullchain/privkey Let’s Encrypt dans `storage/mosquitto/certs/`
(voir `storage/mosquitto/certs/README.md`).

### 2. Démarrer / recreeer Mosquitto

```bash
docker compose up -d --force-recreate mqtt
docker compose logs mqtt --tail=30
# firewall : ouvrir TCP 8883
```

### 3. Clients (Tasmota / Pi)

| Réglage | Valeur |
|---------|--------|
| Host | même CN/SAN que le cert (ex. `command.lha.run`) |
| Port | `8883` |
| TLS / SSL | activé |
| User / pass | `MQTT_AUTH_*` |

Auto-signé : Tasmota peut exiger le fingerprint du cert ou une option « insecure ».
Préférer Let’s Encrypt en prod.

### 4. Fermer 1883 sur Internet (après migration)

Firewall : n’autoriser plus 1883 depuis l’extérieur (garder 8883).
Optionnel : retirer le mapping `"${MQTT_PUBLIC_PORT:-1883}:1883"` du compose.

## Listener de statut (optionnel)

Le Pi peut publier son état sur `display/led/+/status` (JSON libre). Pour mettre à jour `devices.status` / `last_seen_at` :

```bash
docker compose --profile listeners up -d mqtt-listener
# ou
php artisan mqtt:listen-status --topic='display/led/+/status'
```

## Broker MQTT externe

Si Mosquitto tourne ailleurs (pas le service `mqtt` Compose), dans `.env` :

```env
MQTT_HOST=mqtt.example.com
MQTT_PORT=8883
MQTT_TLS_ENABLED=true
MQTT_TLS_CA_FILE=/etc/ssl/certs/ca-certificates.crt
MQTT_AUTH_USERNAME=...
MQTT_AUTH_PASSWORD=...
```

Puis retirez / ne démarrez pas le service `mqtt` du compose.

## Déploiement GitHub Actions → GHCR → VPS

Workflow : `.github/workflows/deploy.yml`

1. **Push `main`** (ou tag `v*`) → build image Docker → push `ghcr.io/fabienhamot/mcp_mqtt_server`
2. Déploiement SSH **optionnel** : définir la variable de dépôt `ENABLE_VPS_DEPLOY=true` et les secrets :
   - `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_PORT`, `VPS_APP_DIR`, `GHCR_TOKEN`

Sur le VPS :

```bash
docker network create proxy 2>/dev/null || true
git clone git@github.com:fabienhamot/mcp_mqtt_server.git ~/mcp_mqtt_server && cd ~/mcp_mqtt_server
cp .env.example .env   # APP_URL=https://led.ton-domaine.fr, DB_*, MQTT_*
# générer passwd mosquitto + ajouter le snippet Caddy (docker/caddy/Caddyfile.snippet)
docker compose pull
docker compose up -d
docker compose exec app php artisan passport:keys --force
docker compose exec app php artisan db:seed --force
```

## Tests

```bash
php artisan test --filter=DisplayPayloadTest
```

## Structure utile

```
app/Mcp/Servers/LedDisplayServer.php
app/Mcp/Tools/*Tool.php
app/Services/{DisplayPayload,MqttPublisher,DisplayCommandService,DevicePermissionService}.php
app/Models/{Device,DeviceUserPermission,DisplayLog,User}.php
routes/ai.php          # MCP + OAuth
routes/api.php         # back-office REST
docker-compose.yml
Dockerfile
.github/workflows/deploy.yml
```

## Points à vérifier contre la doc officielle

1. **`laravel/mcp`** — attributs `#[Name]`, `#[Description]`, annotations tools (`IsReadOnly`, `IsIdempotent`), signature exacte de `Mcp::oauthRoutes()` / middleware Passport : https://laravel.com/docs/mcp
2. **Passport 13** — trait `HasApiTokens` + interface `OAuthenticatable`, guard `api` driver `passport`
3. **TLS Mosquitto 8883** — `./docker/mosquitto/generate-certs.sh` puis clients en MQTTS ; fermer 1883 public après migration
