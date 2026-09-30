# Prototipos y video demo — Atalaya

Material de presentación de lo implementado hasta ahora. No forma parte de la app.

| Archivo | Qué es |
|---|---|
| `prototipos/index.html` | Prototipo navegable (8 pantallas: login, panel, registrar evento, consulta, turnos, usuarios, roles, auditoría). Se abre con doble clic; necesita internet para Tailwind y Leaflet (CDN). El mapa es una imagen local de Villa María (teselas OSM pre-descargadas) porque OSM bloquea teselas pedidas desde `file://`. |
| `brag.mp4` / `brag.jpg` | Video demo de 24,5 s (1920×1080) y su miniatura. |
| `composition/` | Proyecto [HyperFrames](https://hyperframes.dev) del video. Re-render: `cd composition && npx hyperframes render --quality delivery --output ../brag.mp4`. |
| `brag-plan.md`, `composition-brief.md` | Guion/storyboard y especificación del video. |
| `share-copy.txt` | Texto para compartir. |

Todos los datos (personas, puntos de monitoreo, eventos) son ficticios.
Funcionalidades todavía no implementadas (WhatsApp, PDF/A, .xlsx, estadísticas, fe de errata) no se muestran como operativas.

Créditos: mapa © colaboradores de OpenStreetMap · música "Happy Beats / Business Moves" (ende.app) · SFX CC0 (Kenney.nl, unicae_games).
