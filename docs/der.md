# DER — Diagrama Entidad-Relación

Esquema PostgreSQL actual (migraciones hasta `2026_11_11_000001_create_ampliaciones_table`).
Se omiten las tablas de infraestructura de Laravel (`cache`, `jobs`, `sessions`,
`password_reset_tokens`, `failed_jobs`) y las de permisos finos de spatie
(`permissions`, `role_has_permissions`, `model_has_permissions`).

![DER](der.png)

Imagen generada con `npx @mermaid-js/mermaid-cli -i docs/der.md -o docs/der.svg`
(también `der.svg`). Fuente editable:

```mermaid
erDiagram
    users ||--o{ eventos : "registra"
    users ||--o{ erratas : "redacta"
    users ||--o{ ampliaciones : "redacta"
    users |o--o{ auditoria_accesos : "genera"
    users ||--o{ model_has_roles : "tiene"
    roles ||--o{ model_has_roles : "asignado en"
    users ||--o{ personal_access_tokens : "posee (API)"

    grupos_evento ||--o{ tipos_evento : "agrupa"
    tipos_evento ||--o{ eventos : "clasifica"
    tipos_evento ||--o{ grupo_interesado_tipo_evento : ""
    grupos_interesados ||--o{ grupo_interesado_tipo_evento : "se suscribe"
    grupos_interesados ||--o{ contactos : "contiene"
    grupos_interesados ||--o{ notificaciones : "recibe"

    tipos_punto_monitoreo ||--o{ puntos_monitoreo : "tipifica"
    barrios |o--o{ puntos_monitoreo : "ubica"
    cuadrantes |o--o{ puntos_monitoreo : "ubica"
    puntos_monitoreo |o--o{ eventos : "localiza"
    barrios |o--o{ eventos : "localiza (sin PM)"

    eventos ||--o{ erratas : "corregido por"
    eventos ||--o{ ampliaciones : "ampliado por"
    eventos ||--o{ notificaciones : "dispara"

    users {
        bigint id PK
        string name
        string username UK
        string email UK
        timestamp email_verified_at
        string password
        smallint intentos_fallidos
        timestamp bloqueado_hasta
        timestamps timestamps
    }
    roles {
        bigint id PK
        string name "Supervisor | Administrativo | Administrador de sistema"
        string guard_name
    }
    model_has_roles {
        bigint role_id PK,FK
        string model_type PK
        bigint model_id PK,FK
    }
    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id FK
        string name
        string token UK
        text abilities
        timestamp expires_at
    }
    auditoria_accesos {
        bigint id PK
        bigint user_id FK "nullable"
        string email
        string rol
        string evento
        string descripcion
        string ip_address
        text user_agent
        timestamp created_at
    }
    grupos_evento {
        bigint id PK
        string codigo UK
        string nombre UK "Policía | Guardia Local | Internos"
        text descripcion
    }
    tipos_evento {
        bigint id PK
        bigint grupo_evento_id FK "UK(grupo, nombre)"
        string nombre
        text descripcion
        boolean requiere_ubicacion
        boolean es_cuantificable
        boolean notifica
    }
    tipos_punto_monitoreo {
        bigint id PK
        string codigo UK
        string nombre UK
        text descripcion
    }
    barrios {
        bigint id PK
        string nombre UK
    }
    cuadrantes {
        bigint id PK
        string nombre UK
    }
    puntos_monitoreo {
        bigint id PK
        string codigo UK
        string nombre
        bigint tipo_punto_monitoreo_id FK
        bigint barrio_id FK "nullable"
        bigint cuadrante_id FK "nullable"
        string descripcion
        decimal latitud
        decimal longitud
    }
    eventos {
        bigint id PK
        bigint tipo_evento_id FK
        bigint punto_monitoreo_id FK "nullable"
        bigint barrio_id FK "nullable (sin PM)"
        string direccion
        string franja "solo Apertura"
        tinyint grupo_guardia "solo Apertura (1-4)"
        bigint usuario_id FK
        timestamp fecha_hora
        time timestamp_video
        text descripcion
        timestamp created_at "inmutable"
    }
    erratas {
        bigint id PK
        bigint evento_id FK
        bigint usuario_id FK
        string campo_corregido "nullable (legado)"
        text valor_anterior "legado"
        text valor_nuevo "legado"
        text motivo
        timestamp created_at
    }
    ampliaciones {
        bigint id PK
        bigint evento_id FK
        bigint usuario_id FK
        text detalle
        timestamp created_at
    }
    grupos_interesados {
        bigint id PK
        string nombre UK
        string identificador_whatsapp
        string telegram_chat_id
    }
    contactos {
        bigint id PK
        bigint grupo_interesado_id FK
        string nombre
        string numero_whatsapp
    }
    grupo_interesado_tipo_evento {
        bigint grupo_interesado_id PK,FK
        bigint tipo_evento_id PK,FK
    }
    notificaciones {
        bigint id PK
        bigint evento_id FK
        bigint grupo_interesado_id FK
        string canal "telegram | whatsapp"
        text mensaje
        string estado
        smallint intentos
        text error
        timestamp enviada_at
    }
    parametros {
        string clave PK
        integer valor
    }
```

## Notas

- **Sin tabla de turnos.** El turno es un par de eventos internos (Apertura /
  Cierre de turno, grupo Internos); `franja` y `grupo_guardia` solo se cargan en
  la Apertura.
- **Inmutabilidad.** `eventos`, `erratas` y `ampliaciones` solo tienen
  `created_at`. Cada errata/ampliación genera además una constancia (evento
  interno Errata / Ampliación) en `eventos`.
- **Vista `eventos_vigentes`.** Misma forma que `eventos` con las correcciones de
  erratas legadas (campo/valor) aplicadas; la leen estadísticas, mapa,
  exportaciones y API.
- **Ubicación.** Con PM el barrio sale de `puntos_monitoreo.barrio_id`; sin PM
  se usa `eventos.barrio_id` + `direccion`.
- `parametros` es una tabla clave/valor de configuración, sin relaciones.
