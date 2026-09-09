# Requerimientos

> Documento vivo. Completar en equipo. Fuente de verdad de qué debe hacer el
> sistema (el "qué", no el "cómo" — el cómo está en `arquitectura.md`).

## Requerimientos funcionales

- **RF1** — Registro de eventos (Supervisor): tipo, fecha, horario, ubicación
  cuando corresponda; se asocia al turno activo; notificación automática por
  WhatsApp al grupo según clasificación. **Sin derivación/seguimiento.**
- **RF2** — Inmutabilidad + fe de errata: los eventos no se editan ni eliminan;
  se corrigen con un registro de errata separado.
- **RF3** — Clasificación obligatoria en al menos un eje: Prevención,
  Convivencia Urbana o Seguridad Pública (más los Informativos, no cuantificables).
- **RF4** — Gestión de turnos de guardia (Supervisor): abrir y cerrar el turno;
  todo evento queda asociado unívocamente al turno activo.
- **RF5** — Consulta y gestión (Administrativo): búsquedas/filtros por tipo,
  fecha, horario, ubicación; gestión de usuarios y de tipos/categorías de evento.
- **RF6** — Estadísticas mensuales y anuales por tipo y por ubicación.
- **RF7** — Exportación a `.xlsx` (datos abiertos) y PDF (reporte de turno).
- **RF8** — Dashboards (Recharts) — visibles también para el Operador (lectura).
- **RF9** — API pública de solo lectura, versionada, para Datos Abiertos.
- **RF10** — Visualización de ubicaciones en mapa (Leaflet + OpenStreetMap).

<!-- TODO: numerar y detallar criterios de aceptación de cada RF con el equipo -->

## Requerimientos no funcionales

- **RNF1** — Roles: Supervisor, Administrativo y Operador (spatie), sólo esos tres.
- **RNF2** — Notificaciones desacopladas (cola) para no bloquear el registro.
- **RNF3** — API pública actualizada "minuto a minuto".
- **RNF4** — Alta disponibilidad / Zero Downtime: el centro no frena;
  mantenimientos y migraciones sin baja de servicio o con protocolo de contingencia.
- **RNF5** — Reporte de turno (Libro de Guardia digital) exportado en **PDF/A**
  (ISO de archivado a largo plazo).
- **RNF6** — Exportaciones `.xlsx` en **UTF-8** (tildes, "ñ") para la plataforma
  de Datos Abiertos.

<!-- TODO: performance, seguridad, disponibilidad, etc. -->
