<?php

use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErrataController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\GrupoInteresadoController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ParametroController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PuntoMonitoreoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TokenApiController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
    Route::get('usuarios/auditoria/exportar', [AuditoriaController::class, 'exportar'])->name('usuarios.auditoria.exportar');

    Route::resource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['show']);

    Route::get('roles', [RolController::class, 'index'])->name('roles.index');
    Route::post('roles/permisos', [RolController::class, 'storePermiso'])->name('roles.permisos.store');
    Route::put('roles/{role}/permisos', [RolController::class, 'actualizarPermisos'])->name('roles.permisos.update');

    // Tokens de la API pública de Datos Abiertos (también: php artisan api:token).
    Route::get('tokens-api', [TokenApiController::class, 'index'])->name('tokens.index');
    Route::post('tokens-api', [TokenApiController::class, 'store'])->name('tokens.store');
    Route::delete('tokens-api/{token}', [TokenApiController::class, 'destroy'])->whereNumber('token')->name('tokens.destroy');
});

/*
| Puntos de monitoreo (US-024) — permiso puntos.gestionar (Administrador de sistema).
*/
Route::middleware(['auth', 'permission:puntos.gestionar'])->group(function () {
    Route::get('puntos/plantilla', [PuntoMonitoreoController::class, 'plantilla'])->name('puntos.plantilla');
    Route::post('puntos/importar', [PuntoMonitoreoController::class, 'importar'])->name('puntos.importar');
    Route::resource('puntos', PuntoMonitoreoController::class)
        ->parameters(['puntos' => 'punto'])
        ->except(['show']);
});

/*
| Grupos de WhatsApp y correspondencias con los tipos de evento (US-028) —
| permiso notificaciones.configurar (Administrador de sistema).
*/
Route::middleware(['auth', 'permission:notificaciones.configurar'])->group(function () {
    Route::put('grupos/correspondencias', [GrupoInteresadoController::class, 'correspondencias'])
        ->name('grupos.correspondencias');
    Route::resource('grupos', GrupoInteresadoController::class)
        ->parameters(['grupos' => 'grupo'])
        ->except(['show']);
});

/*
| Parámetros del sistema (US-029) — permiso parametros.configurar
| (Administrador de sistema).
*/
Route::middleware(['auth', 'permission:parametros.configurar'])->group(function () {
    Route::get('parametros', [ParametroController::class, 'index'])->name('parametros.index');
    Route::put('parametros', [ParametroController::class, 'update'])->name('parametros.update');
});

/*
| Registro de eventos (US-003) — solo rol Supervisor.
| NO agregar edición directa de eventos (inmutabilidad -> usar Errata).
*/
Route::middleware(['auth', 'role:Supervisor'])->group(function () {
    Route::resource('eventos', EventoController::class)->only(['create', 'store']);

    // Regla de negocio 1: corrección de eventos por fe de errata.
    Route::get('eventos/{evento}/errata', [ErrataController::class, 'create'])
        ->whereNumber('evento')->name('erratas.create');
    Route::post('eventos/{evento}/errata', [ErrataController::class, 'store'])
        ->whereNumber('evento')->name('erratas.store');

    // US-007: reenvío manual de una notificación fallida.
    Route::post('notificaciones/{notificacion}/reenviar', [NotificacionController::class, 'reenviar'])
        ->name('notificaciones.reenviar');
});

/*
| Consulta de eventos (CU04) — permiso eventos.consultar
| (Supervisor, Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:eventos.consultar'])->group(function () {
    Route::get('eventos', [EventoController::class, 'index'])->name('eventos.index');
    // US-010: detalle de un evento. whereNumber: no pisa 'eventos/create'.
    Route::get('eventos/{evento}', [EventoController::class, 'show'])
        ->whereNumber('evento')
        ->name('eventos.show');
});

/*
| Estadísticas mensuales y anuales (US-014) — permiso estadisticas.ver
| (Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:estadisticas.ver'])->group(function () {
    Route::get('estadisticas', [EstadisticaController::class, 'index'])->name('estadisticas.index');
});

/*
| Mapa de eventos y mapa de calor (US-016, US-017) — permiso dashboards.ver.
*/
Route::middleware(['auth', 'permission:dashboards.ver'])->group(function () {
    Route::get('mapa', [MapaController::class, 'index'])->name('mapa.index');
    // US-015, US-018 a US-022: dashboards de análisis.
    Route::get('analisis', [AnalisisController::class, 'index'])->name('analisis.index');
});

/*
| Libro de guardia en PDF (US-012) y exportación de eventos (US-013) —
| permiso reportes.exportar (Administrativo y Administrador de sistema).
*/
Route::middleware(['auth', 'permission:reportes.exportar'])->group(function () {
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    // {apertura}: evento Apertura de turno; el PDF va hasta el Cierre siguiente.
    Route::get('reportes/guardias/{apertura}/pdf', [ReporteController::class, 'guardiaPdf'])
        ->whereNumber('apertura')->name('reportes.guardia.pdf');
    Route::get('reportes/eventos.{formato}', [ReporteController::class, 'eventosPlanilla'])
        ->whereIn('formato', ['xlsx', 'xls'])
        ->name('reportes.eventos.planilla');
});

require __DIR__.'/auth.php';
