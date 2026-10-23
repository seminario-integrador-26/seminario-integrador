# syntax=docker/dockerfile:1
#
# Build multi-stage para el Seminario Integrador (Laravel 13 + Inertia/React).
# Produce DOS imágenes desde un mismo archivo:
#   - target "app": PHP-FPM 8.5 (>= 8.5.12) con el código, vendor y assets ya compilados.
#   - target "web": nginx que sirve public/ y delega el PHP al contenedor app.
# Coexiste con otros proyectos del server: no publica puertos por sí mismo,
# eso lo decide docker-compose.

# ---------------------------------------------------------------------------
# Stage 1 — Compilar assets del front (Vite -> public/build)
# ---------------------------------------------------------------------------
# Node 24 (npm 11), igual que en desarrollo: el package-lock.json se genera con
# npm 11 y npm 10 lo rechaza en `npm ci` (peer opcional @types/react de
# @laravel/multiplex).
FROM node:24-alpine AS assets
WORKDIR /app
# Node >= 24.22.1 (o 22.23.4 / 26.12.1): fix de CVE-2026-111497 (Brotli) y de
# la CA eMudhra. Tag de rama: construir siempre con --pull para traer el último parche.
RUN node -e "const min={22:[23,4],24:[22,1],26:[12,1]}; const [a,b,c]=process.versions.node.split('.').map(Number); const m=min[a]; if (!m || b<m[0] || (b===m[0] && c<m[1])) { console.error('Node '+process.version+' sin parche de seguridad (>= 24.22.1)'); process.exit(1) }"
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — Instalar dependencias PHP (composer, sin dev)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Sin scripts todavía: artisan aún no tiene el código completo copiado.
# --ignore-platform-reqs: este stage (composer:2) no trae las extensiones PHP;
# las instala el stage "app". Acá solo bajamos dependencias según el lock.
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 3 — Imagen de la app (PHP-FPM 8.5)
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS app
WORKDIR /var/www

# PHP >= 8.5.12: fix de CVE-2026-109618 y CVE-2026-109619 (RCE). La imagen
# oficial compila desde fuente, así que PHP_VERSION es confiable. Si la imagen
# cacheada es vieja el build falla acá: reconstruir con --pull --no-cache.
RUN php -r 'if (version_compare(PHP_VERSION, "8.5.12", "<")) { fwrite(STDERR, "PHP ".PHP_VERSION." sin parche de seguridad (>= 8.5.12)" . PHP_EOL); exit(1); }'

# Trust store del contenedor actualizado (CA eMudhra comprometida): la app
# sale por TLS a Telegram/WhatsApp. Alpine corregido: ca-certificates 20261020-r0.
RUN apk upgrade --no-cache ca-certificates

# Binario de composer (para el dump-autoload optimizado de más abajo).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Extensiones PHP con el helper de mlocati (resuelve deps del sistema solo).
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    bcmath \
    zip \
    gd \
    intl \
    mbstring \
    exif \
    pcntl
# opcache no se instala: desde PHP 8.5 viene compilado siempre en el núcleo.

# RNF PDF/A: Ghostscript convierte el reporte de turno a PDF/A-2b
# (config/reportes.php). Trae el perfil sRGB en /usr/share/ghostscript/*/iccprofiles.
# Sin él, el reporte sale igual pero como PDF común (y queda un error en el log).
RUN apk add --no-cache ghostscript

# Config de PHP para producción (opcache + límites razonables).
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=0"; \
      echo "opcache.memory_consumption=128"; \
      echo "opcache.max_accelerated_files=20000"; \
      echo "opcache.validate_timestamps=0"; \
      echo "opcache.jit=1255"; \
      echo "opcache.jit_buffer_size=64M"; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
 && { \
      echo "memory_limit=256M"; \
      echo "upload_max_filesize=20M"; \
      echo "post_max_size=25M"; \
      echo "expose_php=Off"; \
    } > /usr/local/etc/php/conf.d/app.ini

# Código + vendor + assets compilados.
COPY . /var/www
COPY --from=vendor /app/vendor /var/www/vendor
COPY --from=assets /app/public/build /var/www/public/build

# Ahora sí: descubrir paquetes y optimizar el autoload (ya está todo el código).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && php artisan package:discover --ansi

# Permisos: php-fpm corre como www-data y necesita escribir storage y cache.
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4 — Imagen web (nginx sirve estáticos, delega PHP al target app)
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
# nginx necesita los estáticos (public/) para servirlos sin pasar por PHP.
COPY --from=app /var/www/public /var/www/public
EXPOSE 80
