# Modelo de dominio

> Punto de partida, **no definitivo**. Ante ambigüedad, no inventar: dejar
> `// TODO: confirmar con el equipo`.

## Entidades y campos

| Entidad | Campos | Notas |
|---|---|---|
| **Evento** | tipo_evento_id, punto_monitoreo_id (nullable), turno_id, usuario_id, fecha_hora, descripcion, asistencia_operativo (bool) | **Inmutable**. Sin edición directa. Ligado al turno activo (RN). Sin derivación. |
| **Errata** | evento_id, campo_corregido, valor_anterior, valor_nuevo, motivo, usuario_id, created_at | Tabla separada. Único mecanismo de corrección. |
| **TipoEvento** | nombre, categoria, requiere_ubicacion (bool), es_cuantificable (bool) | Flags independientes. N:M con GrupoInteresado. |
| **PuntoMonitoreo** | nombre, latitud, longitud | Leaflet / OpenStreetMap. |
| **Turno** | fecha, hora_inicio, hora_fin, supervisor_id | Lo abre/cierra un Supervisor. |
| **GrupoInteresado** | nombre | N:M con TipoEvento; 1:N con Contacto. |
| **Contacto** | nombre, numero_whatsapp, grupo_interesado_id | |
| **Usuario** | nombre, email, rol | Rol vía spatie: Supervisor, Administrativo u Operador. |

## Relaciones

- Evento **N:1** TipoEvento, PuntoMonitoreo (nullable), Turno, Usuario.
- Evento **1:N** Errata.
- TipoEvento **N:M** GrupoInteresado (tabla pivote).
- GrupoInteresado **1:N** Contacto.
- Turno **N:1** Usuario (supervisor).

## Categorías (TipoEvento.categoria)

`prevención` · `convivencia urbana` · `seguridad pública` · `informativo`

> Los tipos de categoría `informativo` suelen ser **no cuantificables**
> (`es_cuantificable = false`) y no entran en estadísticas.

## Reglas derivadas del modelo

1. Si `TipoEvento.requiere_ubicacion = false`, `Evento.punto_monitoreo_id`
   puede ser `null`.
2. Si `TipoEvento.es_cuantificable = false`, el evento **no** entra en
   estadísticas.
3. Las correcciones **siempre** vía Errata; el Evento original nunca se
   sobreescribe.
4. Todo Evento se asocia al **Turno activo** al momento de la carga
   (`turno_id` obligatorio; no hay eventos sin turno).

<!-- TODO: DER final, tipos de dato exactos, índices, tablas pivote nombradas -->
