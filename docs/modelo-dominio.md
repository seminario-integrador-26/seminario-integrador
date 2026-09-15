# Modelo de dominio

> Punto de partida, **no definitivo**. Ante ambigüedad, no inventar: dejar
> `// TODO: confirmar con el equipo`.

## Entidades y campos

| Entidad | Campos | Notas |
|---|---|---|
| **Evento** | tipo_evento_id, punto_monitoreo_id (nullable), turno_id, usuario_id, fecha_hora, timestamp_video (hh:mm:ss, nullable), descripcion (nullable), created_at, asistencia_operativo (bool, pendiente) | **Inmutable** (sin updated_at; el modelo bloquea update/delete). Ligado al turno activo (RN). Sin derivación. `timestamp_video` obligatorio cuando hay PM (US-003). |
| **Errata** | evento_id, campo_corregido, valor_anterior, valor_nuevo, motivo, usuario_id, created_at | Tabla separada. Único mecanismo de corrección. |
| **TipoEvento** | nombre, categoria, requiere_ubicacion (bool), es_cuantificable (bool) | Flags independientes. N:M con GrupoInteresado. |
| **PuntoMonitoreo** | codigo (único), nombre, jurisdiccion (`municipal` \| `provincial`), latitud, longitud (nullable) | 394 PM: 235 municipales + 159 provinciales. Leaflet / OpenStreetMap. Carga desde `database/data/puntos_monitoreo.csv`. |
| **Turno** | fecha, hora_inicio, hora_fin, supervisor_id | Lo abre/cierra un Supervisor. |
| **GrupoInteresado** | nombre | N:M con TipoEvento; 1:N con Contacto. |
| **Contacto** | nombre, numero_whatsapp, grupo_interesado_id | |
| **Usuario** | nombre, username, email, rol, intentos_fallidos, bloqueado_hasta | Login por **username** (US-001); el email es el canal de recuperación de clave. Rol vía spatie: Supervisor, Administrativo o Administrador de sistema. Bloqueo temporal de cuenta. |
| **AuditoriaAcceso** | user_id (nullable), email, evento, ip_address, user_agent, created_at | Traza de login/logout/intento fallido/bloqueo (US-001). Inmutable, solo created_at. |

## Relaciones

- Evento **N:1** TipoEvento, PuntoMonitoreo (nullable), Turno, Usuario.
- Evento **1:N** Errata.
- TipoEvento **N:M** GrupoInteresado (tabla pivote).
- GrupoInteresado **1:N** Contacto.
- Turno **N:1** Usuario (supervisor).
- AuditoriaAcceso **N:1** Usuario (nullable: el email puede no existir).

## Categorías (TipoEvento.categoria)

Valor persistido → etiqueta (`TipoEvento::CATEGORIAS`):
`prevencion` → Prevención · `convivencia_urbana` → Convivencia Urbana ·
`seguridad_publica` → Seguridad Pública · `informativo` → Informativo

Tablas: `tipos_evento`, `puntos_monitoreo`, `turnos`, `eventos`.

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
