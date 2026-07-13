<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidenciasController;
use App\Http\Controllers\Api\ApoyosController;
use App\Http\Controllers\Api\CatalogosController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HistorialController;
use App\Http\Controllers\Api\NotificacionesController;
use App\Http\Controllers\Api\ReportesController;
use App\Http\Controllers\Api\AdminUsuariosController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\PermisosController;

Route::get('/health', fn () => response()->json(['ok' => true, 'mensaje' => 'Backend funcionando correctamente.']));

// ── Autenticación (pública) ───────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/registro', [AuthController::class, 'registro'])->middleware('throttle:6,1');
    Route::post('/google',   [AuthController::class, 'google'])->middleware('throttle:6,1');

    // Recuperación de contraseña (cédula + pregunta secreta)
    Route::prefix('recuperar')->middleware('throttle:6,1')->group(function () {
        Route::post('/pregunta',  [AuthController::class, 'recuperarPregunta']);
        Route::post('/verificar', [AuthController::class, 'recuperarVerificar']);
        Route::post('/reset',     [AuthController::class, 'recuperarReset']);
    });

    // Rutas protegidas por token
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/perfil',           [AuthController::class, 'perfil']);
        Route::put('/perfil',           [AuthController::class, 'actualizarPerfil']);
        Route::put('/cambiar-password', [AuthController::class, 'cambiarPassword']);
        Route::post('/foto',            [AuthController::class, 'subirFoto']);
        Route::post('/logout',          [AuthController::class, 'logout']);
        Route::put('/desactivar',       [AuthController::class, 'desactivarCuenta']);
        Route::delete('/eliminar',      [AuthController::class, 'eliminarCuenta']);
    });
});

// ── Rutas autenticadas ───────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Incidencias ──────────────────────────────────────────────
    Route::get('incidencias/mapa',                    [IncidenciasController::class, 'mapa']);
    Route::get('incidencias/facetas',                 [IncidenciasController::class, 'facetas']);
    Route::get('incidencias/reportantes',              [IncidenciasController::class, 'reportantes']);
    Route::get('incidencias/posibles-duplicados',      [IncidenciasController::class, 'posiblesDuplicados']);
    Route::get('incidencias/pendientes-aprobacion',   [IncidenciasController::class, 'pendientesAprobacion'])->middleware(['solo.admin', 'permiso:incidencias,ver']);
    Route::get('incidencias/exportar/csv',            [IncidenciasController::class, 'exportarCsv'])->middleware(['solo.admin', 'permiso:incidencias,ver']);
    Route::put('incidencias/{id}/aprobar',            [IncidenciasController::class, 'aprobar'])->middleware(['solo.admin', 'permiso:incidencias,editar']);
    Route::put('incidencias/{id}/rechazar',           [IncidenciasController::class, 'rechazar'])->middleware(['solo.admin', 'permiso:incidencias,editar']);
    Route::put('incidencias/aprobar-lote',            [IncidenciasController::class, 'aprobarLote'])->middleware(['solo.admin', 'permiso:incidencias,editar']);
    Route::put('incidencias/rechazar-lote',           [IncidenciasController::class, 'rechazarLote'])->middleware(['solo.admin', 'permiso:incidencias,editar']);
    Route::get('incidencias/mis-reportes',            [IncidenciasController::class, 'misReportes']);
    Route::get('incidencias/mis-reportes/pdf',        [IncidenciasController::class, 'misReportesPdf']);
    Route::get('incidencias/{id}/comentarios',        [IncidenciasController::class, 'comentarios']);
    Route::post('incidencias/{id}/comentarios',       [IncidenciasController::class, 'agregarComentario']);
    Route::get('incidencias/{id}/fotos',              [IncidenciasController::class, 'fotos']);
    Route::post('incidencias/{id}/fotos',             [IncidenciasController::class, 'agregarFoto']);
    Route::delete('incidencias/{id}/fotos/{idFoto}',  [IncidenciasController::class, 'eliminarFoto']);
    Route::get('incidencias/{id}/ficha-pdf',           [IncidenciasController::class, 'fichaPdf'])->middleware('solo.admin');
    Route::apiResource('incidencias', IncidenciasController::class)->except(['destroy', 'update']);
    Route::match(['put', 'patch'], 'incidencias/{id}', [IncidenciasController::class, 'update'])
        ->middleware(['solo.admin', 'permiso:incidencias,editar']);

    // ── Apoyos ───────────────────────────────────────────────────
    Route::post('apoyos',              [ApoyosController::class, 'store']);
    Route::get('apoyos/mis-apoyos',   [ApoyosController::class, 'misApoyos']);
    Route::get('apoyos/mi-saldo',     [ApoyosController::class, 'miSaldo']);
    Route::get('apoyos/pendientes',   [ApoyosController::class, 'pendientes'])->middleware(['solo.admin', 'permiso:incentivos,ver']);
    Route::get('apoyos',              [ApoyosController::class, 'index'])->middleware(['solo.admin', 'permiso:incentivos,ver']);
    Route::put('apoyos/{id}/aprobar', [ApoyosController::class, 'aprobar'])->middleware(['solo.admin', 'permiso:incentivos,editar']);
    Route::put('apoyos/{id}/rechazar',[ApoyosController::class, 'rechazar'])->middleware(['solo.admin', 'permiso:incentivos,editar']);

    // ── Catálogos ────────────────────────────────────────────────
    Route::get('catalogos/tipos',             [CatalogosController::class, 'tipos']);
    Route::get('catalogos/subtipos/{id_tipo}', [CatalogosController::class, 'subtipos']);
    Route::get('catalogos/estados',           [CatalogosController::class, 'estados']);
    Route::get('catalogos/zonas',             [CatalogosController::class, 'zonas']);
    Route::get('catalogos/sucursales',        [CatalogosController::class, 'sucursales']);
    Route::get('catalogos/usuarios',          [CatalogosController::class, 'usuarios']);
    Route::get('catalogos/incentivos',        [CatalogosController::class, 'incentivos']);

    // ── Dashboard ────────────────────────────────────────────────
    Route::get('dashboard/resumen',    [DashboardController::class, 'resumen']);
    Route::get('dashboard/por-tipo',   [DashboardController::class, 'porTipo']);
    Route::get('dashboard/por-estado', [DashboardController::class, 'porEstado']);
    Route::get('dashboard/por-zona',   [DashboardController::class, 'porZona']);
    Route::get('dashboard/por-sucursal', [DashboardController::class, 'porSucursal']);
    Route::get('dashboard/ultimas',    [DashboardController::class, 'ultimas']);
    Route::get('dashboard/vencidas',   [DashboardController::class, 'vencidas']);

    // ── Historial & Notificaciones ───────────────────────────────
    Route::get('historial',                   [HistorialController::class, 'index'])->middleware(['solo.admin', 'permiso:historial,ver']);
    Route::get('historial/acciones',          [HistorialController::class, 'acciones'])->middleware(['solo.admin', 'permiso:historial,ver']);
    Route::get('notificaciones',              [NotificacionesController::class, 'index']);
    Route::get('notificaciones/no-leidas',    [NotificacionesController::class, 'noLeidas']);
    Route::put('notificaciones/{id}/leida',   [NotificacionesController::class, 'marcarLeida']);
    Route::put('notificaciones/marcar-todas', [NotificacionesController::class, 'marcarTodasLeidas']);

    // ── Reportes ─────────────────────────────────────────────────
    Route::get('reportes/resumen',         [ReportesController::class, 'resumen']);
    Route::get('reportes/por-categoria',   [ReportesController::class, 'porCategoria']);
    Route::get('reportes/por-estado',      [ReportesController::class, 'porEstado']);
    Route::get('reportes/tendencia',       [ReportesController::class, 'tendencia']);
    Route::get('reportes/por-responsable', [ReportesController::class, 'porResponsable']);
    Route::get('reportes/por-sucursal',    [ReportesController::class, 'porSucursal']);
    Route::get('reportes/exportar-pdf-resumen', [ReportesController::class, 'exportarPdfResumen']);
    Route::get('reportes/exportar-pdf-detalle', [ReportesController::class, 'exportarPdfDetalle']);

    // ── Mis permisos (cualquier usuario autenticado) ──────────────
    Route::get('mis-permisos', [PermisosController::class, 'misPermisos']);

    // ── Admin: solicitar permisos ─────────────────────────────────
    Route::middleware('solo.admin')->prefix('admin')->group(function () {
        Route::get('usuarios',              [AdminUsuariosController::class, 'index'])->middleware('permiso:usuarios,ver');
        Route::get('usuarios/estadisticas', [AdminUsuariosController::class, 'estadisticas'])->middleware('permiso:usuarios,ver');
        Route::get('usuarios/{id}',         [AdminUsuariosController::class, 'show'])->middleware('permiso:usuarios,ver');
        Route::put('usuarios/{id}/activo',  [AdminUsuariosController::class, 'toggleActivo'])->middleware('permiso:usuarios,editar');
        Route::get('usuarios/{id}/reporte-pdf', [IncidenciasController::class, 'reportePdfUsuario'])->middleware('permiso:usuarios,ver');
        Route::put('usuarios/{id}/rol',     [AdminUsuariosController::class, 'cambiarRol']);
        Route::put('usuarios/{id}/presencia', [AdminUsuariosController::class, 'actualizarPresencia']);

        Route::delete('incidencias/{id}',  [IncidenciasController::class, 'destroy'])->middleware('permiso:incidencias,eliminar');
        Route::get('apoyos',               [ApoyosController::class, 'index'])->middleware('permiso:incentivos,ver');
        Route::get('apoyos/pendientes',    [ApoyosController::class, 'pendientes'])->middleware('permiso:incentivos,ver');
        Route::put('apoyos/{id}/aprobar',  [ApoyosController::class, 'aprobar'])->middleware('permiso:incentivos,editar');
        Route::put('apoyos/{id}/rechazar', [ApoyosController::class, 'rechazar'])->middleware('permiso:incentivos,editar');
        Route::get('historial',            [HistorialController::class, 'index'])->middleware('permiso:historial,ver');
        Route::get('historial/acciones',   [HistorialController::class, 'acciones'])->middleware('permiso:historial,ver');

        // Solicitudes de permisos (admin solicita al superadmin)
        Route::get('solicitudes-permisos',  [PermisosController::class, 'misSolicitudes']);
        Route::post('solicitudes-permisos', [PermisosController::class, 'solicitarPermisos']);
    });

    // ── SuperAdmin exclusivo ──────────────────────────────────────
    Route::middleware('solo.superadmin')->prefix('superadmin')->group(function () {
        Route::get('usuarios',                      [SuperAdminController::class, 'usuarios']);
        Route::post('usuarios',                     [SuperAdminController::class, 'crear']);
        Route::get('usuarios/{id}/credenciales',    [SuperAdminController::class, 'credenciales']);
        Route::put('usuarios/{id}/datos-completos', [SuperAdminController::class, 'actualizarDatosCompletos']);
        Route::put('usuarios/{id}/rol',             [SuperAdminController::class, 'cambiarRol']);
        Route::put('usuarios/{id}/password',        [SuperAdminController::class, 'resetPassword']);
        Route::delete('usuarios/{id}',              [SuperAdminController::class, 'eliminar']);
        Route::get('logs',                          [SuperAdminController::class, 'logs']);
        Route::get('conectados',                    [SuperAdminController::class, 'conectados']);

        // Permisos granulares — asignación directa
        Route::get('permisos/modulos',              [PermisosController::class, 'modulos']);
        Route::get('permisos/{id_usuario}',         [PermisosController::class, 'permisosDeUsuario']);
        Route::put('permisos/{id_usuario}',         [PermisosController::class, 'asignarPermisos']);

        // Solicitudes de permisos — revisión
        Route::get('solicitudes-permisos',                  [PermisosController::class, 'todasLasSolicitudes']);
        Route::get('solicitudes-permisos/{id}',             [PermisosController::class, 'detalleSolicitud']);
        Route::put('solicitudes-permisos/{id}/revisar',     [PermisosController::class, 'revisarSolicitud']);
    });
});
