<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas API pública — Datos Abiertos, SOLO LECTURA, versionada
|--------------------------------------------------------------------------
| Consumo externo (Gobierno Abierto). Distinta del frontend Inertia.
| Auth: Laravel Sanctum (tokens) o rate limiting si se deja abierta.
| // TODO: confirmar con el equipo el modo de acceso definitivo.
*/

// TODO: Route::prefix('v1')->group(function () {
//     Route::get('eventos', [EventoApiController::class, 'index']);
//     Route::get('eventos/{evento}', [EventoApiController::class, 'show']);
//     Route::get('estadisticas/mensual/{anio}/{mes}', [EstadisticaApiController::class, 'mensual']);
//     Route::get('estadisticas/anual/{anio}', [EstadisticaApiController::class, 'anual']);
// });
