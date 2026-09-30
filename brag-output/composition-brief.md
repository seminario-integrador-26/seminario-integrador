# Hyperframes Composition Brief: Atalaya

## Objective
Video corto de lanzamiento/demo de Atalaya (Centro de Monitoreo Urbano, Villa María) mostrando lo ya implementado.

## Output
- Composition directory: `brag-output/composition/`
- Rendered video: `brag-output/brag.mp4`
- Format: landscape — 1920x1080, 30fps
- Duration: 24.5s

## Source Material
- Project root: repo `seminario-integrador`
- Primary files read: tailwind.config.js, docs/diseno-atalaya.md, resources/js/Pages/Dashboard.jsx, Layouts/AuthenticatedLayout.jsx, Pages/Eventos/Create.jsx, Pages/Turnos/Index.jsx, Pages/Roles/Index.jsx, database/seeders/*
- Product name: ATALAYA · SOC v1.0
- Key UI to recreate: panel táctico (telemetría + mapa + feed), formulario Registrar evento, turno abierto, roles
- Verbatim copy: "Centro de Monitoreo Urbano · Villa María", "+ Situación geoespacial", "+ Feed de eventos registrados", "Registrar evento", "El evento no se puede editar una vez registrado."

## Creative Direction
- Tone preset: polished — consola de sala de control 24/7
- Hook: tres registros tachados → "Un solo registro."
- Outro: "Registrar. Clasificar. Localizar. Consultar."
- Avoid: lenguaje SaaS genérico, claims de features no implementadas (WhatsApp, PDF/A, xlsx, estadísticas)

## Visual Identity
- Background #031427, panel #0B1C30, border #1E3A5F, accent #00D2FF, semáforo #F97316/#F59E0B/#EF4444, text #FFFFFF/#94A3B8
- Fonts: JetBrains Mono + Inter (woff2 locales en assets/fonts)
- Assets: assets/img/logo.png (logo real), assets/img/map-dark.jpg (tiles OSM de Villa María, filtro táctico horneado)

## Storyboard
Ver brag-plan.md (6 escenas, 24.5s).

## Audio
- Music: assets/music/bed.mp3 (vol-12 recortado a 25s), vol 0.32, fades in/out
- Cue guidance: ~/.claude/skills/brag/assets/music/cues/happy-beats-business-moves-vol-12-by-ende-dot-app.music-cues.json — locks 8.74 / 13.11 / 22.93
- Audio-reactive: subtle, RMS → glow del logo y del marco del mapa (assets/audio-data.js)
- SFX: assets/sfx/* (drop, error, card-slide, card-place, mouseclick, select, impactSoft, impactBell), vol 0.5–0.75
