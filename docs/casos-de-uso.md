# Casos de uso

> Completar en equipo. Un caso de uso por sección, con actor, flujo principal y
> flujos alternativos.

## CU01 — Abrir / cerrar turno de guardia (Supervisor)
- **Actor:** Supervisor.
- **Flujo:** al iniciar la guardia registra el evento interno **Apertura de
  turno** (personal presente en la descripción); al finalizar, **Cierre de
  turno** (novedades pendientes). Rediseño 2026-10-23: no hay tabla de turnos
  y los eventos no se asocian a un turno (`docs/rediseno-libro-guardia.md`).

## CU02 — Registrar evento (Supervisor)
- **Actor:** Supervisor.
- **Precondición:** autenticado con rol Supervisor y un turno abierto (Apertura de turno
  vigente); sin turno solo puede registrar la Apertura.
- **Flujo principal:** carga tipo, fecha/horario, ubicación (si el tipo la
  requiere), descripción; el sistema guarda el evento (inmutable), lo asocia al
  turno activo y dispara la notificación por WhatsApp al grupo correspondiente.
- **Alternativos:** tipo sin ubicación; error en la notificación (no bloquea).

## CU03 — Corregir evento con fe de errata (Supervisor)
- Registrar una errata que referencia al evento sin modificarlo: los datos del
  evento se ven bloqueados y solo se escribe el motivo de la corrección.

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
