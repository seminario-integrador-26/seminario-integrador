# Cómo levantar el proyecto (desarrollo local)

Guía paso a paso para dejar el proyecto corriendo en tu máquina desde un clon
limpio. Pensada para cualquier integrante del equipo.

> Para el contexto y las reglas de negocio, ver `CLAUDE.md`. Para la estructura
> de carpetas, ver `docs/arquitectura.md`.

## 1. Requisitos previos

Tené instalado (y disponible en el `PATH`):

- **PHP 8.5.12** o superior (`composer.json` lo exige: las versiones anteriores
  tienen CVE-2026-109618/109619, RCE sin autenticación) con las extensiones que pide Laravel: `pdo_pgsql`,
  `pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`.
  En Windows, apuntar `curl.cainfo` y `openssl.cafile` del `php.ini` a un
  `cacert.pem` actual (≥ `composer/ca-bundle` 1.6.0, por la CA eMudhra comprometida).
- **Composer 2.x** (actualizado: `composer self-update`)
- **Node 24 (≥ 24.22.1) y npm 11**: el `package-lock.json` se genera con npm 11.
  Node 20 no tiene soporte ni parches de seguridad.
- **PostgreSQL 15+** corriendo localmente (o accesible por red)
- **Ghostscript** (`apt install ghostscript`): convierte el reporte de turno a
  PDF/A. Sin él el reporte sale como PDF común y queda un error en el log; para
  no usarlo, `PDFA_HABILITADO=false` en el `.env`.
- **Git**

Verificá versiones:

```bash
php -v
composer -V
node -v
npm -v
psql --version
gs --version
```

## 2. Clonar el repositorio

```bash
git clone https://github.com/seminario-integrador-26/seminario-integrador.git
cd seminario-integrador
```

> Si ya lo tenés clonado, saltá al paso 3.

## 3. Instalar dependencias

```bash
composer install
npm install
```

## 4. Crear la base de datos

En PostgreSQL, creá la base vacía (el nombre por defecto es `seminario_integrador`).
PostgreSQL usa UTF-8 por defecto:

```sql
CREATE DATABASE seminario_integrador ENCODING 'UTF8';
```

Desde la terminal podés hacerlo así (te pedirá la contraseña del usuario `postgres`):

```bash
psql -U postgres -c "CREATE DATABASE seminario_integrador ENCODING 'UTF8';"
```

## 5. Configurar el entorno (`.env`)

Copiá el ejemplo y generá la app key:

```bash
cp .env.example .env
php artisan key:generate
```

Editá `.env` y ajustá las credenciales de tu PostgreSQL local:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=seminario_integrador
DB_USERNAME=postgres
DB_PASSWORD=tu_password_de_postgres
```

> **Si no querés configurar PostgreSQL todavía**, podés arrancar con drivers de
> archivo para no depender de la base en sesión/cache/cola:
> `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.
> Recordá revertir a `database` cuando tengas PostgreSQL listo (es lo que usa prod).

## 6. Migrar la base

```bash
php artisan migrate --seed
```

Esto crea las tablas de base: `users`, `cache`, `jobs`,
`personal_access_tokens` (Sanctum) y las de roles/permisos
(spatie/laravel-permission). El `--seed` además carga los **3 roles**
(Supervisor, Administrativo, Administrador de sistema) y un **usuario de desarrollo por rol**
(`supervisor@example.com`, `administrativo@example.com`, `administradordesistema@example.com`;
password `password`).

> Si querés partir de cero en cualquier momento: `php artisan migrate:fresh --seed`.

### Datos de prueba (opcional)

Para probar estadísticas, análisis, mapa, heatmap, Excel, PDF por turno y API
con datos, sembrá un año de turnos cerrados y eventos de prueba (~3.000, con
algunas fe de errata). No envían notificaciones. Solo corre sobre una base
**sin eventos**:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=EventoDemoSeeder
```

El turno de hoy no se crea: lo abre el Supervisor desde el Panel.

## 7. Compilar / servir el frontend (Vite)

En una terminal aparte, dejá Vite corriendo en modo desarrollo (hot reload):

```bash
npm run dev
```

## 8. Levantar el servidor de Laravel

En otra terminal:

```bash
php artisan serve
```

La app queda en **http://localhost:8000** (el `APP_URL` del `.env`).

## 9. Procesar la cola (notificaciones)

Las notificaciones (ej. WhatsApp al registrar un evento) se procesan por cola.
Con `QUEUE_CONNECTION=database`, corré un worker en otra terminal:

```bash
php artisan queue:work
```

> Si usás `QUEUE_CONNECTION=sync` no hace falta el worker (los jobs corren
> inline), pero no representa el comportamiento real de producción.

## 10. Notificaciones por Telegram (opcional)

Por defecto `NOTIFICADOR_CANAL=log`: las notificaciones se escriben en
`storage/logs/laravel.log` y no se envía nada. Para que lleguen a Telegram:

1. **Crear el bot.** En Telegram, hablale a **@BotFather** → `/newbot` →
   elegí nombre y usuario (tiene que terminar en `bot`). Te da un token del
   estilo `123456789:AAH...`.
2. **Configurar el `.env`:**
   ```
   NOTIFICADOR_CANAL=telegram
   TELEGRAM_BOT_TOKEN=123456789:AAH...
   ```
   y `php artisan config:clear`.
3. **Crear el grupo** de Telegram, agregar el bot como miembro y escribir un
   mensaje en el grupo (p. ej. `/start@NombreDelBot`).
4. **Obtener el chat_id:** `php artisan telegram:chats`. El de un grupo es
   negativo (p. ej. `-1001234567890`). Telegram guarda los mensajes 24 h: si
   no aparece nada, escribí otro mensaje en el grupo y repetí.
5. **Cargarlo en el sistema:** entrá como `administradordesistema` →
   Administración → Notificaciones → nuevo grupo (o editar uno), pegá el
   chat_id en "Chat del grupo de Telegram" y **tildá los tipos de evento** que
   se le notifican. Sin tipos asociados no se notifica nada (no vienen
   sembrados).
6. **Reiniciar la cola** (cortar y volver a correr `composer run dev`, o
   `php artisan queue:restart`): el worker no relee el `.env` solo.
7. **Probar:** como `supervisor`, abrí turno y registrá un evento de uno de
   esos tipos. Llega en segundos; en el detalle del evento se ve el estado de
   la notificación. Si falla, reintenta 3 veces y después aparece el botón
   **Reenviar**.

> Si el bot está en el grupo pero no ve los mensajes, revisá en @BotFather
> `/setprivacy` → `Disable`, o escribí el comando mencionando al bot.

## Atajo: todo junto (recomendado)

El repo define un script de Composer que levanta **servidor + cola + Vite** a la
vez (equivale a `php artisan dev`):

```bash
composer run dev
```

Dejalo corriendo en una sola terminal; con eso no hace falta abrir las
terminales separadas de los pasos 7, 8 y 9. La app queda en
**http://localhost:8000**.

## Verificar que funciona

1. Abrí **http://localhost:8000** → deberías ver la pantalla de bienvenida.
2. Entrá a **/login** e iniciá sesión con un usuario sembrado. El login es por
   **nombre de usuario**, por ejemplo `supervisor` / `password`.
3. Deberías llegar al **/dashboard**.

> No hay auto-registro: los usuarios los da de alta el **Administrador de
> sistema** desde la sección Usuarios (o con `php artisan user:role`).

## Comandos útiles

```bash
php artisan config:clear   # limpiar caché de config si cambiaste el .env
php artisan optimize:clear # limpiar todos los cachés (config, rutas, vistas)
php artisan route:list     # ver rutas registradas
php artisan test           # correr los tests
```

## Problemas comunes

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `SQLSTATE[08006] ... password authentication failed` | credenciales de PostgreSQL mal | revisá `DB_USERNAME` / `DB_PASSWORD` en `.env` |
| `SQLSTATE[08006] ... database "seminario_integrador" does not exist` | no creaste la base | volvé al paso 4 |
| `No application encryption key has been specified` | falta la app key | `php artisan key:generate` |
| Cambios del `.env` que no toman efecto | config cacheada | `php artisan config:clear` |
| El front no actualiza / estilos rotos | Vite no está corriendo | dejá `npm run dev` activo |
| `Vite manifest not found` | no compilaste assets | `npm run dev` (o `npm run build` para prod) |
