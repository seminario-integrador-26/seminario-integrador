# Relevamiento del dominio

> Contexto de negocio. Completar con lo relevado en la Municipalidad.

## Situación actual (problema)

El mismo evento se registra **3 veces** y sin generar reportes útiles:

1. Libro de guardia, a mano.
2. Sistema de conteo estadístico.
3. WhatsApp.

No hay un registro único ni información para tomar decisiones.

## Objetivo del sistema

Centralizar en **un único registro**: registro, clasificación, localización,
notificación y consulta de eventos detectados por el Centro de Monitoreo
(Sistema de Videovigilancia, Municipalidad de Villa María).

## Actores

- **Supervisor** — registra eventos, genera fe de errata y gestiona los turnos
  de guardia (abre/cierra).
- **Administrativo** — consulta, gestiona, genera estadísticas y reportes,
  exporta y administra usuarios y tipos de evento.
- **Operador** — monitorea en vivo; en el sistema tiene acceso de **solo
  lectura** (dashboards). No carga eventos.
- **Sistemas externos** (Gobierno Abierto) — consumen la API pública.

## Clasificación de eventos

Los eventos se relacionan con **prevención**, **convivencia urbana** o
**seguridad pública**; además existen eventos **informativos** (no
cuantificables). El sistema **no** deriva ni hace seguimiento posterior del
evento (fuera de alcance).

<!-- TODO: detalles del proceso actual, áreas destino, grupos de WhatsApp reales -->
