<?php

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
        'canRegister' => Route::has('register'),
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
| Administración de usuarios y roles — solo rol Administrativo.
*/
Route::middleware(['auth', 'role:Administrativo'])->group(function () {
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
| Consulta de eventos (CU04) — permiso eventos.consultar (Supervisor, Administrativo).
| TODO: confirmar con el equipo si el Operador también consulta el listado.
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
//     Route::get('dashboard', [DashboardController::class, 'index']);       // rol:Administrativo|Operador (lectura)
//     Route::get('reportes', [ReporteController::class, 'index']);          // rol:Administrativo
//     Route::post('reportes/exportar', [ReporteController::class, 'exportar']);
// });

require __DIR__.'/auth.php';
