# Atalaya — Sistema Visual & Propuestas de Diseño Táctico (SOC)

> **Centro de Monitoreo Urbano (CMU / SOC) — Municipalidad de Villa María**  
> Interfaz táctica de alta densidad orientada al monitoreo continuo 24/7, mitigación de fatiga ocular y cumplimiento estricto de accesibilidad **WCAG AAA**.

---

## 1. Tokens de Color Oficiales

La paleta se estructura a partir del contraste cromático de los instrumentos de aviónica y salas de control de misión, garantizando legibilidad en turnos extendidos:

| Categoría | Nombre del Token | Hex | Descripción / Uso en UI |
| :--- | :--- | :--- | :--- |
| **Superficie** | `atalaya-canvas` | `#031427` | Fondo base canvas (Azul marino obsidiana profundo) |
| **Superficie** | `atalaya-surface` | `#0B1C30` | Paneles, tarjetas, barras de navegación y tablas |
| **Superficie** | `atalaya-elevated` | `#0F2847` | Modales, popovers y capas emergentes elevadas |
| **Retícula** | `atalaya-border` | `#1E3A5F` | Borde técnico sutil de 1px y líneas de división |
| **Acento** | `atalaya-cyan` | `#00D2FF` | Radar glow, crosshairs, selectores activos y telemetría |
| **Acento** | `atalaya-blue-ops` | `#0284C7` | Azul operativo y streams de video de cámaras |
| **Acento** | `atalaya-sky` | `#38BDF8` | Acento de soporte y estado de conexión |
| **Estructura** | `atalaya-blue-deep` | `#0369A1` | Encabezados de gráficos y divisores estructurales |
| **Nivel 1** | `atalaya-orange` | `#F97316` | **Prevención** / Baliza 24/7 de alta visibilidad |
| **Nivel 2** | `atalaya-amber` | `#F59E0B` | **Convivencia Urbana** / Tránsito / Advertencias |
| **Nivel 3** | `atalaya-crimson` | `#EF4444` | **Seguridad Pública** / Alertas críticas / SAME |
| **Texto** | `atalaya-text-primary`| `#FFFFFF` | KPIs, títulos y lecturas críticas (Contraste 18.5:1) |
| **Texto** | `atalaya-text-muted` | `#94A3B8` | Coordenadas, marcas de tiempo y metadatos secundarios |
| **Texto** | `atalaya-text-dim` | `#475569` | Etiquetas de apoyo y estados deshabilitados |

---

## 2. Opciones de Dirección Visual

---

### Opción 1: "Modern Cyber-SOC / Linear Tactical" *(Recomendada)*
*Alineada con el estándar moderno de interfaces de ingeniería (Linear Dark, Vercel, CrowdStrike Falcon).*

- **Geometría y Materialidad:**
  - Estructura *Double-Bezel* táctica: contenedor exterior con borde `1px solid #1E3A5F` y tarjeta interior `#0B1C30` con relieve de luz interior `shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]`.
  - Radio de curvatura sutil y uniforme (`rounded-xl` / 12px) que suaviza la tensión visual sin perder el rigor técnico.
- **Tipografía y Legibilidad:**
  - Tipografía sans-serif de alta legibilidad (`Geist` / `Plus Jakarta Sans`) combinada con `Geist Mono` para coordenadas, IDs de eventos e índices de turnos.
- **Distribución:**
  - Panel izquierdo colapsable con estado de puntos de monitoreo y cámaras.
  - Centro dominado por el mapa oscuro táctico (Leaflet con cartografía invertida / Stamen Toner / Carto Dark).
  - Panel derecho con feed de eventos en vivo y KPIs en tiempo real.
- **Ventaja para el operador:** Es el estilo con menor índice de fatiga visual a lo largo de turnos de 8 a 12 horas.

---

### Opción 2: "Tactical HUD & Telemetry"
*Alineada con terminales de mando aeroespacial, consolas militares y centros de telemetría (Anduril Lattice, Palantir).*

- **Geometría y Materialidad:**
  - Ángulos estrictamente rectos (`rounded-none`). Cero curvaturas.
  - Retícula técnica evidente con líneas divisorias completas en `#1E3A5F`, marcadores de coordenadas en las esquinas (`+`) y corchetes de encuadre `[ ]`.
  - Pulso y halos de radar cian (`#00D2FF`) para indicar barrido de sensores y telemetría de activos.
- **Tipografía y Legibilidad:**
  - Enfoque mono-espaciado dominante (`JetBrains Mono` / `Space Mono`) en mayúsculas técnicas con tracking extendido (`tracking-wider`).
- **Distribución:**
  - Disposición de cabina de comando ("Cockpit"): micro-indicadores modulares, trazas vectoriales de cuadrantes urbanos y tabla de incidentes secuencial ultra-densa.
- **Ventaja para el operador:** Máxima sensación de inmediatez y precisión analítica para supervisores tácticos.

---

### Opción 3: "Swiss Precision Clean Grid"
*Alineada con los principios suizos de diseño funcional (Dieter Rams aplicado a defensa e instrumentación de aviónica).*

- **Geometría y Materialidad:**
  - Grilla matemática pura basada en `display: grid; gap: 1px; background: #1E3A5F;`.
  - Cero sombras difusas, cero desenfoques (`backdrop-blur` prohibido en áreas de datos). Cada bloque es una placa monolítica pura de `#0B1C30`.
  - Botones y acciones técnicas con bordes nítidos de alto contraste.
- **Tipografía y Legibilidad:**
  - Foco en `Inter Display` / `Neue Haas Grotesk` en peso pesado para encabezados de sección y números tabulares claros (`tabular-nums`).
- **Distribución:**
  - Esquema bimodal balanceado: 65% superficie para visualización cartográfica geopolítica/urbana y 35% columna rígida para lista cronológica de incidentes y fe de erratas.
- **Ventaja para el operador:** Máxima velocidad de renderizado, consumo nulo de GPU y cero distracciones decorativas.

---

## 3. Estado de la implementación

Dirección adoptada: **mezcla de la Opción 1 y la Opción 3** — placas monolíticas
sin sombras difusas y grilla suiza de 1px (`.swiss-grid` / `.swiss-panel` en
`resources/css/app.css`), con ángulos rectos y tipografía mono en mayúsculas
para telemetría.

Dónde vive cada cosa:

| Pieza | Archivo |
| :--- | :--- |
| Tokens de color y fuentes | `tailwind.config.js` (prefijo `atalaya-`) |
| Base, grilla suiza y tema oscuro de Leaflet | `resources/css/app.css` |
| Cabecera de sala, reloj y navegación | `resources/js/Layouts/AuthenticatedLayout.jsx` |
| Pantallas de acceso | `resources/js/Layouts/GuestLayout.jsx` |
| Visor cartográfico del panel | `resources/js/Components/TacticalMap.jsx` |
| Consola táctica | `resources/js/Pages/Dashboard.jsx` + `App\Services\DashboardService` |

Notas de implementación:

- La cartografía usa teselas estándar de **OpenStreetMap** oscurecidas por
  filtro CSS (clase `.mapa-tactico`). No se usan teselas oscuras de terceros
  porque hoy exigen API key, y CLAUDE.md fija OSM vía Leaflet.
- El semáforo de color sigue los ejes de clasificación de la RN-02; los tipos
  **Informativos** (no cuantificables, RN-04) van en cian y quedan fuera del
  conteo estadístico del panel.
- Pendientes marcados con `TODO` en la UI, deshabilitados en vez de simulados:
  apertura/cierre de turno (CU01), exportación PDF/A y `.xlsx`, y fe de errata.
