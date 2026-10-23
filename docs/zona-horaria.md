# Corrección de zona horaria (UTC → hora argentina)

Hasta el 2026-10-15 la app corría en **UTC** (`config/app.php`). Desde ese
cambio corre en `America/Argentina/Cordoba` (UTC-3, sin horario de verano).

Las columnas de fecha de PostgreSQL **no guardan la zona**: lo que se grabó con
la hora del servidor (`now()`) quedó **3 horas adelantado**. Lo que cargó a mano
el Supervisor (fecha y horario del hecho) ya estaba en hora local y **no** se toca.

## Qué hay que corregir en una base con datos previos al cambio

| Tabla | Columnas | Por qué |
|---|---|---|
| todas | `created_at`, `updated_at` | las pone Laravel con `now()` |
| `turnos` | `fecha` + `hora_inicio`, `fecha_fin` + `hora_fin` | apertura/cierre con `Carbon::now()` (TurnoService). La tabla se eliminó el 2026-10-23: omitir sus `UPDATE` si ya se migró. |
| `notificaciones` | `enviada_at` | `now()` al enviar |
| `eventos` | **NO** tocar `fecha_hora` ni `timestamp_video` | los carga el Supervisor en hora local |

## Script (PostgreSQL) — correr UNA sola vez, con backup previo

> Correrlo solo sobre filas creadas **antes** de desplegar el cambio de zona.
> Si el deploy fue en otro momento, ajustar el `WHERE created_at < ...`.
>
> **Correrlo apenas termina el deploy, antes de que se cargue nada.** Lo que se
> graba después del deploy queda en hora local, o sea 3 h por debajo del corte
> (expresado en UTC): durante esas 3 horas las filas nuevas también cumplen
> `created_at < corte` y el script las atrasaría por error. Si ya hubo carga,
> filtrar por `id <= <último id previo al deploy>` en vez de por fecha.

```sql
BEGIN;

-- Marca de corte: momento del deploy, expresado en la hora VIEJA (UTC).
-- Ejemplo: deploy el 2026-10-15 a las 10:00 hora argentina = 13:00 UTC.
\set corte '''2026-10-15 13:00:00'''

UPDATE turnos
SET fecha       = ((fecha + hora_inicio) - interval '3 hours')::date,
    hora_inicio = ((fecha + hora_inicio) - interval '3 hours')::time
WHERE created_at < :corte;

UPDATE turnos
SET fecha_fin = ((fecha_fin + hora_fin) - interval '3 hours')::date,
    hora_fin  = ((fecha_fin + hora_fin) - interval '3 hours')::time
WHERE hora_fin IS NOT NULL AND fecha_fin IS NOT NULL AND updated_at < :corte;

UPDATE notificaciones SET enviada_at = enviada_at - interval '3 hours'
WHERE enviada_at IS NOT NULL AND created_at < :corte;

-- Timestamps de Laravel (al final, porque arriba se filtra por ellos).
UPDATE turnos             SET created_at = created_at - interval '3 hours', updated_at = updated_at - interval '3 hours' WHERE created_at < :corte;
UPDATE eventos            SET created_at = created_at - interval '3 hours' WHERE created_at < :corte;
UPDATE notificaciones     SET created_at = created_at - interval '3 hours', updated_at = updated_at - interval '3 hours' WHERE created_at < :corte;
UPDATE auditoria_accesos  SET created_at = created_at - interval '3 hours' WHERE created_at < :corte;
UPDATE users              SET created_at = created_at - interval '3 hours', updated_at = updated_at - interval '3 hours' WHERE created_at < :corte;

-- Revisar antes de confirmar:
SELECT id, fecha, hora_inicio, fecha_fin, hora_fin FROM turnos ORDER BY id DESC LIMIT 10;

COMMIT;  -- o ROLLBACK si algo no cierra
```

`auditoria_accesos` es inmutable para la app, pero corregir la zona de sus
`created_at` no altera qué pasó, solo cuándo se muestra; queda a criterio del
equipo si se corrige o se deja documentado el corte.
