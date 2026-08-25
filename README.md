# Seminario Integrador — Gestión de Eventos del Centro de Monitoreo

Plataforma web para registrar, clasificar, localizar, notificar y consultar
eventos detectados por el Sistema de Videovigilancia de la Municipalidad de
Villa María (UTN FRVM).

## Stack

- **Backend:** Laravel · PHP 8.4 · MySQL
- **Frontend interno:** React + Inertia.js (Breeze)
- **API pública:** REST solo lectura, versionada (`/api/v1`), Laravel Sanctum
- **Mapas:** Leaflet + OpenStreetMap (react-leaflet)
- **Colas:** Laravel Queues (driver `database`) + Supervisor (prod)
- **Notificaciones:** WhatsApp Cloud API (Meta)
- **Exportación:** maatwebsite/laravel-excel (.xlsx) · barryvdh/laravel-dompdf (PDF)
- **Gráficos:** Recharts
- **Roles:** spatie/laravel-permission (Operador, Administrador)

## Documentación

- `CLAUDE.md` — contexto y reglas de negocio (fuente de verdad).
- `docs/arquitectura.md` — **estructura de carpetas y cómo se comunican** (leer
  antes de escribir código).
- `docs/requerimientos.md`, `docs/relevamiento-dominio.md`,
  `docs/casos-de-uso.md`, `docs/modelo-dominio.md`.

## Estado

Scaffolding de estructura (carpetas + stubs + docs). **Aún no** se instaló
Laravel ni dependencias.

## Bootstrap pendiente (cuando arranquemos la implementación)

> Requiere PHP 8.4, Composer y Node instalados.

```bash
# 1. Laravel + Breeze (Inertia + React)
composer create-project laravel/laravel .
composer require laravel/breeze --dev
php artisan breeze:install react

# 2. Paquetes
composer require spatie/laravel-permission laravel/sanctum \
  maatwebsite/laravel-excel barryvdh/laravel-dompdf
npm install react-leaflet leaflet recharts

# 3. Configurar .env (MySQL, QUEUE_CONNECTION=database) y migrar
php artisan migrate

# 4. Levantar en desarrollo
composer install
npm install
npm run dev            # Vite
php artisan serve
php artisan queue:work # procesar colas (notificaciones)
```

> Nota: la estructura de `app/` de este repo ya sigue la arquitectura del
> proyecto; al integrar Laravel, los stubs existentes conviven con lo que genera
> el framework (revisar colisiones de `AppServiceProvider`, `Controller`, etc.).
