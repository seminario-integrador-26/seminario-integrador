# Deploy a producción (VPS + Docker)

Producción corre en un VPS con Docker Compose (`docker-compose.prod.yml`):
`app` (PHP-FPM), `web` (nginx interno, solo en `127.0.0.1:8080`), `queue`
(worker de notificaciones), `scheduler` y `db` (PostgreSQL 18). El nginx del
host hace de proxy con TLS hacia `127.0.0.1:8080`.

- Repo en el VPS: `/var/www/seminario`
- URL: https://atalayavm.duckdns.org
- El `.env` real vive **solo** en el VPS (plantilla: `.env.production.example`).

## Checklist antes de cada deploy

1. Tests en verde en local: `php artisan test`.
2. Todo commiteado y **pusheado a `main`**: el VPS hace `git pull` de ahí.
3. Si el cambio agrega variables al `.env`, cargarlas en el VPS **antes** de
   correr `deploy.sh` (comparar con `.env.production.example`).
4. Versiones con parche de seguridad: la imagen exige **PHP ≥ 8.5.12** y
   **Node ≥ 24.22.1** y corta el build si la base no las trae (antes de tocar los
   contenedores). `deploy.sh` construye con `--pull`; si igual corta, esperar a
   que salga la imagen oficial o forzar `docker compose -f docker-compose.prod.yml build --pull --no-cache`.

## Deploy

```bash
ssh <usuario>@<vps>
cd /var/www/seminario
./deploy.sh
```

`deploy.sh` es idempotente: pull, build, `up -d`, **backup de la base** en
`backups/` (quedan los últimos 10), migraciones, seeders de catálogo (no pisan
lo cargado desde la UI), caché de config/rutas/vistas, reinicio de `web` y
`queue`, y al final chequea Ghostscript y que `/login` responda 200.

> **Corte de servicio:** al recrear `app` hay unos segundos sin respuesta. Para
> no cortar una guardia en medio de una carga, deployar en un horario acordado
> con el centro de monitoreo. Las migraciones del proyecto son aditivas (columnas
> y tablas nuevas, vista `eventos_vigentes`), sin bloqueos largos.

## Primer deploy con esta versión (pasos únicos)

Variables nuevas respecto del `.env` que ya estaba en el VPS:

```
APP_TIMEZONE=America/Argentina/Cordoba
NOTIFICADOR_CANAL=telegram
TELEGRAM_BOT_TOKEN=<token del bot de producción>
PDFA_HABILITADO=true
MAIL_SCHEME=            # en lugar de MAIL_ENCRYPTION
```

1. **Zona horaria.** Si la base de producción tiene datos cargados antes del
   2026-10-15 (cuando la app corría en UTC), correr una vez el script de
   `docs/zona-horaria.md` **apenas termina el deploy**, con el backup que deja
   `deploy.sh`. Si la base está vacía, no hace falta.
2. **Administrador de sistema.** `deploy.sh` no crea usuarios. El primero se
   crea por consola; el resto, desde la sección Usuarios:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan user:role \
     admin@municipalidad.gob.ar "Administrador de sistema" \
     --name="Nombre Apellido" --username=admin --password='<clave fuerte>'
   ```
3. **Bot de Telegram de producción.** No reusar el token de desarrollo (quedó
   expuesto): en @BotFather `/revoke` sobre `@atalaya_cmu_bot` (o crear uno
   nuevo con `/newbot`), `/setprivacy` → `Disable`, y pegar el token nuevo en
   el `.env` del VPS.
4. **Grupos destinatarios.** Agregar el bot a cada grupo real de Telegram,
   escribir un mensaje en cada uno y obtener los chat_id:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan telegram:chats
   ```
   Cargarlos en Administración → Notificaciones y asociar los tipos de evento
   de cada grupo (no vienen sembrados).
5. **Logo** (opcional): `public/images/logo-municipalidad.png` en el repo; sin
   él el reporte de turno sale sin logo.

## Verificación después del deploy (smoke test)

- [ ] `deploy.sh` terminó con `Ghostscript ... (PDF/A OK)` y `/login -> HTTP 200`.
- [ ] Login con el Administrador de sistema.
- [ ] Como Supervisor: registrar una Apertura de turno, registrar un evento de un tipo con grupo
      asociado → llega a Telegram y el detalle muestra la notificación enviada.
- [ ] Registrar el Cierre de turno → Reportes → descargar el Libro de guardia en PDF.
- [ ] Eventos → exportar `.xlsx`; Análisis y Mapa cargan.
- [ ] API: `curl -H "Authorization: Bearer <token>" https://atalayavm.duckdns.org/api/v1/eventos`.

Logs: `docker compose -f docker-compose.prod.yml logs --tail=100 app queue web`
y `storage/logs/laravel.log` dentro del contenedor `app`.

## Volver atrás (rollback)

1. Código: `git revert <commit>` en local, push a `main` y `./deploy.sh` en el
   VPS (el script hace `git pull --ff-only`, así que no sirve un checkout a un
   commit viejo).
2. Base, solo si una migración rompió datos — **restaurar pisa todo lo cargado
   después del backup**:
   ```bash
   gunzip -c backups/atalaya_<fecha>.sql.gz | \
     docker compose -f docker-compose.prod.yml exec -T db \
     sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
   ```
   (sobre una base vacía: `dropdb`/`createdb` antes, dentro del contenedor `db`).

## No hacer

- `restart app` solo: nginx (`web`) queda con la IP vieja → 502. Usar `deploy.sh`.
- Correr `EventoDemoSeeder` o `GrupoInteresadoSeeder` en producción (son de
  desarrollo).
- `migrate:fresh` / `db:wipe` en producción.
