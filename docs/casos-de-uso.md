# Casos de uso

> Completar en equipo. Un caso de uso por sección, con actor, flujo principal y
> flujos alternativos.

## CU01 — Registrar evento (Operador)
- **Actor:** Operador.
- **Precondición:** autenticado con rol Operador.
- **Flujo principal:** carga tipo, fecha/horario, ubicación (si el tipo la
  requiere), descripción, área; el sistema guarda el evento (inmutable) y
  dispara la notificación por WhatsApp al grupo correspondiente.
- **Alternativos:** tipo sin ubicación; error en la notificación (no bloquea).

## CU02 — Corregir evento con fe de errata (Operador/Administrador)
- Registrar una errata que referencia al evento sin modificarlo.

## CU03 — Consultar y filtrar eventos (Administrador)
- Filtros por tipo, fecha, horario, ubicación.

## CU04 — Ver estadísticas y dashboard (Administrador)
- Agregados mensuales/anuales por tipo y ubicación (excluye no cuantificables).

## CU05 — Exportar (Administrador)
- `.xlsx` (datos abiertos) y PDF (reporte de turno).

## CU06 — Consumir API pública (Sistema externo)
- Lectura de eventos y estadísticas vía `/api/v1`.

<!-- TODO: detallar flujos alternativos y de excepción de cada CU -->
