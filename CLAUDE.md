# CLAUDE.md — Contexto permanente del proyecto

Referencia viva para Claude Code y para el equipo. Es la **fuente de verdad de
arquitectura**. Si algo cambia por requerimientos, actualizar este archivo.

## Dominio

Plataforma web para la gestión de eventos detectados por el Sistema de
Videovigilancia del Centro de Monitoreo de la Municipalidad de Villa María
(UTN FRVM — Seminario Integrador): registro, clasificación, localización,
notificación y consulta. Reemplaza el triple registro actual (libro de guardia
a mano, sistema de conteo, WhatsApp) por un único registro centralizado.

## Usuarios (solo dos)

- **Operador** — registra eventos (tipo, fecha, horario, ubicación si
  corresponde); se derivan al área; al registrarse, notificación automática por
  WhatsApp al grupo según clasificación.
- **Administrador** — consulta/gestiona registros, estadísticas,
  búsquedas/filtros, exporta a `.xlsx` y PDF, dashboards.

## Reglas de negocio (no negociables)

1. **Eventos inmutables.** No se editan; se corrigen con **fe de errata**
   (registro separado que referencia al original: campo, valor anterior, valor
   nuevo, motivo, autor). El original nunca se sobreescribe.
2. Hay tipos que **no requieren ubicación** (`TipoEvento.requiere_ubicacion`).
3. Hay tipos **no cuantificables** que no entran en estadísticas
   (`TipoEvento.es_cuantificable`). Independiente del punto 2.
4. Estadísticas mensuales y anuales por tipo y por ubicación en un período.
5. **API pública de solo lectura, versionada** (`/api/v1`) para Datos Abiertos,
   ADEMÁS del frontend Inertia. Auth: Sanctum o rate limit — // TODO confirmar.
6. Ubicaciones con **OpenStreetMap vía Leaflet** (no Google Maps).

## Arquitectura

Monolito **Laravel + Inertia.js + React** (frontend interno) + **API REST
pública de solo lectura** (Datos Abiertos) + **Blade puro solo para PDF**.

**No crear:** API para el frontend interno (Inertia ya lo resuelve),
microservicios, ni edición directa de eventos.

```
Request → Route → Controller (fino) → Service (negocio) → Model/Eloquent → MySQL
                                         ↓ Events/Listeners → Jobs (cola)
```

Detalle completo de carpetas y comunicación: **`docs/arquitectura.md`**.

## SOLID (aplicado)

- **SRP** — Controllers finos; un Service por responsabilidad.
- **OCP** — Exportadores y Notificadores como estrategias detrás de interfaz;
  agregar formato/canal = clase nueva, sin tocar lo existente.
- **LSP** — cualquier `NotificadorInterface` reemplaza a otra sin romper.
- **ISP** — interfaces chicas (`notificar()`, `exportar()`).
- **DIP** — Services dependen de interfaces; binding en ServiceProvider/Factory.

## Patrones

- **Strategy** — `ExportadorInterface` (Xlsx/Pdf), `EstadisticaCalculadorInterface`.
- **Observer** — Laravel Events/Listeners: `EventoRegistrado` →
  `NotificarInteresadosListener` (en cola) → notificación.
- **Factory** — `ExportadorFactory` / `NotificadorFactory`.
- **Sin Repository Pattern** sobre Eloquent (sobre-ingeniería), salvo consigna
  académica → consultar al equipo antes.

## Stack

Laravel (estable) · PHP 8.4 · React + Inertia (Breeze) · Sanctum (API pública) ·
Leaflet + OpenStreetMap (react-leaflet) · MySQL · Laravel Queues (driver
database) + Supervisor · WhatsApp Cloud API de Meta (no Twilio) ·
maatwebsite/laravel-excel · barryvdh/laravel-dompdf · Recharts ·
spatie/laravel-permission (roles Operador, Administrador — solo esos).

## Modelo de dominio

Ver `docs/modelo-dominio.md`. Entidades: Evento, Errata, TipoEvento,
PuntoMonitoreo, Turno, GrupoInteresado, Contacto, Usuario.

## Estado actual del repo

Sólo **scaffolding de estructura** (carpetas + stubs con docblocks + docs).
Todavía **no** se instaló Laravel/Breeze ni dependencias. Ver README para el
plan de bootstrap. Ante ambigüedad: no inventar, dejar `// TODO: confirmar con
el equipo`.
