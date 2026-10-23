# Rediseño: Libro de guardia, eventos internos y fin de los turnos

> Estado: **implementado** (2026-10-23), pasos 1 a 6, sin puntos abiertos.
> Pedido del Centro de Monitoreo: los eventos internos (apertura y cierre de
> turno, notas, erratas, otros) se registran en el libro y **se notifican por
> Telegram** como cualquier otro evento.

## Decisiones tomadas

1. **Grupos de evento:** Policía, **Guardia Local** e **Internos** (solo esos tres).
   - `seguridad_ciudadana` → se renombra a **Guardia Local**.
   - `oficios_recepcionados` → sus tipos pasan a **Internos**.
   - Internos suma: **Apertura de turno, Cierre de turno, Nota, Errata, Otros**.
2. **La tabla `turnos` desaparece**, igual que la sección "Turnos" del menú y
   la tarjeta (`AbrirTurnoModal`) que se abre sola al Supervisor sin turno abierto.
   Abrir y cerrar el turno pasa a ser **registrar un evento** de tipo Apertura
   de turno o Cierre de turno.
3. **Se puede registrar sin una apertura vigente:** la apertura y el cierre son
   eventos más del libro y no bloquean nada (desaparece `SinTurnoActivoException`).
4. **PDF del Libro de guardia (US-012):** se elige una Apertura de turno y el
   PDF incluye todo lo registrado hasta el Cierre de turno siguiente.
5. **Datos de producción:** los turnos cargados se **descartan**. Los eventos
   existentes se conservan; solo pierden `turno_id`.
6. Ya hecho: el panel del Panel se llama "+ Libro de guardia" (en vez de
   "+ Feed de eventos registrados").

## Diseño propuesto

- **Errata:** la corrección **sigue en la tabla `erratas`**, porque de ahí sale la
  vista `eventos_vigentes` (estadísticas, mapa, Excel, API: regla 1). Además, al
  cargarla se crea un evento interno "Errata" con un resumen ("Corrige
  AT-2026-0012: Tipo de evento, de «Robos» a «Hurtos». Motivo: …") para que figure en el libro
  y se notifique.
- **Notificación:** sin código nuevo. Todo evento dispara `EventoRegistrado` →
  `NotificarInteresadosListener` → Telegram, según los tipos asociados a cada
  grupo en Administración → Notificaciones.
- **Tipos internos:** `es_cuantificable=false` (fuera de estadísticas) y
  `requiere_ubicacion=false`.
- **Apertura de turno:** el personal presente (hoy `turnos.personal_presente`)
  va en la descripción del evento. **Cierre de turno:** las novedades
  pendientes (hoy `turnos.novedades_pendientes`) también.
- **Guardia vigente** (Panel, encabezado "TURNO #…"): se deriva de la última
  Apertura de turno sin un Cierre posterior.
- **Constancias automáticas:** el evento "Errata" lo crea el sistema y no se
  carga a mano ni se le hace errata.

## Decidido el 2026-10-23 (2ª tanda)

1. **Alcance del Supervisor en Eventos:** ve **todos** los eventos (como el
   Administrativo); desaparece el filtro por turno de `EventoService::visiblesPara`.
2. **Errata se notifica** como cualquier tipo: según la matriz de
   Administración → Notificaciones.
3. **API pública:** **no** expone el grupo Internos.
4. **Apertura y Cierre se cargan desde "Registrar evento"** como un tipo más;
   personal presente y novedades van en la descripción. Sin botón propio.
5. Regla 5 de `CLAUDE.md` ("Evento ↔ turno activo") deja de existir: actualizar
   `CLAUDE.md`, `docs/modelo-dominio.md` y US-012 / US-025 en `docs/backlog.md`
   (paso 6).

## Decidido el 2026-10-23 (3ª tanda)

1. **Los tipos de Oficios (ahora en Internos) siguen contando en
   estadísticas** (`es_cuantificable=true`); solo los 5 internos nuevos no.
2. **La API de estadísticas (`/api/v1/estadisticas/*`) conserva la clave
   `internos`:** da los mismos números que la pantalla (agregados, en la práctica
   solo oficios). "La API no expone Internos" aplica al listado de eventos
   (`/api/v1/eventos`), no a los totales.

## Impacto relevado (≈50 archivos)

- **Backend:** `Turno`, `TurnoService`, `TurnoController`, `AbrirTurnoRequest`,
  `CerrarTurnoRequest`, `SinTurnoActivoException`, `TurnoYaAbiertoException`,
  `EventoService` (registrar, visiblesPara, detalle), `EventoController`,
  `ErrataController`/`ErrataService`, `DashboardService`/`DashboardController`,
  `ReporteService`/`ReporteController` + `resources/views/reportes/turno.blade.php`,
  `HandleInertiaRequests` (`turnoSupervisor`), `User::turnos()`,
  `UsuarioController` (no borrar usuario con turnos), `AuditoriaAcceso`
  (turno_abierto / turno_cerrado), `routes/web.php` (`turnos.*`,
  `reportes.turno.pdf`), `GrupoEvento` (comentario).
- **Base:** migración nueva que saca `eventos.turno_id`, recrea la vista
  `eventos_vigentes` (hoy incluye `e.turno_id`), dropea `turnos`, renombra el
  grupo y mueve los tipos de Oficios Recepcionados a Internos.
  `TipoEventoSeeder` con el catálogo nuevo. `PermissionSeeder`
  (`turnos.gestionar`), `RoleSeeder`, `EventoDemoSeeder`, factories
  `TurnoFactory` y `EventoFactory`.
- **Frontend:** `AbrirTurnoModal`, `AuthenticatedLayout` (menú Turnos y badge
  de turno), `Dashboard`, `Eventos/Create`, `Eventos/Index`, `Eventos/Show`,
  `Reportes/Index`, `Turnos/Index` (se borra), `Usuarios/Auditoria`,
  `TacticalMap`, `Welcome`, `types/index.d.ts`.
- **Tests:** Turnos, Registro/Consulta/Detalle de eventos, Errata, Reportes,
  Dashboard, Notificaciones, Estadísticas, Bitácora, EventoDemoSeeder.

## Orden sugerido (un commit por paso)

1. ✅ Grupos y catálogo: migración `2026_10_23_000001` renombra las filas
   (`seguridad_ciudadana` → `guardia_local`, `oficios_recepcionados` →
   `internos`); `TipoEventoSeeder` suma Apertura de turno, Cierre de turno,
   Nota, Errata y Otros (no cuantificables). Front y tests con los códigos nuevos.
2–4. ✅ (un solo commit: estaban acoplados) Apertura y Cierre como eventos
   (`LibroGuardiaService`: turno vigente = última Apertura sin Cierre posterior;
   un Cierre registrado después de otra Apertura no cierra la anterior); sin
   tarjeta automática ni menú Turnos; migración `2026_10_23_000002` borra
   `turnos` y `eventos.turno_id` y recrea `eventos_vigentes`; el Supervisor ve
   todo el libro; permisos `eventos.consultar_historico` y `turnos.gestionar`
   eliminados; PDF (`reportes.guardia.pdf`) de una Apertura al Cierre
   siguiente, con valores vigentes; sin Cierre, no se genera.
5. ✅ Evento interno "Errata" automático al cargar una fe de errata
   (`ErrataService::corregir`, misma transacción, se notifica según la matriz).
   El tipo Errata no se ofrece ni se acepta en el registro manual ni como
   corrección, y a una constancia no se le hace errata. La API de eventos
   (`/api/v1/eventos`) no publica el grupo Internos.
6. ✅ Docs (`CLAUDE.md`, modelo de dominio, backlog, casos de uso,
   requerimientos, deploy). **Al desplegar:** la migración borra los turnos de
   producción (`deploy.sh` hace backup antes de migrar) y `deploy.sh` corre
   `TipoEventoSeeder` (tipos internos) y `PermissionSeeder` (borra los permisos
   obsoletos). Revisar en Administración → Notificaciones a qué grupos se
   avisan los tipos internos.
