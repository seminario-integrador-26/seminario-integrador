# Requerimientos

> Documento vivo. Completar en equipo. Fuente de verdad de qué debe hacer el
> sistema (el "qué", no el "cómo" — el cómo está en `arquitectura.md`).

## Requerimientos funcionales

- **RF1** — Registro de eventos (Operador): tipo, fecha, horario, ubicación
  cuando corresponda; derivación al área; notificación automática por WhatsApp
  al grupo según clasificación.
- **RF2** — Inmutabilidad + fe de errata: los eventos no se editan; se corrigen
  con un registro de errata separado.
- **RF3** — Consulta y gestión (Administrador): búsquedas/filtros por tipo,
  fecha, horario, ubicación.
- **RF4** — Estadísticas mensuales y anuales por tipo y por ubicación.
- **RF5** — Exportación a `.xlsx` (datos abiertos) y PDF (reporte de turno).
- **RF6** — Dashboards (Recharts).
- **RF7** — API pública de solo lectura, versionada, para Datos Abiertos.
- **RF8** — Visualización de ubicaciones en mapa (Leaflet + OpenStreetMap).

<!-- TODO: numerar y detallar criterios de aceptación de cada RF con el equipo -->

## Requerimientos no funcionales

- **RNF1** — Roles: Operador y Administrador (spatie), sólo esos dos.
- **RNF2** — Notificaciones desacopladas (cola) para no bloquear el registro.
- **RNF3** — API pública actualizada "minuto a minuto".

<!-- TODO: performance, seguridad, disponibilidad, etc. -->
