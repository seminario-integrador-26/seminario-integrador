# Modelo de dominio

> Punto de partida, **no definitivo**. Ante ambigüedad, no inventar: dejar
> `// TODO: confirmar con el equipo`.

## Diagrama de clases (equipo)

![Diagrama de clases del dominio](diagrama-clases.jpeg)

Diagrama de análisis elaborado por el equipo. La versión generada desde el
código está en `diagrams/clases-dominio.puml` (y su DER en
`diagrams/der-dominio.html`).

### Diferencias entre el diagrama y lo implementado

El diagrama y el código no coinciden en todo. Hasta decidir cuál se ajusta,
esta tabla deja registradas las diferencias.
// TODO: confirmar con el equipo qué se actualiza (el diagrama o el código).

| En el diagrama | En el código | Comentario |
|---|---|---|
| `TipoPuntoMonitoreo` (entidad) | `PuntoMonitoreo.jurisdiccion` (`municipal` \| `provincial`) | El doc solo distingue municipal/provincial (RN11): alcanza con un campo. |
| `Barrio` y `Cuadrante` (entidades) | `PuntoMonitoreo.zona` (texto) | Una sola agrupación libre. Si se necesitan barrio y cuadrante por separado, pasar a entidades. |
| `PuntoMonitoreo.descripcion` | `PuntoMonitoreo.codigo` + `nombre` | El código identifica al PM en el listado oficial (US-024). |
| `Grupo` asociado a `Evento` | — | No hay agrupación de eventos. ¿Qué representa? |
| `Evento.eventoPrecedente` (0..1) | — | No implementado: ¿encadenar eventos relacionados? |
| — | `Evento.timestamp_video` | Ya no se carga (decisión 2026-10-26); queda solo en eventos anteriores. |
| `GrupoEvento` 1 — `TipoEvento` | `TipoEvento.categoria` (Prevención, Convivencia Urbana, Seguridad Pública, Informativo) | Equivalente; en el código es un campo con valores fijos. |
| `TipoEvento.notifica` (bool) | `TipoEvento` N:M `GrupoInteresado` | Se notifica si el tipo tiene grupos asociados (US-006/US-028). |
| — | `TipoEvento.requiere_ubicacion`, `es_cuantificable` | Reglas 3 y 4 de CLAUDE.md. Faltan en el diagrama. |
| `Interesado` (sin atributos ni relaciones) | `GrupoInteresado` 1:N `Contacto` | Destinatarios de WhatsApp. |
| `Turno` | — (eventos internos Apertura de turno / Cierre de turno) | Rediseño 2026-10-23: sin tabla de turnos (`docs/rediseno-libro-guardia.md`). |
| `Usuario.telefono` | — | No se pide en el backlog. |
| `Auditoria` (sin atributos) | `AuditoriaAcceso` (usuario, rol, acción, detalle, IP, fecha) | US-026. |
| — | `Errata` | Regla de negocio 1: falta en el diagrama. |
| — | `Notificacion` | Resultado de cada envío por grupo (US-006/US-007). |

## Entidades y campos

| Entidad | Campos | Notas |
|---|---|---|
| **Evento** | tipo_evento_id, punto_monitoreo_id (nullable), barrio_id y direccion (nullable, solo sin PM), usuario_id, fecha_hora, timestamp_video (hh:mm:ss, nullable, ya no se carga), descripcion, created_at | **Inmutable** (sin updated_at; el modelo bloquea update/delete). Sin derivación. Abrir y cerrar el turno también son eventos (grupo Internos). descripción obligatoria (US-003/US-005). Sin PM: barrio y dirección, obligatorios si el tipo exige ubicación. |
| **Errata** | evento_id, motivo, usuario_id, created_at (campo_corregido, valor_anterior, valor_nuevo: solo en las anteriores a 2026-10-23) | Tabla separada, inmutable. Único mecanismo de corrección: registra el motivo; los datos del evento quedan bloqueados. Las erratas viejas con campo y valores siguen aplicando en `eventos_vigentes`. |
| **TipoEvento** | nombre, categoria, requiere_ubicacion (bool), es_cuantificable (bool) | Flags independientes. N:M con GrupoInteresado. |
| **PuntoMonitoreo** | codigo (único), nombre, jurisdiccion (`municipal` \| `provincial`), zona (nullable), latitud, longitud (nullable) | 394 PM: 235 municipales + 159 provinciales. Coordenadas dentro de Villa María. Se cargan desde Puntos → Importación masiva (US-024). |
| **GrupoInteresado** | nombre, identificador_whatsapp (nullable) | N:M con TipoEvento; 1:N con Contacto. |
| **Contacto** | nombre, numero_whatsapp, grupo_interesado_id | |
| **Notificacion** | evento_id, grupo_interesado_id, canal, mensaje, estado, intentos, error, enviada_at | Un envío por evento y grupo: pendiente / enviada / fallida (US-006/US-007). |
| **Usuario** | nombre, username, email, intentos_fallidos, bloqueado_hasta | Login por **username** (US-001); el email es el canal de recuperación de clave. Roles vía spatie: Supervisor, Administrativo o Administrador de sistema (uno o más). Bloqueo temporal de cuenta. |
| **AuditoriaAcceso** | user_id (nullable), email, rol, evento, descripcion, ip_address, user_agent, created_at | Log de accesos y acciones de dominio (US-026). Inmutable, solo created_at. |

## Relaciones

- Evento **N:1** TipoEvento, PuntoMonitoreo (nullable), Usuario.
- Evento **1:N** Errata, Notificacion.
- TipoEvento **N:M** GrupoInteresado (tabla pivote `grupo_interesado_tipo_evento`).
- GrupoInteresado **1:N** Contacto, Notificacion.
- AuditoriaAcceso **N:1** Usuario (nullable: el email puede no existir).

## Categorías (TipoEvento.categoria)

Valor persistido → etiqueta (`TipoEvento::CATEGORIAS`):
`prevencion` → Prevención · `convivencia_urbana` → Convivencia Urbana ·
`seguridad_publica` → Seguridad Pública · `informativo` → Informativo

Tablas: `grupos_evento`, `tipos_evento`, `puntos_monitoreo`, `eventos`, `erratas`,
`grupos_interesados`, `contactos`, `grupo_interesado_tipo_evento`,
`notificaciones`, `auditoria_accesos`.

> Los tipos de categoría `informativo` suelen ser **no cuantificables**
> (`es_cuantificable = false`) y no entran en estadísticas.

## Reglas derivadas del modelo

1. Si `TipoEvento.requiere_ubicacion = false`, `Evento.punto_monitoreo_id`
   puede ser `null`.
2. Si `TipoEvento.es_cuantificable = false`, el evento **no** entra en
   estadísticas.
3. Las correcciones **siempre** vía Errata; el Evento original nunca se
   sobreescribe.
4. El turno vigente es la última **Apertura de turno** sin un **Cierre de
   turno** posterior; sin turno vigente solo se registra la Apertura (no hay
   eventos sin turno). El Libro de
   guardia en PDF va de una Apertura al Cierre siguiente.

<!-- TODO: tipos de dato exactos e índices -->
