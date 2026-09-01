<?php

use App\Http\Controllers\ProfileController;
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
|--------------------------------------------------------------------------
| Rutas de dominio (Inertia) — pendientes de implementar
|--------------------------------------------------------------------------
| Protegidas con auth + roles de spatie/laravel-permission.
| NO agregar edición directa de eventos (inmutabilidad -> usar Errata).
*/
// Route::middleware(['auth'])->group(function () {
//     Route::resource('eventos', EventoController::class)->except(['edit', 'update', 'destroy']);
//     Route::get('eventos/{evento}/errata', [ErrataController::class, 'create']);
//     Route::post('eventos/{evento}/errata', [ErrataController::class, 'store']);
//     Route::get('dashboard', [DashboardController::class, 'index']);       // rol:Administrador
//     Route::get('reportes', [ReporteController::class, 'index']);          // rol:Administrador
//     Route::post('reportes/exportar', [ReporteController::class, 'exportar']);
// });

require __DIR__.'/auth.php';
