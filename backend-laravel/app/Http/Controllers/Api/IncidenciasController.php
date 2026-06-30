<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estado;
use App\Models\HistorialActividad;
use App\Models\Incidencia;
use App\Models\IncidenciaComentario;
use App\Models\IncidenciaFoto;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class IncidenciasController extends Controller
{
    // Selección base reutilizable equivalente a vw_incidencias_completo
    private function baseQuery()
    {
        return Incidencia::query()
            ->join('tipos_incidencia as ti', 'incidencias.id_tipo', '=', 'ti.id_tipo')
            ->leftJoin('subtipos_incidencia as st', 'incidencias.id_subtipo', '=', 'st.id_subtipo')
            ->join('estados as e', 'incidencias.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'incidencias.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->join('provincias as p', 'c.id_provincia', '=', 'p.id_provincia')
            ->join('paises as pa', 'p.id_pais', '=', 'pa.id_pais')
            ->leftJoin('usuarios as uc', 'incidencias.id_usuario_creador', '=', 'uc.id_usuario')
            ->select([
                'incidencias.id_incidencia', 'incidencias.titulo', 'incidencias.descripcion', 'incidencias.prioridad',
                'ti.nombre as tipo', 'st.nombre as subtipo',
                'e.nombre as estado', 'e.color as color_estado',
                'incidencias.estado_aprobacion',
                'z.nombre as zona', 'c.nombre as ciudad', 'p.nombre as provincia', 'pa.nombre as pais',
                'incidencias.latitud', 'incidencias.longitud',
                'incidencias.fecha_ocurrencia', 'incidencias.hora_ocurrencia',
                'incidencias.fecha_resolucion', 'incidencias.tiempo_resolucion_horas',
                'incidencias.fecha_registro', 'incidencias.fecha_actualizacion',
                'incidencias.reportante_nombre', 'incidencias.reportante_contacto',
                DB::raw("CONCAT(uc.nombre,' ',IFNULL(uc.apellido,'')) as creado_por"),
                'incidencias.id_tipo', 'incidencias.id_subtipo', 'incidencias.id_estado_actual',
                'incidencias.id_zona', 'incidencias.id_usuario_creador',
            ]);
    }
// GET /api/incidencias
public function index(Request $request)
{
    $usuario = $request->user();
    $query = $this->baseQuery();

    $verTodas = $request->boolean('todas') && $usuario->rol === 'admin';
    if (! $verTodas) {
        $query->where('incidencias.estado_aprobacion', 'aprobada');
    }

    if ($buscar = $request->query('buscar')) {
        $query->where(function ($q) use ($buscar) {
            $q->where('incidencias.titulo', 'like', "%$buscar%")
              ->orWhere('incidencias.descripcion', 'like', "%$buscar%");
        });
    }

    if ($tipo = $request->query('tipo'))         $query->where('incidencias.id_tipo', $tipo);
    if ($subtipo = $request->query('subtipo'))    $query->where('incidencias.id_subtipo', $subtipo);
    
    // ← CAMBIO AQUÍ: usar ID en lugar de nombre
    if ($estado = $request->query('estado'))      $query->where('incidencias.id_estado_actual', $estado);
    if ($prioridad = $request->query('prioridad'))$query->where('incidencias.prioridad', $prioridad);
    if ($zona = $request->query('zona'))          $query->where('incidencias.id_zona', $zona);

    if ($desde = $request->query('desde'))        $query->whereDate('incidencias.fecha_ocurrencia', '>=', $desde);
    if ($hasta = $request->query('hasta'))        $query->whereDate('incidencias.fecha_ocurrencia', '<=', $hasta);

    $porPagina = (int) $request->query('por_pagina', 10);
    $pagina = (int) $request->query('pagina', 1);

    $total = (clone $query)->count();
    $datos = $query->orderByDesc('incidencias.fecha_registro')
        ->skip(($pagina - 1) * $porPagina)
        ->take($porPagina)
        ->get();

    return response()->json([
        'datos' => $datos,
        'total' => $total,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'debug_filtros' => $request->all(),
        'debug_sql' => $query->toSql(),
    ]);
    }


    // GET /api/incidencias/mapa
    public function mapa()
    {
        $datos = $this->baseQuery()
            ->where('incidencias.estado_aprobacion', 'aprobada')
            ->whereNotNull('incidencias.latitud')
            ->whereNotNull('incidencias.longitud')
            ->get(['incidencias.id_incidencia', 'incidencias.titulo', 'ti.nombre as tipo', 'e.nombre as estado', 'e.color as color_estado', 'z.nombre as zona', 'incidencias.latitud', 'incidencias.longitud']);

        return response()->json($datos);
    }

    // GET /api/incidencias/pendientes-aprobacion
    public function pendientesAprobacion()
    {
        $datos = $this->baseQuery()
            ->where('incidencias.estado_aprobacion', 'pendiente_revision')
            ->orderBy('incidencias.fecha_registro')
            ->get();

        return response()->json($datos);
    }

            // GET /api/incidencias/{id}
        public function show(int $id)
        {
            $inc = $this->baseQuery()
                ->where('incidencias.id_incidencia', $id)
                ->first();

            if (! $inc) {
                return response()->json([
                    'ok' => false, 
                    'mensaje' => 'Incidencia no encontrada'
                ], 404);
            }

            return response()->json($inc);
        }

    // POST /api/incidencias
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:200',
            'id_tipo' => 'required|integer|exists:tipos_incidencia,id_tipo',
            'id_zona' => 'required|integer|exists:zonas,id_zona',
            'fecha_ocurrencia' => 'required|date',
            'prioridad' => 'required|in:Baja,Media,Alta',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = $request->user();
        $estadoInicial = Estado::where('nombre', 'Pendiente')->first();

        $incidencia = Incidencia::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'prioridad' => $request->prioridad,
            'id_tipo' => $request->id_tipo,
            'id_subtipo' => $request->id_subtipo,
            'id_estado_actual' => $estadoInicial?->id_estado ?? 1,
            'estado_aprobacion' => 'pendiente_revision',
            'id_zona' => $request->id_zona,
            'latitud' => $request->latitud,
            'longitud' => $request->longitud,
            'direccion_texto' => $request->direccion_texto,
            'fecha_ocurrencia' => $request->fecha_ocurrencia,
            'hora_ocurrencia' => $request->hora_ocurrencia,
            'reportante_nombre' => $request->reportante_nombre,
            'reportante_contacto' => $request->reportante_contacto,
            'id_usuario_creador' => $usuario->id_usuario,
        ]);

        DB::table('incidencia_estados_historial')->insert([
            'id_incidencia' => $incidencia->id_incidencia,
            'id_estado_anterior' => null,
            'id_estado_nuevo' => $incidencia->id_estado_actual,
            'id_usuario' => $usuario->id_usuario,
            'comentario' => 'Incidencia registrada, pendiente de revisión',
            'fecha_cambio' => now(),
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, $incidencia->id_incidencia, 'creo_incidencia',
            "{$usuario->nombre_completo} registró la incidencia \"{$incidencia->titulo}\" (pendiente de revisión)",
            $request->ip()
        );

        // Notificar a admins
        $admins = Usuario::where('rol', 'admin')->where('activo', 1)->get();
        foreach ($admins as $admin) {
            Notificacion::create([
                'id_usuario' => $admin->id_usuario,
                'id_incidencia' => $incidencia->id_incidencia,
                'titulo' => 'Nueva incidencia por revisar',
                'mensaje' => "\"{$incidencia->titulo}\" necesita tu aprobación.",
            ]);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => 'Incidencia registrada. Quedará visible una vez aprobada por un administrador.',
            'id' => $incidencia->id_incidencia,
        ], 201);
    }

    // GET /api/incidencias/mis-reportes
    public function misReportes(Request $request)
    {
        $usuario = $request->user();
        $datos = $this->baseQuery()
            ->where('incidencias.id_usuario_creador', $usuario->id_usuario)
            ->orderByDesc('incidencias.fecha_registro')
            ->get();
        return response()->json(['datos' => $datos, 'total' => $datos->count()]);
    }   

    // PUT /api/incidencias/{id}
    public function update(Request $request, $id)
    {
        $incidencia = Incidencia::find($id);
        if (! $incidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $incidencia->update($request->only([
            'titulo', 'descripcion', 'prioridad', 'id_tipo', 'id_subtipo',
            'id_estado_actual', 'id_zona', 'latitud', 'longitud', 'fecha_ocurrencia',
        ]));

        $usuario = $request->user();
        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'edito_incidencia',
            "{$usuario->nombre_completo} editó la incidencia #{$id}", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Incidencia actualizada.']);
    }

    // DELETE /api/incidencias/{id} (solo admin, validado por middleware en rutas)
    public function destroy(Request $request, $id)
    {
        Incidencia::where('id_incidencia', $id)->delete();

        $usuario = $request->user();
        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'elimino_incidencia',
            "Admin {$usuario->nombre_completo} eliminó la incidencia #{$id}", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Incidencia eliminada.']);
    }

    // PUT /api/incidencias/{id}/aprobar
    public function aprobar(Request $request, $id)
    {
        $incidencia = Incidencia::find($id);
        if (! $incidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $usuario = $request->user();
        $incidencia->update([
            'estado_aprobacion' => 'aprobada',
            'id_admin_revisor' => $usuario->id_usuario,
            'fecha_revision' => now(),
        ]);

        if ($incidencia->id_usuario_creador) {
            Notificacion::create([
                'id_usuario' => $incidencia->id_usuario_creador,
                'id_incidencia' => $id,
                'titulo' => 'Incidencia aprobada',
                'mensaje' => "Tu incidencia \"{$incidencia->titulo}\" fue aprobada y ya es visible.",
            ]);
        }

        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'aprobo_incidencia',
            "Admin {$usuario->nombre_completo} aprobó la incidencia #{$id}", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Incidencia aprobada.']);
    }

    // PUT /api/incidencias/{id}/rechazar
    public function rechazar(Request $request, $id)
    {
        $incidencia = Incidencia::find($id);
        if (! $incidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $usuario = $request->user();
        $motivo = $request->input('motivo');

        $incidencia->update([
            'estado_aprobacion' => 'rechazada',
            'id_admin_revisor' => $usuario->id_usuario,
            'fecha_revision' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        if ($incidencia->id_usuario_creador) {
            Notificacion::create([
                'id_usuario' => $incidencia->id_usuario_creador,
                'id_incidencia' => $id,
                'titulo' => 'Incidencia rechazada',
                'mensaje' => "Tu incidencia \"{$incidencia->titulo}\" fue rechazada. Motivo: " . ($motivo ?: 'No especificado'),
            ]);
        }

        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'rechazo_incidencia',
            "Admin {$usuario->nombre_completo} rechazó la incidencia #{$id}. Motivo: " . ($motivo ?: 'N/A'),
            $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Incidencia rechazada.']);
    }

    // GET /api/incidencias/exportar/csv
    public function exportarCsv()
    {
        $datos = $this->baseQuery()->where('incidencias.estado_aprobacion', 'aprobada')->get();
        return response()->json($datos);
    }

    /* ══════════════════════════════════════════════════════
       COMENTARIOS
    ══════════════════════════════════════════════════════ */

    // GET /api/incidencias/{id}/comentarios
    public function comentarios(int $id)
    {
        if (! Incidencia::where('id_incidencia', $id)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $comentarios = IncidenciaComentario::with('usuario:id_usuario,nombre,apellido')
            ->where('id_incidencia', $id)
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function ($c) {
                return [
                    'id_comentario' => $c->id_comentario,
                    'comentario'    => $c->comentario,
                    'fecha'         => $c->fecha,
                    'usuario'       => $c->usuario ? [
                        'id_usuario'  => $c->usuario->id_usuario,
                        'nombre'      => trim($c->usuario->nombre . ' ' . $c->usuario->apellido),
                    ] : null,
                ];
            });

        return response()->json(['ok' => true, 'datos' => $comentarios]);
    }

    // POST /api/incidencias/{id}/comentarios
    public function agregarComentario(Request $request, int $id)
    {
        $usuario = $request->user();

        if (! Incidencia::where('id_incidencia', $id)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'comentario' => 'required|string|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $comentario = IncidenciaComentario::create([
            'id_incidencia' => $id,
            'id_usuario'    => $usuario->id_usuario,
            'comentario'    => trim($request->comentario),
            'fecha'         => now(),
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'comentario_incidencia',
            "{$usuario->nombre} comentó en la incidencia #{$id}.",
            $request->ip()
        );

        return response()->json([
            'ok' => true,
            'mensaje' => 'Comentario agregado.',
            'datos' => [
                'id_comentario' => $comentario->id_comentario,
                'comentario'    => $comentario->comentario,
                'fecha'         => $comentario->fecha,
                'usuario'       => [
                    'id_usuario'  => $usuario->id_usuario,
                    'nombre'      => trim($usuario->nombre . ' ' . $usuario->apellido),
                ],
            ],
        ], 201);
    }

    /* ══════════════════════════════════════════════════════
       FOTOS (antes / después)
    ══════════════════════════════════════════════════════ */

    // GET /api/incidencias/{id}/fotos
    public function fotos(int $id)
    {
        if (! Incidencia::where('id_incidencia', $id)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $fotos = IncidenciaFoto::with('usuario:id_usuario,nombre,apellido')
            ->where('id_incidencia', $id)
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function ($f) {
                return [
                    'id_foto' => $f->id_foto,
                    'tipo'    => $f->tipo,
                    'url'     => Storage::disk('public')->url($f->ruta),
                    'fecha'   => $f->fecha,
                    'usuario' => $f->usuario ? trim($f->usuario->nombre . ' ' . $f->usuario->apellido) : null,
                ];
            });

        return response()->json(['ok' => true, 'datos' => $fotos]);
    }

    // POST /api/incidencias/{id}/fotos
    public function agregarFoto(Request $request, int $id)
    {
        $usuario = $request->user();

        if (! Incidencia::where('id_incidencia', $id)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'tipo' => 'required|in:antes,despues',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $ruta = $request->file('foto')->store('incidencias', 'public');

        $foto = IncidenciaFoto::create([
            'id_incidencia' => $id,
            'id_usuario'    => $usuario->id_usuario,
            'ruta'          => $ruta,
            'tipo'          => $request->tipo,
            'fecha'         => now(),
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'foto_incidencia',
            "{$usuario->nombre} subió una foto de '{$request->tipo}' en la incidencia #{$id}.",
            $request->ip()
        );

        return response()->json([
            'ok' => true,
            'mensaje' => 'Foto subida correctamente.',
            'datos' => [
                'id_foto' => $foto->id_foto,
                'tipo'    => $foto->tipo,
                'url'     => Storage::disk('public')->url($foto->ruta),
                'fecha'   => $foto->fecha,
                'usuario' => trim($usuario->nombre . ' ' . $usuario->apellido),
            ],
        ], 201);
    }

    // DELETE /api/incidencias/{id}/fotos/{idFoto}
    public function eliminarFoto(Request $request, int $id, int $idFoto)
    {
        $usuario = $request->user();
        $foto = IncidenciaFoto::where('id_incidencia', $id)->where('id_foto', $idFoto)->first();

        if (! $foto) {
            return response()->json(['ok' => false, 'mensaje' => 'Foto no encontrada'], 404);
        }

        if ($usuario->rol !== 'admin' && $foto->id_usuario !== $usuario->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No tienes permiso para eliminar esta foto'], 403);
        }

        Storage::disk('public')->delete($foto->ruta);
        $foto->delete();

        return response()->json(['ok' => true, 'mensaje' => 'Foto eliminada.']);
    }
}
