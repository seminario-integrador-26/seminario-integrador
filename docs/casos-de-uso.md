# Casos de uso

> Completar en equipo. Un caso de uso por sección, con actor, flujo principal y
> flujos alternativos.

## CU01 — Abrir / cerrar turno de guardia (Supervisor)
- **Actor:** Supervisor.
- **Flujo:** al iniciar la guardia abre el turno (fecha, hora inicio); al
  finalizar lo cierra (hora fin). Los eventos del turno se asocian al turno abierto.

## CU02 — Registrar evento (Supervisor)
- **Actor:** Supervisor.
- **Precondición:** autenticado con rol Supervisor y con un turno abierto.
- **Flujo principal:** carga tipo, fecha/horario, ubicación (si el tipo la
  requiere), descripción; el sistema guarda el evento (inmutable), lo asocia al
  turno activo y dispara la notificación por WhatsApp al grupo correspondiente.
- **Alternativos:** tipo sin ubicación; error en la notificación (no bloquea).

## CU03 — Corregir evento con fe de errata (Supervisor)
- Registrar una errata que referencia al evento sin modificarlo.

## CU04 — Consultar y filtrar eventos (Administrativo / Administrador de sistema)
- Filtros por tipo, fecha, horario, ubicación.

## CU05 — Ver estadísticas y dashboard (Administrativo / Administrador de sistema)
- Agregados mensuales/anuales por tipo y ubicación (excluye no cuantificables).
- El Administrativo es de **solo visualización** (ve dashboards y estadísticas).

## CU06 — Exportar (Administrativo / Administrador de sistema)
- `.xlsx` UTF-8 (datos abiertos) y PDF/A (reporte de turno).

## CU07 — Administrar usuarios y tipos de evento (Administrador de sistema)
- Alta/baja de usuarios y roles; gestión de tipos y categorías de evento.

## CU08 — Consumir API pública (Sistema externo)
- Lectura de eventos y estadísticas vía `/api/v1` (auth por token).

<!-- TODO: detallar flujos alternativos y de excepción de cada CU -->
