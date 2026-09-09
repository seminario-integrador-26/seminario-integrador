# Arquitectura y estructura de carpetas

> Documento de referencia para **todo el equipo**. Antes de crear un archivo
> nuevo, verificá acá dónde va y con qué otras capas puede hablar. El objetivo
> es que todos sigamos las mismas reglas y el código sea predecible.

## 1. Idea general

Es un **monolito Laravel + Inertia.js + React**. No hay microservicios. El
frontend interno (Supervisor / Administrativo / Operador) se sirve con **Inertia**, así que
**no existe una API interna** que el frontend consuma: el Controller pasa props
directo a un componente React.

Aparte, y solo para consumo externo (Datos Abiertos del Gobierno), existe una
**API pública REST de solo lectura** bajo `/api/v1`. Son dos cosas distintas
que conviven:

| Frontend interno (Inertia) | API pública (`/api/v1`) |
|---|---|
| Supervisor, Administrativo, Operador | Sistemas externos |
| Lectura y escritura | Solo lectura |
| Controllers en `Http/Controllers/` | Controllers en `Http/Controllers/Api/V1/` |
| Devuelve `Inertia::render(...)` | Devuelve JSON |
| Auth de sesión + roles spatie | Sanctum token (personal access token) |

Además, **Blade puro** se usa **solo** para las plantillas que se convierten a
PDF (`resources/views/reportes/`). El resto de la UI es React.

## 2. Flujo de una petición (el camino feliz)

```
Request
  → Route (routes/web.php | routes/api.php)
    → Controller            (FINO: valida entrada, orquesta, arma la respuesta)
      → Service             (TODA la lógica de negocio vive acá)
        → Model (Eloquent)  → PostgreSQL
        → Contracts (interfaces): Notificador / Exportador / EstadisticaCalculador
              ↑ la implementación concreta la inyecta el container / una Factory
  ← el Controller responde con Inertia::render(...) o JSON
```

Side-effects (notificaciones, PDF/XLSX pesados) **no** se hacen dentro del
request:

```
Service persiste un Evento
  → dispara Event de dominio (EventoRegistrado)      [OBSERVER]
      → Listener en cola (NotificarInteresadosListener)
          → despacha Job (EnviarNotificacionWhatsapp)  [Queue: database]
              → usa NotificadorInterface → WhatsappNotificador
```

Así, si WhatsApp falla o está lento, **el evento igual quedó guardado** y el
usuario recibió respuesta rápido.

## 3. Qué hace cada carpeta y con quién habla

### `app/Http/Controllers/`
- **Rol:** capa de entrada web. Reciben el Request, delegan en un Service y
  devuelven `Inertia::render(...)`.
- **Regla:** son **finos**. Nada de lógica de negocio, ni queries directas, ni
  `if` de reglas del dominio. Si un método crece, la lógica va a un Service.
- **Habla con:** Services. **No** con Models directamente (salvo route-model
  binding para recibir el registro), **no** con otros Controllers.
- **Ojo:** no hay `edit`/`update`/`destroy` de eventos → los eventos son
  inmutables (se corrigen con Errata).

### `app/Http/Controllers/Api/V1/`
- **Rol:** API pública de solo lectura (JSON). Solo verbos GET.
- **Habla con:** Services (los mismos que el frontend interno, si aplica) o
  Models de solo lectura. Versionado en la carpeta `V1` para poder agregar `V2`
  sin romper consumidores.

### `app/Services/`
- **Rol:** el **cerebro**. Toda la lógica de negocio. Un Service = una
  responsabilidad (**Single Responsibility**):
  - `EventoService` — registrar / consultar eventos.
  - `ErrataService` — corregir eventos (fe de errata).
  - `EstadisticaService` — calcular agregados.
  - `ExportacionService` — coordinar exportaciones (delega en `ExportadorFactory`).
- **Habla con:** Models, Contracts (interfaces), Factories, y dispara Events.
- **Regla clave (Dependency Inversion):** cuando un Service necesita notificar
  o exportar, depende de la **interfaz** (`NotificadorInterface`,
  `ExportadorInterface`), **nunca** de la clase concreta. Así se puede testear
  con un mock sin llamar a WhatsApp real.

### `app/Services/Exportadores/` y `app/Services/Notificadores/`
- Implementaciones **concretas** de las interfaces de `Contracts/`
  (`XlsxExportador`, `PdfExportador`, `WhatsappNotificador`).
- **Patrón Strategy:** son estrategias intercambiables. Agregar un formato o un
  canal nuevo = **agregar una clase acá**, sin tocar nada existente (Open/Closed).

### `app/Contracts/`
- Solo **interfaces**, chicas y específicas (**Interface Segregation**):
  `NotificadorInterface` (solo `notificar()`), `ExportadorInterface` (solo
  `exportar()`), `EstadisticaCalculadorInterface`.
- Es el "enchufe" entre quien pide (Services) y quien implementa
  (Exportadores/Notificadores). Nadie instancia una concreta a mano: se resuelve
  por container o Factory.

### `app/Factories/`
- **Patrón Factory:** deciden **qué implementación concreta** crear según un
  parámetro (formato `'xlsx'`/`'pdf'`, canal `'whatsapp'`). Evita `if/switch`
  desparramados en Controllers y Services.
- Se usan cuando una interfaz tiene **varias** implementaciones (por eso el
  exportador NO se bindea 1:1 en el ServiceProvider).

### `app/Events/` y `app/Listeners/`
- **Patrón Observer** vía el sistema de Events de Laravel.
- `Events/` = hechos de dominio que ya ocurrieron (`EventoRegistrado`).
- `Listeners/` = reacciones (`NotificarInteresadosListener`, en cola).
- El que dispara el evento **no sabe** quién escucha → desacople total.
- El mapeo evento → listener se registra en `Providers/EventServiceProvider`.

### `app/Jobs/`
- Trabajo pesado o con dependencia externa que corre **en cola**
  (`EnviarNotificacionWhatsapp`). Driver de cola: `database`.
- Un Listener despacha el Job; el Job hace el trabajo real usando una interfaz.

### `app/Models/`
- Eloquent (Active Record). Representan tablas + relaciones + casts.
- **No** metemos lógica de negocio compleja acá; reglas transversales van en
  Services. Sí van relaciones, scopes y accessors simples.
- **No** usamos Repository Pattern sobre Eloquent (sería sobre-ingeniería para
  este alcance), salvo que la cátedra lo exija; en ese caso se consulta al
  equipo antes.

### `app/Providers/`
- **Cableado de la app (Dependency Inversion en la práctica):**
  - `AppServiceProvider` — bindea `NotificadorInterface → WhatsappNotificador`.
  - `EventServiceProvider` — mapea Events → Listeners.

### `routes/`
- `web.php` — rutas Inertia autenticadas (Supervisor/Administrativo/Operador), con roles.
- `api.php` — API pública versionada `/api/v1`, solo lectura.

### `resources/js/` (frontend React + Inertia)
- `Pages/` — una carpeta por módulo (`Eventos/`, `Errata/`, `Dashboard/`,
  `Reportes/`). Cada página es el destino de un `Inertia::render('Modulo/Pagina')`.
  **Convención:** el nombre pasado en el Controller = ruta dentro de `Pages/`.
- `Components/` — componentes reutilizables entre páginas (tablas, el mapa
  Leaflet, gráficos Recharts, inputs).
- `Layouts/` — cascarones de página (layout autenticado, etc.).
- Los datos llegan como **props** desde el Controller (no se hace `fetch` a una
  API interna).

### `resources/views/reportes/` (Blade puro)
- **Único** lugar con Blade. Plantillas que `PdfExportador` renderiza a PDF.
  No confundir con la UI (que es React).

### `docs/`
- Documentación viva del proyecto (este archivo, requerimientos, relevamiento,
  casos de uso, modelo de dominio).

## 4. Reglas de comunicación entre capas (resumen para no equivocarse)

- ✅ Controller → Service → (Model | Contract | Event)
- ✅ Service → Model, Service → Contract (interfaz), Service → Factory, Service → Event
- ✅ Listener → Job → Notificador (vía interfaz)
- ❌ Controller → Model con lógica de negocio (usá un Service)
- ❌ Controller → otro Controller
- ❌ Service o Controller instanciando una clase concreta de Notificador/Exportador
  a mano (pedila por interfaz o Factory)
- ❌ Lógica de negocio dentro de un Model, una Route o un componente React
- ❌ Editar un Evento (usar Errata) o crear roles fuera de Supervisor/Administrativo/Operador

## 5. Por qué esta separación

- **Testeable:** al depender de interfaces, los Services se prueban con mocks
  sin tocar WhatsApp ni generar archivos reales.
- **Extensible sin romper:** formatos y canales nuevos = clases nuevas
  (Open/Closed + Strategy), no ediciones riesgosas.
- **Desacoplado:** el registro de un evento no se cae si un side-effect falla
  (Observer + Queue).
- **Predecible para un equipo:** cada tipo de código tiene un único lugar
  correcto, así cualquier dev sabe dónde buscar y dónde escribir.
