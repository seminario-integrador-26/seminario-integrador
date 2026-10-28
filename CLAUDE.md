# CLAUDE.md — Contexto permanente del proyecto

Referencia viva para Claude Code y para el equipo. Es la **fuente de verdad de
arquitectura**. Si algo cambia por requerimientos, actualizar este archivo.

## Dominio

Plataforma web para la gestión de eventos detectados por el Sistema de
Videovigilancia del Centro de Monitoreo de la Municipalidad de Villa María
(UTN FRVM — Seminario Integrador): registro, clasificación, localización,
notificación y consulta. Reemplaza el triple registro actual (libro de guardia
a mano, sistema de conteo, WhatsApp) por un único registro centralizado.

**Vocabulario (decisión 2026-10-28).** En todo texto visible (UI, PDF,
exportaciones, notificaciones, mensajes de validación) se dice **Novedad**,
nunca "evento" ni "hecho". En el código, la base y la API pública sigue siendo
`Evento` / `eventos`.

## Usuarios (tres roles)

Roles vía `spatie/laravel-permission` — **solo estos tres**:

- **Supervisor** — registra eventos (tipo, fecha, horario, ubicación si
  corresponde; evento **inmutable**); genera la **fe de errata**; abre y cierra
  el **turno de guardia** registrando los eventos internos Apertura de turno /
  Cierre de turno. Al registrar un evento se dispara la notificación
  automática (Telegram o WhatsApp) según la matriz de Notificaciones.
- **Administrativo** — **solo visualización**: consulta registros,
  búsquedas/filtros, estadísticas y dashboards, y exporta a `.xlsx` y PDF.
  **No** administra usuarios ni tipos de evento.
- **Administrador de sistema** — todo lo del Administrativo **más** la gestión: administra
  usuarios/roles y tipos de evento. Hereda lo que el dominio original llamaba
  "Administrador".

**Autenticación (US-001).** El login es por **nombre de usuario** (`username`) y
contraseña; el email es solo el canal de recuperación. Reset de contraseña:
el Administrador de sistema **blanquea** la clave de los demás roles; **entre
Administradores de sistema no** pueden blanquearse (si la pierden, la recuperan
por email vía el flujo estándar de reseteo).

## Reglas de negocio (no negociables)

1. **Eventos inmutables.** No se editan ni se eliminan; se corrigen con **fe de
   errata**: registro separado que referencia al original y guarda **solo el
   motivo** y el autor; los datos del evento quedan bloqueados (decisión
   2026-10-23). Las erratas anteriores con campo, valor anterior y valor nuevo
   siguen aplicando: para leer estadísticas, mapa, exportaciones y API usar
   `Evento::vigentes()` (vista `eventos_vigentes`), no `Evento::query()`.
2. **Clasificación obligatoria.** Todo evento se clasifica al registrarse por su
   TipoEvento, que pertenece a un **GrupoEvento**: Policía, Guardia Local
   o Internos (catálogo real del "Registro de Intervenciones" más los eventos
   internos del libro de guardia, `TipoEventoSeeder`).
3. **Ubicación condicional.** La ubicación (PuntoMonitoreo) es opcional y
   depende del tipo (`TipoEvento.requiere_ubicacion`). Sin PM se cargan
   **barrio y dirección** (obligatorios si el tipo exige ubicación, opcionales
   si no). Con PM el barrio sale del PM y la dirección es opcional (dónde fue
   exactamente; decisión 2026-10-28). Apertura, Cierre de turno y Nota no llevan
   ubicación (`TipoEvento::SIN_CAMARA`).
4. Hay tipos **no cuantificables** que no entran en estadísticas
   (`TipoEvento.es_cuantificable`). Independiente del punto 3.
   // TODO: confirmar con el equipo cuáles; hoy la planilla real los cuenta a todos.
5. **Libro de guardia sin turnos.** No hay tabla de turnos: el turno se abre y
   se cierra registrando un evento interno (Apertura de turno / Cierre de
   turno, grupo Internos). **No hay eventos sin turno** (decisión 2026-10-26):
   sin una Apertura vigente solo se puede registrar la Apertura (ni eventos
   ni fe de errata), con una abierta no se abre otra, y el hecho no puede ser
   anterior a la Apertura. El turno vigente es la última Apertura sin Cierre
   posterior (`LibroGuardiaService`); `EventoService` lo vuelve a chequear con
   lock para dos Aperturas simultáneas. Apertura, Cierre y Nota se notifican
   (Telegram/WhatsApp) a todos los grupos por defecto (decisión 2026-10-28). Cada fe de errata deja además una constancia
   (evento interno Errata) en el libro. Ver `docs/rediseno-libro-guardia.md`.
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

Laravel (estable) · PHP 8.5 (≥ 8.5.12) · React + Inertia (Breeze; frontend en **TypeScript**
estricto, solo `.ts`/`.tsx`, ver `docs/migracion-typescript.md`) · Sanctum (API pública,
auth por token) · Leaflet + OpenStreetMap (react-leaflet) · **PostgreSQL** ·
Laravel Queues (driver database) + Supervisor · notificaciones por **Telegram Bot
API** (gratuita; canal por defecto en producción) o WhatsApp Cloud API de Meta
(no Twilio), elegible con `NOTIFICADOR_CANAL` · maatwebsite/laravel-excel (export `.xlsx` UTF-8) ·
barryvdh/laravel-dompdf (Libro de guardia) · Recharts ·
spatie/laravel-permission (roles **Supervisor, Administrativo, Administrador de sistema** —
solo esos).

## Requerimientos no funcionales (clave)

- **Alta disponibilidad / Zero Downtime** — el centro de monitoreo no frena.
  Migraciones y mantenimientos sin baja de servicio, o protocolo de contingencia.
- **PDF/A** — el Libro de guardia digital (de una Apertura al Cierre de turno) se exporta bajo el
  estándar PDF/A (ISO de archivado a largo plazo): dompdf genera el PDF y
  **Ghostscript** lo convierte a PDF/A-2b (`ConversorPdfAInterface`,
  `config/reportes.php`). // TODO: validar una muestra real con veraPDF.
- **UTF-8** — los `.xlsx` de Datos Abiertos se generan en UTF-8 (tildes, "ñ").

## Modelo de dominio

Ver `docs/modelo-dominio.md`. Entidades: Evento, Errata, TipoEvento,
PuntoMonitoreo, GrupoEvento, GrupoInteresado, Contacto, Usuario (rol Supervisor,
Administrativo o Administrador de sistema).

## Estado actual del repo

Sobre **PostgreSQL** (ver `docs/levantar-proyecto.md`), con el dominio
implementado (migraciones, modelos, services, tests PHPUnit) y el frontend
en TypeScript. Avance por historia de usuario, decisiones del equipo y
próximos pasos: **`docs/backlog.md`**. Ante ambigüedad: no inventar, dejar
`// TODO: confirmar con el equipo`.
