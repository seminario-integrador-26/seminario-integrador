# Modelo de dominio

> Punto de partida, **no definitivo**. Ante ambigüedad, no inventar: dejar
> `// TODO: confirmar con el equipo`.

## Entidades y campos

| Entidad | Campos | Notas |
|---|---|---|
| **Evento** | tipo_evento_id, punto_monitoreo_id (nullable), turno_id, usuario_id, fecha_hora, descripcion, area_derivada, asistencia_operativo (bool) | **Inmutable**. Sin edición directa. |
| **Errata** | evento_id, campo_corregido, valor_anterior, valor_nuevo, motivo, usuario_id, created_at | Tabla separada. Único mecanismo de corrección. |
| **TipoEvento** | nombre, categoria, requiere_ubicacion (bool), es_cuantificable (bool) | Flags independientes. N:M con GrupoInteresado. |
| **PuntoMonitoreo** | nombre, latitud, longitud | Leaflet / OpenStreetMap. |
| **Turno** | fecha, hora_inicio, hora_fin, operador_id | |
| **GrupoInteresado** | nombre | N:M con TipoEvento; 1:N con Contacto. |
| **Contacto** | nombre, numero_whatsapp, grupo_interesado_id | |
| **Usuario** | nombre, email, rol | Rol vía spatie: Operador o Administrador. |

## Relaciones

- Evento **N:1** TipoEvento, PuntoMonitoreo (nullable), Turno, Usuario.
- Evento **1:N** Errata.
- TipoEvento **N:M** GrupoInteresado (tabla pivote).
- GrupoInteresado **1:N** Contacto.
- Turno **N:1** Usuario (operador).

## Categorías (TipoEvento.categoria)

`prevención` · `convivencia urbana` · `seguridad pública`

## Reglas derivadas del modelo

1. Si `TipoEvento.requiere_ubicacion = false`, `Evento.punto_monitoreo_id`
   puede ser `null`.
2. Si `TipoEvento.es_cuantificable = false`, el evento **no** entra en
   estadísticas.
3. Las correcciones **siempre** vía Errata; el Evento original nunca se
   sobreescribe.

<!-- TODO: DER final, tipos de dato exactos, índices, tablas pivote nombradas -->
