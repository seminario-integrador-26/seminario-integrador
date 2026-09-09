# CLAUDE.md — Contexto permanente del proyecto

Referencia viva para Claude Code y para el equipo. Es la **fuente de verdad de
arquitectura**. Si algo cambia por requerimientos, actualizar este archivo.

## Dominio

Plataforma web para la gestión de eventos detectados por el Sistema de
Videovigilancia del Centro de Monitoreo de la Municipalidad de Villa María
(UTN FRVM — Seminario Integrador): registro, clasificación, localización,
notificación y consulta. Reemplaza el triple registro actual (libro de guardia
a mano, sistema de conteo, WhatsApp) por un único registro centralizado.

## Usuarios (tres roles)

Roles vía `spatie/laravel-permission` — **solo estos tres**:

- **Supervisor** — registra eventos (tipo, fecha, horario, ubicación si
  corresponde; evento **inmutable**); genera la **fe de errata**; abre y cierra
  los **turnos de guardia**. Al registrar un evento se dispara la notificación
  automática por WhatsApp al grupo según clasificación.
- **Administrativo** — consulta/gestiona registros, búsquedas/filtros,
  estadísticas, dashboards, exporta a `.xlsx` y PDF, administra usuarios y
  tipos de evento. Hereda lo que el dominio original llamaba "Administrador".
- **Operador** — **solo lectura**: monitorea en vivo y visualiza dashboards;
  **no** carga eventos (detecta y avisa al Supervisor).

## Reglas de negocio (no negociables)

1. **Eventos inmutables.** No se editan ni se eliminan; se corrigen con **fe de
   errata** (registro separado que referencia al original: campo, valor
   anterior, valor nuevo, motivo, autor). El original nunca se sobreescribe.
2. **Clasificación obligatoria.** Todo evento se clasifica al registrarse en al
   menos uno de los ejes: Prevención, Convivencia Urbana o Seguridad Pública
   (los **Informativos** son no cuantificables, ver punto 4).
3. **Ubicación condicional.** La ubicación (PuntoMonitoreo) es opcional y
   depende del tipo (`TipoEvento.requiere_ubicacion`).
4. Hay tipos **no cuantificables** (Informativos) que no entran en estadísticas
   (`TipoEvento.es_cuantificable`). Independiente del punto 3.
5. **Evento ↔ turno activo.** Todo evento queda asociado unívocamente al turno
   de guardia abierto al momento de la carga.
6. **Sin derivación ni seguimiento.** El sistema registra, clasifica, localiza,
   notifica y consulta; **no** deriva al área ni hace seguimiento posterior del
   evento (eso queda fuera de alcance por decisión del dominio final).
7. Estadísticas mensuales y anuales por tipo y por ubicación en un período.
8. **API pública de solo lectura, versionada** (`/api/v1`) para Datos Abiertos,
   ADEMÁS del frontend Inertia. Auth: **token (Sanctum personal access token)**.
9. Ubicaciones con **OpenStreetMap vía Leaflet** (no Google Maps).

## Arquitectura

Monolito **Laravel + Inertia.js + React** (frontend interno) + **API REST
pública de solo lectura** (Datos Abiertos) + **Blade puro solo para PDF**.

**No crear:** API para el frontend interno (Inertia ya lo resuelve),
microservicios, ni edición directa de eventos.

```
Request → Route → Controller (fino) → Service (negocio) → Model/Eloquent → PostgreSQL
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

Laravel (estable) · PHP 8.4 · React + Inertia (Breeze) · Sanctum (API pública,
auth por token) · Leaflet + OpenStreetMap (react-leaflet) · **PostgreSQL** ·
Laravel Queues (driver database) + Supervisor · WhatsApp Cloud API de Meta (no
Twilio) · maatwebsite/laravel-excel (export `.xlsx` UTF-8) ·
barryvdh/laravel-dompdf (reporte de turno) · Recharts ·
spatie/laravel-permission (roles **Supervisor, Administrativo, Operador** —
solo esos).

## Requerimientos no funcionales (clave)

- **Alta disponibilidad / Zero Downtime** — el centro de monitoreo no frena.
  Migraciones y mantenimientos sin baja de servicio, o protocolo de contingencia.
- **PDF/A** — el reporte de turno (Libro de Guardia digital) se exporta bajo el
  estándar PDF/A (ISO de archivado a largo plazo). // TODO: `barryvdh/laravel-dompdf`
  no genera PDF/A nativo → validar (post-proceso Ghostcript/VeraPDF o alternativa).
- **UTF-8** — los `.xlsx` de Datos Abiertos se generan en UTF-8 (tildes, "ñ").

## Modelo de dominio

Ver `docs/modelo-dominio.md`. Entidades: Evento, Errata, TipoEvento,
PuntoMonitoreo, Turno, GrupoInteresado, Contacto, Usuario (rol Supervisor,
Administrativo u Operador).

## Estado actual del repo

**Laravel + Breeze (Inertia/React) y dependencias del proyecto ya instalados**
(ver commit `feat: instalar Laravel + Breeze`). Falta: migrar la conexión a
**PostgreSQL** (el `.env`/docs todavía asumen MySQL), y escribir migraciones,
modelos y lógica de dominio. Ante ambigüedad: no inventar, dejar
`// TODO: confirmar con el equipo`.
