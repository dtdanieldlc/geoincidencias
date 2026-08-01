<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ReporteUsuarioController;
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
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\SucursalResponsableController;
use App\Http\Controllers\Api\EvidenciasController;

Route::get('/health', fn () => response()->json(['ok' => true, 'mensaje' => 'Backend funcionando correctamente.']));

// Catálogo público de sucursales (necesario en el registro de cuenta)
Route::get('catalogos/sucursales-publicas', [\App\Http\Controllers\Api\CatalogosController::class, 'sucursales']);

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

    // ── Chat / Mensajería ────────────────────────────────────────
    // chat/usuarios se usa también en registrar.js (selector "Reportado
    // por"), fuera del chat en sí, así que queda accesible a cualquier
    // usuario autenticado y no entra en el grupo exclusivo de abajo.
    Route::get('chat/usuarios', [ChatController::class, 'usuarios']);

    // ── Chat / Mensajería — exclusivo Admin/Superadmin (HU-02) ──────
    Route::middleware('solo.staff')->group(function () {
        Route::get('chat/conversaciones',                 [ChatController::class, 'conversaciones']);
        Route::get('chat/conversaciones/{id}/mensajes',   [ChatController::class, 'mensajes']);
        Route::post('chat/mensajes',                      [ChatController::class, 'enviar']);
        Route::post('chat/mensajes/imagen',               [ChatController::class, 'enviarImagen']);
        Route::post('chat/escribiendo',                    [ChatController::class, 'escribiendo']);
        Route::post('chat/pusher-auth',                   [ChatController::class, 'pusherAuth']);

        Route::get('reportes/mis-resoluciones',     [ReportesController::class, 'misResoluciones']);
        Route::get('reportes/mis-resoluciones/pdf', [ReportesController::class, 'misResolucionesPdf']);
    });

    // ── Reportar / denunciar usuarios (cualquier rol) ───────────────
    Route::post('usuarios/{id}/reportar', [ReporteUsuarioController::class, 'reportar']);

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
    Route::post('incidencias/{id}/confirmar-resolucion', [IncidenciasController::class, 'confirmarResolucion']);
    Route::post('incidencias/{id}/reportar-novedad',     [IncidenciasController::class, 'reportarNovedad']);
    Route::get('incidencias/{id}/comentarios',        [IncidenciasController::class, 'comentarios']);
    Route::post('incidencias/{id}/comentarios',       [IncidenciasController::class, 'agregarComentario']);
    Route::get('incidencias/{id}/fotos',              [IncidenciasController::class, 'fotos']);
    Route::post('incidencias/{id}/fotos',             [IncidenciasController::class, 'agregarFoto']);
    Route::delete('incidencias/{id}/fotos/{idFoto}',  [IncidenciasController::class, 'eliminarFoto']);
    Route::get('incidencias/{id}/ficha-pdf',           [IncidenciasController::class, 'fichaPdf'])->middleware('solo.admin');
    Route::apiResource('incidencias', IncidenciasController::class)->except(['destroy', 'update']);
    Route::match(['put', 'patch'], 'incidencias/{id}', [IncidenciasController::class, 'update'])
        ->middleware(['solo.staff']);

    // ── Apoyos — MÓDULO ELIMINADO (HU-06): la empresa ya cuenta con
    // personal designado por tipo de incidencia; se conservan los
    // datos históricos en la base de datos, pero el acceso se desactiva.
    // Route::post('apoyos',              [ApoyosController::class, 'store']);
    // Route::get('apoyos/mis-apoyos',   [ApoyosController::class, 'misApoyos']);
    // Route::get('apoyos/mi-saldo',     [ApoyosController::class, 'miSaldo']);
    // Route::get('apoyos/pendientes',   [ApoyosController::class, 'pendientes'])->middleware(['solo.admin', 'permiso:incentivos,ver']);
    // Route::get('apoyos',              [ApoyosController::class, 'index'])->middleware(['solo.admin', 'permiso:incentivos,ver']);
    // Route::put('apoyos/{id}/aprobar', [ApoyosController::class, 'aprobar'])->middleware(['solo.admin', 'permiso:incentivos,editar']);
    // Route::put('apoyos/{id}/rechazar',[ApoyosController::class, 'rechazar'])->middleware(['solo.admin', 'permiso:incentivos,editar']);

    // ── Catálogos ────────────────────────────────────────────────
    Route::get('catalogos/tipos',             [CatalogosController::class, 'tipos']);
    Route::get('catalogos/subtipos/{id_tipo}', [CatalogosController::class, 'subtipos']);
    Route::get('catalogos/estados',           [CatalogosController::class, 'estados']);
    Route::get('catalogos/zonas',             [CatalogosController::class, 'zonas']);
    Route::get('catalogos/sucursales',        [CatalogosController::class, 'sucursales']);
    Route::get('catalogos/usuarios',          [CatalogosController::class, 'usuarios']);
    Route::get('catalogos/incentivos',        [CatalogosController::class, 'incentivos']);
    Route::get('catalogos/departamentos',     [DepartamentoController::class, 'index']);

    // ── Evidencias de incidencias (HU-11) ────────────────────────
    Route::get('incidencias/{id}/evidencias',              [EvidenciasController::class, 'index']);
    Route::post('incidencias/{id}/evidencias',             [EvidenciasController::class, 'store']);
    Route::delete('incidencias/{id}/evidencias/{idEvidencia}', [EvidenciasController::class, 'destroy']);

    // ── Dashboard ────────────────────────────────────────────────
    Route::get('dashboard/resumen',    [DashboardController::class, 'resumen'])->middleware('solo.admin');
    Route::get('dashboard/por-tipo',   [DashboardController::class, 'porTipo']);
    Route::get('dashboard/por-estado', [DashboardController::class, 'porEstado']);
    Route::get('dashboard/por-zona',   [DashboardController::class, 'porZona']);
    Route::get('dashboard/por-sucursal', [DashboardController::class, 'porSucursal']);
    Route::get('dashboard/ultimas',    [DashboardController::class, 'ultimas']);
    Route::get('dashboard/vencidas',   [DashboardController::class, 'vencidas']);

    // ── Historial & Notificaciones ───────────────────────────────
    Route::get('historial',                   [HistorialController::class, 'index'])->middleware(['solo.admin']);
    Route::get('historial/acciones',          [HistorialController::class, 'acciones'])->middleware(['solo.admin']);
    Route::get('notificaciones',              [NotificacionesController::class, 'index']);
    Route::get('notificaciones/no-leidas',    [NotificacionesController::class, 'noLeidas']);
    Route::put('notificaciones/{id}/leida',   [NotificacionesController::class, 'marcarLeida']);
    Route::put('notificaciones/marcar-todas', [NotificacionesController::class, 'marcarTodasLeidas']);

    // ── Reportes ─────────────────────────────────────────────────
    // ── Reportes — exclusivo Admin/Superadmin (HU-07) ───────────────
    Route::middleware('solo.admin')->group(function () {
        Route::get('reportes/resumen',         [ReportesController::class, 'resumen']);
        Route::get('reportes/por-categoria',   [ReportesController::class, 'porCategoria']);
        Route::get('reportes/por-estado',      [ReportesController::class, 'porEstado']);
        Route::get('reportes/tendencia',       [ReportesController::class, 'tendencia']);
        Route::get('reportes/por-responsable', [ReportesController::class, 'porResponsable']);
        Route::get('reportes/por-sucursal',    [ReportesController::class, 'porSucursal']);
        Route::get('reportes/exportar-pdf-resumen', [ReportesController::class, 'exportarPdfResumen']);
        Route::get('reportes/exportar-pdf-detalle', [ReportesController::class, 'exportarPdfDetalle']);
    });

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
        // Apoyos — MÓDULO ELIMINADO (HU-06), ver nota arriba.
        // Route::get('apoyos',               [ApoyosController::class, 'index'])->middleware('permiso:incentivos,ver');
        // Route::get('apoyos/pendientes',    [ApoyosController::class, 'pendientes'])->middleware('permiso:incentivos,ver');
        // Route::put('apoyos/{id}/aprobar',  [ApoyosController::class, 'aprobar'])->middleware('permiso:incentivos,editar');
        // Route::put('apoyos/{id}/rechazar', [ApoyosController::class, 'rechazar'])->middleware('permiso:incentivos,editar');
        Route::get('historial',            [HistorialController::class, 'index']);
        Route::get('historial/acciones',   [HistorialController::class, 'acciones']);

        // Solicitudes de permisos (admin solicita al superadmin)
        Route::get('solicitudes-permisos',  [PermisosController::class, 'misSolicitudes']);
        Route::post('solicitudes-permisos', [PermisosController::class, 'solicitarPermisos']);

        // Departamentos — lectura para admin; escritura solo superadmin
        Route::get('departamentos',              [DepartamentoController::class, 'index']);

        // Responsables por sucursal — solo lectura para admin (asignar es superadmin)
        Route::get('sucursales-responsables',              [SucursalResponsableController::class, 'index']);
        Route::get('sucursales-responsables/candidatos',   [SucursalResponsableController::class, 'candidatos']);
    });

    // ── SuperAdmin exclusivo ──────────────────────────────────────
    Route::middleware('solo.superadmin')->prefix('superadmin')->group(function () {
        Route::get('usuarios',                      [SuperAdminController::class, 'usuarios']);
        Route::post('usuarios',                     [SuperAdminController::class, 'crear']);
        Route::get('reportes-usuario',                    [ReporteUsuarioController::class, 'panelReportes']);
        Route::get('reportes-usuario/usuario/{id}',       [ReporteUsuarioController::class, 'detalleUsuario']);
        Route::put('reportes-usuario/{id}/estado',        [ReporteUsuarioController::class, 'cambiarEstado']);
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

        // Asignar/quitar encargados de sucursal — exclusivo Superadmin
        Route::post('sucursales-responsables',             [SucursalResponsableController::class, 'asignar']);
        Route::delete('sucursales-responsables/{id}',      [SucursalResponsableController::class, 'quitar']);

        // Departamentos — crear/editar/activar: exclusivo Superadmin
        Route::post('departamentos',             [DepartamentoController::class, 'store']);
        Route::put('departamentos/{id}',         [DepartamentoController::class, 'update']);
        Route::put('departamentos/{id}/activo',  [DepartamentoController::class, 'toggleActivo']);

        // Asignar encargados de departamento
        Route::get('departamento-responsables',            [\App\Http\Controllers\Api\DepartamentoResponsableController::class, 'index']);
        Route::post('departamento-responsables',           [\App\Http\Controllers\Api\DepartamentoResponsableController::class, 'asignar']);
        Route::delete('departamento-responsables/{id}',    [\App\Http\Controllers\Api\DepartamentoResponsableController::class, 'quitar']);
    });
});
