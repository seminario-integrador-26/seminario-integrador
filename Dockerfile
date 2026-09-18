# syntax=docker/dockerfile:1
#
# Build multi-stage para el Seminario Integrador (Laravel 13 + Inertia/React).
# Produce DOS imágenes desde un mismo archivo:
#   - target "app": PHP-FPM 8.4 con el código, vendor y assets ya compilados.
#   - target "web": nginx que sirve public/ y delega el PHP al contenedor app.
# Coexiste con otros proyectos del server: no publica puertos por sí mismo,
# eso lo decide docker-compose.

# ---------------------------------------------------------------------------
# Stage 1 — Compilar assets del front (Vite -> public/build)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
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
# Stage 3 — Imagen de la app (PHP-FPM 8.4)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app
WORKDIR /var/www

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
    pcntl \
    opcache

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
