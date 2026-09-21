#!/usr/bin/env bash
#
# Deploy / redeploy de Atalaya en el VPS de producción.
#
# Uso (en el VPS):
#   cd /var/www/seminario
#   ./deploy.sh
#
# Qué hace (idempotente, se puede correr las veces que haga falta):
#   1. git pull (fast-forward).
#   2. Rebuild de las imágenes Docker.
#   3. up -d  -> recrea SOLO lo que cambió y RE-LINKEA la red interna.
#   4. Espera a que Postgres esté healthy.
#   5. migrate --force.
#   6. Seeders idempotentes (roles, permisos, tipos, puntos). NO crea usuarios:
#      el admin de prod se administra a mano (ver docs de deploy).
#   7. Cachea config/rutas/vistas.
#   8. restart web  -> nginx vuelve a resolver la IP del contenedor `app`.
#
# Lecciones que este script evita:
#   - El 500 "relation cache does not exist": pasa cuando la base queda vacía
#     (p. ej. al cambiar el volumen/version de Postgres). El paso 5+6 la repuebla.
#   - El 502 "connect() failed (111)": nginx (web) cachea la IP vieja del `app`
#     tras reiniciarlo. Por eso usamos `up -d` (re-linkea) y al final reiniciamos
#     `web`. NUNCA hacer sólo `restart app` sin tocar `web`.
#
set -euo pipefail

# Ubicarse en el directorio del script (raíz del repo), sin importar desde dónde
# se invoque.
cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.prod.yml"
DB_CONTAINER="seminario-db-1"

echo "==> [1/8] git pull"
git pull --ff-only

echo "==> [2/8] build de imágenes"
$COMPOSE build

echo "==> [3/8] up -d (recrea lo cambiado y re-linkea la red)"
$COMPOSE up -d

echo "==> [4/8] esperando a que Postgres esté healthy..."
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$DB_CONTAINER" 2>/dev/null)" = "healthy" ]; do
    sleep 2
done
echo "    Postgres OK."

echo "==> [5/8] migraciones"
$COMPOSE exec -T app php artisan migrate --force

echo "==> [6/8] seeders idempotentes"
for seeder in RoleSeeder PermissionSeeder TipoEventoSeeder PuntoMonitoreoSeeder; do
    echo "    - $seeder"
    $COMPOSE exec -T app php artisan db:seed --class="$seeder" --force
done

echo "==> [7/8] cacheando config/rutas/vistas"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> [8/8] reiniciando web (re-resolver IP del app)"
$COMPOSE restart web

echo
echo "==> Estado final:"
$COMPOSE ps

echo
echo "==> Deploy OK -> https://atalayavm.duckdns.org"
