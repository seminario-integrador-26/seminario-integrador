<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

/**
 * API PÚBLICA versionada (/api/v1), SOLO LECTURA, para Datos Abiertos.
 * Convive con Inertia (que resuelve el frontend interno); esto es ADEMÁS.
 *
 * Auth: Laravel Sanctum (personal access tokens) o rate limiting si se deja
 * abierta. // TODO: confirmar con el equipo el modo de acceso definitivo.
 */
class EventoApiController extends Controller
{
    // TODO: index(Request $request)  -> listado paginado de eventos (JSON)
    // TODO: show(Evento $evento)      -> detalle de un evento (JSON)
    // Sólo GET. Nada de store/update/destroy acá.
}
