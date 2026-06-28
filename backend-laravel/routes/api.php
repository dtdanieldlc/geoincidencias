<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidenciasController;
use App\Http\Controllers\Api\ApoyosController;
use App\Http\Controllers\Api\CatalogosController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HistorialController;
use App\Http\Controllers\Api\NotificacionesController;
use App\Http\Controllers\Api\ReportesController;

Route::get('/health', fn () => response()->json(['ok' => true, 'mensaje' => 'Backend funcionando correctamente.']));

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/registro', [AuthController::class, 'registro']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/perfil', [AuthController::class, 'perfil']);
        Route::put('/perfil', [AuthController::class, 'actualizarPerfil']);
        Route::put('/cambiar-password', [AuthController::class, 'cambiarPassword']);
        Route::post('/foto', [AuthController::class, 'subirFoto']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // Incidencias — rutas específicas SIEMPRE antes del apiResource,
    // porque apiResource registra GET incidencias/{incidencia} y
    // si va primero, "mapa", "exportar", etc. se interpretan como {id}.
    Route::get('incidencias/mapa', [IncidenciasController::class, 'mapa']);
    Route::get('incidencias/pendientes-aprobacion', [IncidenciasController::class, 'pendientesAprobacion']);
    Route::get('incidencias/exportar/csv', [IncidenciasController::class, 'exportarCsv']);
    Route::put('incidencias/{id}/aprobar', [IncidenciasController::class, 'aprobar']);
    Route::put('incidencias/{id}/rechazar', [IncidenciasController::class, 'rechazar']);
    Route::get('incidencias/mis-reportes', [IncidenciasController::class, 'misReportes']);
    Route::apiResource('incidencias', IncidenciasController::class);

    // Apoyos
    Route::post('apoyos', [ApoyosController::class, 'store']);
    Route::get('apoyos/mis-apoyos', [ApoyosController::class, 'misApoyos']);
    Route::get('apoyos/mi-saldo', [ApoyosController::class, 'miSaldo']);
    Route::get('apoyos/pendientes', [ApoyosController::class, 'pendientes'])->middleware('solo.admin');
    Route::get('apoyos', [ApoyosController::class, 'index'])->middleware('solo.admin');
    Route::put('apoyos/{id}/aprobar', [ApoyosController::class, 'aprobar'])->middleware('solo.admin');
    Route::put('apoyos/{id}/rechazar', [ApoyosController::class, 'rechazar'])->middleware('solo.admin');

    // Catálogos
    Route::get('catalogos/tipos', [CatalogosController::class, 'tipos']);
    Route::get('catalogos/subtipos/{id_tipo}', [CatalogosController::class, 'subtipos']);
    Route::get('catalogos/estados', [CatalogosController::class, 'estados']);
    Route::get('catalogos/zonas', [CatalogosController::class, 'zonas']);
    Route::get('catalogos/usuarios', [CatalogosController::class, 'usuarios']);
    Route::get('catalogos/incentivos', [CatalogosController::class, 'incentivos']);

    // Dashboard
    Route::get('dashboard/resumen', [DashboardController::class, 'resumen']);
    Route::get('dashboard/por-tipo', [DashboardController::class, 'porTipo']);
    Route::get('dashboard/por-estado', [DashboardController::class, 'porEstado']);
    Route::get('dashboard/por-zona', [DashboardController::class, 'porZona']);
    Route::get('dashboard/ultimas', [DashboardController::class, 'ultimas']);

    // Historial y Notificaciones
    Route::get('historial', [HistorialController::class, 'index']);
    Route::get('historial/acciones', [HistorialController::class, 'acciones']);
    Route::get('notificaciones', [NotificacionesController::class, 'index']);
    Route::get('notificaciones/no-leidas', [NotificacionesController::class, 'noLeidas']);
    Route::put('notificaciones/{id}/leida', [NotificacionesController::class, 'marcarLeida']);
    Route::put('notificaciones/marcar-todas', [NotificacionesController::class, 'marcarTodasLeidas']);

    // Reportes
    Route::get('reportes/resumen', [ReportesController::class, 'resumen']);
    Route::get('reportes/por-categoria', [ReportesController::class, 'porCategoria']);
    Route::get('reportes/por-estado', [ReportesController::class, 'porEstado']);
    Route::get('reportes/tendencia', [ReportesController::class, 'tendencia']);
    Route::get('reportes/por-responsable', [ReportesController::class, 'porResponsable']);
});

Route::middleware('solo.admin')->group(function () {
    Route::delete('/incidencias/{id}', [IncidenciasController::class, 'destroy']);
    Route::get('/apoyos', [ApoyosController::class, 'index']);
    Route::get('/apoyos/pendientes', [ApoyosController::class, 'pendientes']);
    Route::put('/apoyos/{id}/aprobar', [ApoyosController::class, 'aprobar']);
    Route::put('/apoyos/{id}/rechazar', [ApoyosController::class, 'rechazar']);
    Route::get('/historial', [HistorialController::class, 'index']);
    Route::get('/historial/acciones', [HistorialController::class, 'acciones']);
});