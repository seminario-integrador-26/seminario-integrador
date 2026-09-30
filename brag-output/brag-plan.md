# Brag Plan: Atalaya — Centro de Monitoreo Urbano (Villa María)

## What is this app?
Plataforma web (Laravel + Inertia/React) donde el Centro de Monitoreo de Villa María registra, clasifica, localiza y consulta los eventos que detecta su red de videovigilancia, reemplazando el libro de guardia a mano, el sistema de conteo y los mensajes de WhatsApp por un único registro.

## The angle
"Tres registros → uno." El video abre con el caos actual (libro a mano, conteo, WhatsApp), los tacha, y muestra la consola táctica Atalaya haciendo su trabajo: mapa de 394 puntos de monitoreo, eventos clasificados por semáforo y un evento registrado en vivo que queda inmutable y atado al turno de guardia.

## Hook (0–4.9s)
Tres líneas en mono: "Libro de guardia a mano." / "Sistema de conteo." / "WhatsApp." — se tachan en carmesí y aparece "Un solo registro." en blanco grande.

## Key moments
- El mapa oscuro de Villa María con puntos de monitoreo cian y marcadores de evento en naranja/ámbar/carmesí que aparecen sobre el beat.
- Feed de eventos: tarjetas AT-2026-0137 / 0136 / 0135 deslizándose con badge de categoría.
- Formulario "Registrar evento": se elige "Accidente de tránsito", PM-M-014, hora; el cursor hace clic en REGISTRAR EVENTO → confirmación: inmutable, Turno #42.
- Turnos + roles: tarjeta "Turno #42 abierto" y las 3 columnas de roles (Supervisor / Administrativo / Administrador de sistema) con sus permisos.

## Outro / punchline
Logo Atalaya + "Registrar. Clasificar. Localizar. Consultar." + "UTN FRVM · Seminario Integrador".

## User flow worth showing
Abrir turno → ver situación en el panel (mapa + feed) → registrar evento → queda inmutable en el turno.

## Tone
- Preset: polished
- Creative direction: consola de sala de control 24/7 — sobria, técnica, HUD táctico.
- Interpretation: pocas escenas, holds legibles, transiciones limpias; mono en mayúsculas con tracking amplio; cero chistes.

## Format: landscape — 1920x1080
## Duration: 24.5s (una escena extra sobre el patrón polished porque el pedido es mostrar funcionalidades)

## Visual identity (from the project — tailwind.config.js / docs/diseno-atalaya.md)
- Background: #031427 (canvas), paneles #0B1C30, bordes #1E3A5F
- Accent: #00D2FF (cian telemetría); semáforo #F97316 Prevención / #F59E0B Convivencia / #EF4444 Seguridad Pública
- Text: #FFFFFF / muted #94A3B8
- Display font: JetBrains Mono (800) · Body: Inter
- Strongest visual element: el panel táctico (mapa oscuro OSM + feed con badges)

## Share copy (draft)
Atalaya: el libro de guardia del Centro de Monitoreo de Villa María, digital, georreferenciado e inmutable. Laravel + Inertia/React + Leaflet.

## Audio direction
- Role: warm bed, sobria
- Music: happy-beats-business-moves-vol-12 (steady, clean), vol ~0.32, fade-in 0.4s y fade-out en el último 1.2s
- Music cue guidance: preset `assets/music/cues/…vol-12….music-cues.json` (110 BPM). Strong cues: 8.74 (marcadores del mapa), 13.11 (entra Registrar), 22.93 (logo final). Beat grid para feed: 9.29 / 10.37 / 11.46 (uno sí, uno no — texto legible).
- Audio-reactive treatment: subtle; RMS hace respirar el glow cian del logo y del borde del mapa. Nada de ecualizadores.
- SFX posture: sparse; drop suave para las líneas del hook, un error seco sobre el tachado, card-slide en el feed, click de mouse en Registrar, bell al final.
- Restraint rule: nunca más de un SFX por medio segundo; nada agresivo.

## Storyboard

### Scene 1 — Hook: tres registros — 4.9s
Tres líneas mono aparecen rápido (0.3/0.9/1.5s) y quedan; tachado carmesí a 2.2s; "Un solo registro." slam a 2.73s, hold hasta 4.8s.
Sequential: sí, 3 líneas. Audio: drop suave por línea, error seco en el tachado. Transición: crossfade limpia.

### Scene 2 — Reveal: Atalaya — 2.7s (4.9–7.6)
Logo hexagonal escala in (beat 4.91), "ATALAYA" + "SOC v1.0" + "Centro de Monitoreo Urbano · Villa María". Glow cian con RMS.
Audio: impactSoft. Transición: zoom hacia el panel.

### Scene 3 — Panel táctico — 5.5s (7.6–13.1)
Recreación del Dashboard: barra de telemetría (9 eventos · 394 PMs · Turno #42), mapa oscuro con PMs cian, marcadores de evento aparecen en 8.74 (beat-locked). Feed: 3 tarjetas en 9.29 / 10.37 / 11.46. Kicker: "Situación geoespacial en vivo".
Audio: card-slide por tarjeta.

### Scene 4 — Registrar evento — 4.9s (13.1–18.0)
Formulario real: Tipo → "Accidente de tránsito" (Seguridad Pública), PM-M-014, hora 12:08:41; cursor → clic en REGISTRAR EVENTO (15.84); banner verde "Evento AT-2026-0138 registrado · Turno #42" + "Inmutable: se corrige con fe de errata".
Audio: select ticks, mouseclick, drop en confirmación.

### Scene 5 — Turnos y roles — 4.3s (18.0–22.3)
Izquierda: tarjeta "Turno #42 abierto" con personal presente. Derecha: 3 tarjetas de rol aparecen en secuencia con sus permisos. Kicker: "Turnos de guardia · 3 roles · auditoría de accesos".
Audio: card-place en la primera y última.

### Scene 6 — Outro — 2.2s+ (22.3–24.5)
Logo + "Registrar. Clasificar. Localizar. Consultar." lock en 22.93. "UTN FRVM · Seminario Integrador".
Audio: impactBell suave, música se desvanece.

**Music mood:** steady, clean.
**Audio summary:** bed sobrio que acompaña, tres acentos grandes (mapa, registrar, logo).

## Nota sobre datos
Nombres de personas, PMs y hechos son ficticios. No se muestran credenciales ni datos reales. La notificación por WhatsApp NO se muestra: está stubbeada en el código (TODO), no implementada.
