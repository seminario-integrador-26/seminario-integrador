<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web (Inertia) — frontend interno, autenticado
|--------------------------------------------------------------------------
| Operador y Administrador. Se sirven con Inertia (sin API intermedia).
| Protegidas con middleware auth + roles de spatie/laravel-permission.
|
| NO agregar rutas de edición directa de eventos (inmutabilidad).
*/

// TODO: Route::middleware(['auth'])->group(function () {
//     Route::resource('eventos', EventoController::class)->except(['edit', 'update', 'destroy']);
//     Route::get('eventos/{evento}/errata', [ErrataController::class, 'create']);
//     Route::post('eventos/{evento}/errata', [ErrataController::class, 'store']);
//     Route::get('dashboard', [DashboardController::class, 'index']);       // rol:Administrador
//     Route::get('reportes', [ReporteController::class, 'index']);          // rol:Administrador
//     Route::post('reportes/exportar', [ReporteController::class, 'exportar']);
// });
