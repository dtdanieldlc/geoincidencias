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
use Barryvdh\DomPDF\Facade\Pdf;
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
    if ($sucursal = $request->query('sucursal'))  $query->where('c.id_ciudad', $sucursal);
    if ($usuario = $request->query('usuario'))    $query->where('incidencias.id_usuario_creador', $usuario);

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

    // GET /api/incidencias/reportantes
    // Lista de usuarios que han reportado al menos una incidencia (para el filtro).
    public function reportantes(Request $request)
    {
        $lista = $this->baseQuery()
            ->whereNotNull('incidencias.id_usuario_creador')
            ->get(['incidencias.id_usuario_creador', 'uc.nombre', 'uc.apellido'])
            ->unique('id_usuario_creador')
            ->map(fn ($i) => [
                'id_usuario' => $i->id_usuario_creador,
                'nombre'     => trim($i->nombre . ' ' . ($i->apellido ?? '')),
            ])
            ->sortBy('nombre')
            ->values();

        return response()->json($lista);
    }

    // GET /api/incidencias/facetas
    // Búsqueda por facetas: para cada filtro (tipo, estado, prioridad, zona,
    // sucursal) calcula qué valores siguen dando al menos un resultado si se
    // aplican los DEMÁS filtros activos (sin incluir ese mismo filtro). El
    // frontend usa esto para deshabilitar en los <select> las opciones que
    // darían 0 resultados, sin tener que adivinarlo por ensayo y error.
    public function facetas(Request $request)
    {
        $usuario  = $request->user();
        $verTodas = $request->boolean('todas') && $usuario->rol === 'admin';

        $construir = function (array $excluir) use ($request, $verTodas) {
            $query = $this->baseQuery();
            if (! $verTodas) {
                $query->where('incidencias.estado_aprobacion', 'aprobada');
            }

            if (($buscar = $request->query('buscar')) && !in_array('buscar', $excluir)) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('incidencias.titulo', 'like', "%$buscar%")
                      ->orWhere('incidencias.descripcion', 'like', "%$buscar%");
                });
            }
            if (($tipo = $request->query('tipo')) && !in_array('tipo', $excluir))
                $query->where('incidencias.id_tipo', $tipo);
            if (($estado = $request->query('estado')) && !in_array('estado', $excluir))
                $query->where('incidencias.id_estado_actual', $estado);
            if (($prioridad = $request->query('prioridad')) && !in_array('prioridad', $excluir))
                $query->where('incidencias.prioridad', $prioridad);
            if (($zona = $request->query('zona')) && !in_array('zona', $excluir))
                $query->where('incidencias.id_zona', $zona);
            if (($sucursal = $request->query('sucursal')) && !in_array('sucursal', $excluir))
                $query->where('c.id_ciudad', $sucursal);
            if (($desde = $request->query('desde')) && !in_array('desde', $excluir))
                $query->whereDate('incidencias.fecha_ocurrencia', '>=', $desde);
            if (($hasta = $request->query('hasta')) && !in_array('hasta', $excluir))
                $query->whereDate('incidencias.fecha_ocurrencia', '<=', $hasta);

            return $query;
        };

        return response()->json([
            'tipos'       => $construir(['tipo'])->get(['incidencias.id_tipo'])->pluck('id_tipo')->unique()->values(),
            'estados'     => $construir(['estado'])->get(['incidencias.id_estado_actual'])->pluck('id_estado_actual')->unique()->values(),
            'prioridades' => $construir(['prioridad'])->get(['incidencias.prioridad'])->pluck('prioridad')->unique()->values(),
            'zonas'       => $construir(['zona'])->get(['incidencias.id_zona'])->pluck('id_zona')->unique()->values(),
            'sucursales'  => $construir(['sucursal'])->get(['c.id_ciudad'])->pluck('id_ciudad')->unique()->values(),
        ]);
    }

    // GET /api/incidencias/posibles-duplicados?tipo=&zona=
    // Antes de registrar, avisa (sin bloquear) si ya existe algo muy
    // parecido: mismo tipo + misma zona, reportado en las últimas 48h y
    // que todavía no está resuelto/cerrado. Evita que 3 personas reporten
    // por separado "se fue la luz" en la misma sucursal el mismo día.
    public function posiblesDuplicados(Request $request)
    {
        $request->validate(['tipo' => 'required|integer', 'zona' => 'required|integer']);

        $similares = $this->baseQuery()
            ->whereIn('incidencias.estado_aprobacion', ['aprobada', 'pendiente_revision'])
            ->whereNotIn('e.nombre', ['Resuelto', 'Cerrado'])
            ->where('incidencias.id_tipo', $request->query('tipo'))
            ->where('incidencias.id_zona', $request->query('zona'))
            ->where('incidencias.fecha_registro', '>=', now()->subHours(48))
            ->orderByDesc('incidencias.fecha_registro')
            ->limit(5)
            ->get(['incidencias.id_incidencia', 'incidencias.titulo', 'incidencias.fecha_registro', 'e.nombre as estado', 'incidencias.reportante_nombre']);

        return response()->json(['datos' => $similares]);
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

    // GET /api/incidencias/{id}/ficha-pdf
    // Ficha en PDF de UNA sola incidencia: todos sus datos, comentarios y
    // enlaces a las fotos de evidencia. Pensado para imprimir o adjuntar
    // en un reporte a terceros (ej. autoridades, en casos de seguridad).
    public function fichaPdf(int $id)
    {
        $inc = $this->baseQuery()->where('incidencias.id_incidencia', $id)->first();
        if (! $inc) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $comentarios = IncidenciaComentario::with('usuario:id_usuario,nombre,apellido')
            ->where('id_incidencia', $id)->orderBy('fecha')->get();

        $fotos = IncidenciaFoto::where('id_incidencia', $id)->get()
            ->map(fn ($f) => Storage::disk('public')->url($f->ruta));

        $pdf = Pdf::loadView('reportes.ficha-incidencia', [
            'inc'         => $inc,
            'comentarios' => $comentarios,
            'fotos'       => $fotos,
            'generadoEn'  => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('ficha-incidencia-' . $id . '.pdf');
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

        // ── Alerta inmediata por correo para incidencias críticas ──
        // Prioridad Alta + un tipo "crítico" (por defecto: Seguridad, Accidentes)
        // dispara un correo al instante, sin esperar la cola de aprobación del
        // panel admin — la vida/seguridad no debería esperar ese trámite.
        // Configurable con las variables de entorno TIPOS_CRITICOS y
        // ALERTA_SEGURIDAD_EMAILS (correos separados por coma).
        if ($incidencia->prioridad === 'Alta') {
            $tiposCriticos = array_map('trim', explode(',', env('TIPOS_CRITICOS', 'Seguridad,Accidentes')));
            $nombreTipo = $incidencia->tipo?->nombre;
            $destinatarios = array_filter(array_map('trim', explode(',', env('ALERTA_SEGURIDAD_EMAILS', ''))));

            if ($nombreTipo && in_array($nombreTipo, $tiposCriticos) && count($destinatarios) > 0) {
                try {
                    \Illuminate\Support\Facades\Mail::to($destinatarios)
                        ->send(new \App\Mail\AlertaIncidenciaCritica($incidencia));
                } catch (\Throwable $e) {
                    // No dejar que un problema de correo (SMTP no configurado, etc.)
                    // impida registrar la incidencia.
                    \Illuminate\Support\Facades\Log::warning('No se pudo enviar la alerta de incidencia crítica: ' . $e->getMessage());
                }
            }
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

    // ──────────────────────────────────────────────────────────────
    //  GET /api/admin/usuarios/{id}/reporte-pdf
    //  Un admin/superadmin descarga el historial de incidencias
    //  de CUALQUIER usuario, en el mismo formato de "Mis Reportes".
    // ──────────────────────────────────────────────────────────────
    public function reportePdfUsuario(Request $request, $id)
    {
        $usuario = \App\Models\Usuario::findOrFail($id);

        $incidencias = $this->baseQuery()
            ->where('incidencias.id_usuario_creador', $usuario->id_usuario)
            ->orderByDesc('incidencias.fecha_registro')
            ->get();

        $pdf = Pdf::loadView('reportes.pdf-mis-reportes', [
            'incidencias' => $incidencias,
            'usuario'     => trim($usuario->nombre . ' ' . ($usuario->apellido ?? '')),
            'generadoEn'  => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $nombreArchivo = \Illuminate\Support\Str::slug($usuario->nombre . '-' . $usuario->apellido);
        return $pdf->download("reporte-{$nombreArchivo}-" . now()->format('Y-m-d') . '.pdf');
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/incidencias/mis-reportes/pdf
    //  Cualquier usuario puede exportar SU PROPIO historial de
    //  incidencias reportadas, como PDF descargable.
    // ──────────────────────────────────────────────────────────────
    public function misReportesPdf(Request $request)
    {
        $usuario = $request->user();

        $incidencias = $this->baseQuery()
            ->where('incidencias.id_usuario_creador', $usuario->id_usuario)
            ->orderByDesc('incidencias.fecha_registro')
            ->get();

        $pdf = Pdf::loadView('reportes.pdf-mis-reportes', [
            'incidencias' => $incidencias,
            'usuario'     => trim($usuario->nombre . ' ' . ($usuario->apellido ?? '')),
            'generadoEn'  => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('mis-reportes-' . now()->format('Y-m-d') . '.pdf');
    }   

    // PUT /api/incidencias/{id}
    // Flujo de estados permitido (orden fijo del ciclo de atención).
    // Solo se permite avanzar al siguiente estado o reabrir un caso Resuelto
    // devolviéndolo a "En proceso". No se permite saltar estados ni retroceder
    // desde "Cerrado", que es un estado final.
    private const TRANSICIONES_VALIDAS = [
        1 => [2],       // Pendiente        -> En proceso
        2 => [1, 3],    // En proceso       -> Pendiente | Resuelto
        3 => [2, 4],    // Resuelto         -> En proceso (reabrir) | Cerrado
        4 => [],        // Cerrado (estado final, no editable)
    ];

    public function update(Request $request, $id)
    {
        $incidencia = Incidencia::find($id);
        if (! $incidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Incidencia no encontrada'], 404);
        }

        $usuario = $request->user();
        $estadoAnteriorId = $incidencia->id_estado_actual;
        $nuevoEstadoId    = $request->input('id_estado_actual');
        $cambiaEstado     = $request->has('id_estado_actual') && (int) $nuevoEstadoId !== (int) $estadoAnteriorId;

        // ── Regla de negocio: no se puede editar una incidencia Cerrada ──
        if ($estadoAnteriorId == 4) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'La incidencia está Cerrada y no admite más cambios.',
            ], 422);
        }

        // ── Regla de negocio: el cambio de estado debe seguir el flujo definido ──
        if ($cambiaEstado) {
            $permitidos = self::TRANSICIONES_VALIDAS[$estadoAnteriorId] ?? [];
            if (! in_array((int) $nuevoEstadoId, $permitidos, true)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Transición de estado no permitida según el flujo definido.',
                ], 422);
            }
        }

        $datos = $request->only([
            'titulo', 'descripcion', 'prioridad', 'id_tipo', 'id_subtipo',
            'id_estado_actual', 'id_zona', 'latitud', 'longitud', 'fecha_ocurrencia',
        ]);

        // Al llegar a "Resuelto" se sella fecha y tiempo total de resolución.
        if ($cambiaEstado && (int) $nuevoEstadoId === 3) {
            $datos['fecha_resolucion'] = now();
            $datos['tiempo_resolucion_horas'] = round(
                now()->diffInMinutes($incidencia->fecha_registro) / 60, 2
            );
        }

        $incidencia->update($datos);

        // ── Trazabilidad: cada cambio de estado queda en el historial ──
        if ($cambiaEstado) {
            DB::table('incidencia_estados_historial')->insert([
                'id_incidencia'      => $incidencia->id_incidencia,
                'id_estado_anterior' => $estadoAnteriorId,
                'id_estado_nuevo'    => $nuevoEstadoId,
                'id_usuario'         => $usuario->id_usuario,
                'comentario'         => $request->input('comentario_estado', 'Cambio de estado'),
                'fecha_cambio'       => now(),
            ]);

            HistorialActividad::registrar(
                $usuario->id_usuario, $id, 'cambio_estado_incidencia',
                "{$usuario->nombre_completo} cambió el estado de la incidencia #{$id}",
                $request->ip()
            );
        } else {
            HistorialActividad::registrar(
                $usuario->id_usuario, $id, 'edito_incidencia',
                "{$usuario->nombre_completo} editó la incidencia #{$id}", $request->ip()
            );
        }

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

    // PUT /api/incidencias/aprobar-lote  { ids: [1,2,3] }
    public function aprobarLote(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $usuario = $request->user();
        $ok = 0;

        foreach (Incidencia::whereIn('id_incidencia', $request->ids)->get() as $incidencia) {
            $incidencia->update([
                'estado_aprobacion' => 'aprobada',
                'id_admin_revisor'  => $usuario->id_usuario,
                'fecha_revision'    => now(),
            ]);

            if ($incidencia->id_usuario_creador) {
                Notificacion::create([
                    'id_usuario'    => $incidencia->id_usuario_creador,
                    'id_incidencia' => $incidencia->id_incidencia,
                    'titulo'        => 'Incidencia aprobada',
                    'mensaje'       => "Tu incidencia \"{$incidencia->titulo}\" fue aprobada y ya es visible.",
                ]);
            }

            HistorialActividad::registrar(
                $usuario->id_usuario, $incidencia->id_incidencia, 'aprobo_incidencia',
                "Admin {$usuario->nombre_completo} aprobó la incidencia #{$incidencia->id_incidencia} (acción en lote)", $request->ip()
            );
            $ok++;
        }

        return response()->json(['ok' => true, 'mensaje' => "$ok incidencia(s) aprobada(s)."]);
    }

    // PUT /api/incidencias/rechazar-lote  { ids: [1,2,3], motivo: '...' }
    public function rechazarLote(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $usuario = $request->user();
        $motivo  = $request->input('motivo');
        $ok = 0;

        foreach (Incidencia::whereIn('id_incidencia', $request->ids)->get() as $incidencia) {
            $incidencia->update([
                'estado_aprobacion' => 'rechazada',
                'id_admin_revisor'  => $usuario->id_usuario,
                'fecha_revision'    => now(),
                'motivo_rechazo'    => $motivo,
            ]);

            if ($incidencia->id_usuario_creador) {
                Notificacion::create([
                    'id_usuario'    => $incidencia->id_usuario_creador,
                    'id_incidencia' => $incidencia->id_incidencia,
                    'titulo'        => 'Incidencia rechazada',
                    'mensaje'       => "Tu incidencia \"{$incidencia->titulo}\" fue rechazada. Motivo: " . ($motivo ?: 'No especificado'),
                ]);
            }

            HistorialActividad::registrar(
                $usuario->id_usuario, $incidencia->id_incidencia, 'rechazo_incidencia',
                "Admin {$usuario->nombre_completo} rechazó la incidencia #{$incidencia->id_incidencia} (acción en lote). Motivo: " . ($motivo ?: 'N/A'),
                $request->ip()
            );
            $ok++;
        }

        return response()->json(['ok' => true, 'mensaje' => "$ok incidencia(s) rechazada(s)."]);
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
