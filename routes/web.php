<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
| Administración de usuarios y roles — permiso usuarios.gestionar (solo Administrador de sistema).
*/
Route::middleware(['auth', 'permission:usuarios.gestionar'])->group(function () {
    // Antes del resource: evita que 'auditoria' se resuelva como {usuario}.
    Route::get('usuarios/auditoria', [AuditoriaController::class, 'index'])->name('usuarios.auditoria');

    Route::resource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['show']);

    Route::get('roles', [RolController::class, 'index'])->name('roles.index');
});

/*
| Registro de eventos (US-003) — solo rol Supervisor.
| NO agregar edición directa de eventos (inmutabilidad -> usar Errata).
*/
Route::middleware(['auth', 'role:Supervisor'])->group(function () {
    Route::resource('eventos', EventoController::class)->only(['create', 'store']);
});

/*
| Consulta de eventos (CU04) — permiso eventos.consultar
| (Supervisor, Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:eventos.consultar'])->group(function () {
    Route::get('eventos', [EventoController::class, 'index'])->name('eventos.index');
});

/*
|--------------------------------------------------------------------------
| Rutas de dominio (Inertia) — pendientes de implementar
|--------------------------------------------------------------------------
| Protegidas con auth + roles de spatie/laravel-permission.
*/
// Route::middleware(['auth'])->group(function () {
//     Route::get('eventos/{evento}/errata', [ErrataController::class, 'create']);
//     Route::post('eventos/{evento}/errata', [ErrataController::class, 'store']);
//     Route::get('dashboard', [DashboardController::class, 'index']);       // permission:dashboards.ver (Administrativo|Administrador de sistema)
//     Route::get('reportes', [ReporteController::class, 'index']);          // permission:reportes.exportar (Administrativo|Administrador de sistema)
//     Route::post('reportes/exportar', [ReporteController::class, 'exportar']);
// });

require __DIR__.'/auth.php';
