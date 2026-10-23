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
#   5. Backup de la base (backups/, se guardan los últimos 10) y migrate --force.
#   6. Seeders idempotentes (roles, permisos, tipos, puntos). NO crea usuarios:
#      el admin de prod se administra a mano (ver docs de deploy).
#   7. Cachea config/rutas/vistas.
#   8. restart web  -> nginx vuelve a resolver la IP del contenedor `app`;
#      restart queue -> el worker relee el .env (p. ej. token de Telegram).
#   9. Chequeos: Ghostscript (PDF/A) y que la app responda.
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

echo "==> [1/9] git pull"
git pull --ff-only

echo "==> [2/9] build de imágenes"
# --pull: las bases usan tag de rama (php:8.5-fpm-alpine, node:24-alpine) y
# así siempre traen el último parche de seguridad. El Dockerfile corta el build
# si la versión de PHP/Node no tiene el fix, antes de tocar los contenedores.
$COMPOSE build --pull

echo "==> [3/9] up -d (recrea lo cambiado y re-linkea la red)"
$COMPOSE up -d

echo "==> [4/9] esperando a que Postgres esté healthy..."
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$DB_CONTAINER" 2>/dev/null)" = "healthy" ]; do
    sleep 2
done
echo "    Postgres OK."

echo "==> [5/9] backup de la base + migraciones"
mkdir -p backups
backup="backups/atalaya_$(date +%Y%m%d_%H%M%S).sql.gz"
$COMPOSE exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' | gzip > "$backup"
echo "    backup: $backup ($(du -h "$backup" | cut -f1))"
ls -1t backups/atalaya_*.sql.gz | tail -n +11 | xargs -r rm --
$COMPOSE exec -T app php artisan migrate --force

echo "==> [6/9] seeders idempotentes"
for seeder in RoleSeeder PermissionSeeder TipoEventoSeeder PuntoMonitoreoSeeder; do
    echo "    - $seeder"
    $COMPOSE exec -T app php artisan db:seed --class="$seeder" --force
done

echo "==> [7/9] cacheando config/rutas/vistas"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> [8/9] reiniciando web (re-resolver IP del app)"
$COMPOSE restart web queue

echo "==> [9/9] chequeos"
if $COMPOSE exec -T app gs --version >/dev/null 2>&1; then
    echo "    Ghostscript $($COMPOSE exec -T app gs --version) (PDF/A OK)"
else
    echo "    AVISO: falta Ghostscript en la imagen: el reporte de turno sale sin PDF/A."
fi
codigo=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/login || true)
echo "    /login -> HTTP $codigo"
[ "$codigo" = "200" ] || echo "    AVISO: la app no respondió 200; revisar: $COMPOSE logs --tail=50 app web"

echo
echo "==> Estado final:"
$COMPOSE ps

echo
echo "==> Deploy OK -> https://atalayavm.duckdns.org"
