<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    const SLA_HORAS_ALTA = 48;

    /** IDs de ciudad visibles para el usuario autenticado (null = sin filtro / todo). */
    private function ciudadesScope(?Request $request = null): ?array
    {
        $user = $request ? $request->user() : auth()->user();
        if (! $user) {
            return null;
        }
        if ($user->rol === 'admin') {
            $ids = $user->idsCiudadesEncargadas();
            return $ids ?: [-1]; // sin sucursal = no ve nada
        }
        // superadmin y resto del staff con acceso al dashboard: todo
        return null;
    }

    private function aplicarScopeCiudad($query, ?array $idsCiudad, string $zonaAlias = 'z')
    {
        if ($idsCiudad === null) {
            return $query;
        }
        return $query->whereIn("{$zonaAlias}.id_ciudad", $idsCiudad);
    }

    public function resumen(Request $request)
    {
        $ids = $this->ciudadesScope($request);

        $q = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->where('i.estado_aprobacion', 'aprobada');

        if ($ids !== null) {
            $q->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
              ->whereIn('z.id_ciudad', $ids);
        }

        $r = $q->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN e.nombre='Pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN e.nombre='Pendiente' THEN 1 ELSE 0 END) as abiertas,
                SUM(CASE WHEN e.nombre='En proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN e.nombre='Resuelto' THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN e.nombre='Cerrado' THEN 1 ELSE 0 END) as cerradas,
                SUM(CASE WHEN i.prioridad='Alta' THEN 1 ELSE 0 END) as alta_prioridad,
                SUM(CASE WHEN i.estado_aprobacion='pendiente_revision' THEN 1 ELSE 0 END) as pendientes_aprobacion,
                SUM(CASE WHEN i.prioridad='Alta' AND e.nombre NOT IN ('Resuelto','Cerrado')
                    AND TIMESTAMPDIFF(HOUR, i.fecha_ocurrencia, NOW()) > " . self::SLA_HORAS_ALTA . "
                    THEN 1 ELSE 0 END) as vencidas
            ")
            ->first();

        return response()->json($r);
    }

    public function vencidas(Request $request)
    {
        $ids = $this->ciudadesScope($request);

        $q = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->where('i.estado_aprobacion', 'aprobada')
            ->where('i.prioridad', 'Alta')
            ->whereNotIn('e.nombre', ['Resuelto', 'Cerrado'])
            ->whereRaw('TIMESTAMPDIFF(HOUR, i.fecha_ocurrencia, NOW()) > ?', [self::SLA_HORAS_ALTA]);

        $this->aplicarScopeCiudad($q, $ids, 'z');

        $datos = $q->orderBy('i.fecha_ocurrencia')
            ->select(
                'i.id_incidencia', 'i.titulo', 'i.prioridad', 'i.fecha_ocurrencia',
                'ti.nombre as tipo', 'e.nombre as estado', 'c.nombre as ciudad', 'z.nombre as zona'
            )
            ->get();

        return response()->json(['datos' => $datos, 'sla_horas' => self::SLA_HORAS_ALTA]);
    }

    public function porTipo(Request $request)
    {
        $ids = $this->ciudadesScope($request);

        $join = function ($join) use ($ids) {
            $join->on('ti.id_tipo', '=', 'i.id_tipo')->where('i.estado_aprobacion', '=', 'aprobada');
            if ($ids !== null) {
                $join->whereIn('i.id_zona', function ($q) use ($ids) {
                    $q->select('id_zona')->from('zonas')->whereIn('id_ciudad', $ids);
                });
            }
        };

        $datos = DB::table('tipos_incidencia as ti')
            ->leftJoin('incidencias as i', $join)
            ->groupBy('ti.nombre')
            ->orderByDesc(DB::raw('COUNT(i.id_incidencia)'))
            ->select('ti.nombre as tipo', DB::raw('COUNT(i.id_incidencia) as total'))
            ->get();

        return response()->json($datos);
    }

    public function porEstado(Request $request)
    {
        $ids = $this->ciudadesScope($request);

        $join = function ($join) use ($ids) {
            $join->on('e.id_estado', '=', 'i.id_estado_actual')->where('i.estado_aprobacion', '=', 'aprobada');
            if ($ids !== null) {
                $join->whereIn('i.id_zona', function ($q) use ($ids) {
                    $q->select('id_zona')->from('zonas')->whereIn('id_ciudad', $ids);
                });
            }
        };

        $datos = DB::table('estados as e')
            ->leftJoin('incidencias as i', $join)
            ->groupBy('e.nombre', 'e.color')
            ->select('e.nombre as estado', 'e.color', DB::raw('COUNT(i.id_incidencia) as total'))
            ->get();

        return response()->json($datos);
    }

    public function porSucursal(Request $request)
    {
        $permitidas = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];
        $ids = $this->ciudadesScope($request);

        $q = DB::table('ciudades as c')
            ->whereIn('c.nombre', $permitidas)
            ->leftJoin('zonas as z', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->leftJoin('incidencias as i', function ($join) {
                $join->on('z.id_zona', '=', 'i.id_zona')->where('i.estado_aprobacion', '=', 'aprobada');
            })
            ->leftJoin('estados as e', 'e.id_estado', '=', 'i.id_estado_actual');

        if ($ids !== null) {
            $q->whereIn('c.id_ciudad', $ids);
        }

        $datos = $q->groupBy('c.id_ciudad', 'c.nombre', 'c.latitud_ref', 'c.longitud_ref')
            ->select(
                'c.id_ciudad as id',
                'c.nombre as sucursal',
                'c.latitud_ref as latitud',
                'c.longitud_ref as longitud',
                DB::raw('COUNT(i.id_incidencia) as total'),
                DB::raw("SUM(CASE WHEN i.estado_aprobacion='aprobada' AND e.nombre NOT IN ('Resuelto','Cerrado') THEN 1 ELSE 0 END) as abiertas")
            )
            ->orderBy('c.nombre')
            ->get();

        return response()->json($datos);
    }

    public function porZona(Request $request)
    {
        $ids = $this->ciudadesScope($request);

        $q = DB::table('zonas as z')
            ->leftJoin('incidencias as i', function ($join) {
                $join->on('z.id_zona', '=', 'i.id_zona')->where('i.estado_aprobacion', '=', 'aprobada');
            });

        if ($ids !== null) {
            $q->whereIn('z.id_ciudad', $ids);
        }

        $datos = $q->groupBy('z.nombre')
            ->orderByDesc(DB::raw('COUNT(i.id_incidencia)'))
            ->select('z.nombre as zona', DB::raw('COUNT(i.id_incidencia) as total'))
            ->get();

        return response()->json($datos);
    }

    public function ultimas(Request $request)
    {
        $ids = $this->ciudadesScope($request);

        $q = DB::table('incidencias as i')
            ->join('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->where('i.estado_aprobacion', 'aprobada');

        $this->aplicarScopeCiudad($q, $ids, 'z');

        $datos = $q->orderByDesc('i.fecha_registro')
            ->limit(10)
            ->select(
                'i.id_incidencia', 'i.titulo', 'i.prioridad', 'i.fecha_registro',
                'ti.nombre as tipo', 'e.nombre as estado', 'e.color as color_estado',
                'c.nombre as ciudad', 'z.nombre as zona'
            )
            ->get();

        return response()->json($datos);
    }


    public function recordarVencidas(Request $request)
    {
        $user = $request->user();
        if (! $user || $user->rol !== 'superadmin') {
            return response()->json(['ok' => false, 'mensaje' => 'Solo el Superadmin puede enviar recordatorios.'], 403);
        }

        $q = DB::table('incidencias as i')
            ->join('estados as e', 'i.id_estado_actual', '=', 'e.id_estado')
            ->join('zonas as z', 'i.id_zona', '=', 'z.id_zona')
            ->join('ciudades as c', 'z.id_ciudad', '=', 'c.id_ciudad')
            ->where('i.estado_aprobacion', 'aprobada')
            ->where('i.prioridad', 'Alta')
            ->whereNotIn('e.nombre', ['Resuelto', 'Cerrado'])
            ->whereRaw('TIMESTAMPDIFF(HOUR, i.fecha_ocurrencia, NOW()) > ?', [self::SLA_HORAS_ALTA])
            ->select('i.id_incidencia', 'i.titulo', 'c.id_ciudad', 'c.nombre as ciudad');

        $vencidas = $q->get();
        if ($vencidas->isEmpty()) {
            return response()->json(['ok' => true, 'mensaje' => 'No hay incidencias vencidas para recordar.', 'notificados' => 0]);
        }

        $porCiudad = $vencidas->groupBy('id_ciudad');
        $notificados = 0;

        foreach ($porCiudad as $idCiudad => $items) {
            $ciudad = $items->first()->ciudad ?? 'Sucursal';
            $lista = $items->take(8)->map(fn ($x) => '• #' . $x->id_incidencia . ' ' . $x->titulo)->implode("\n");
            $extra = $items->count() > 8 ? "\n… y " . ($items->count() - 8) . ' más' : '';
            $titulo = "Recordatorio: {$items->count()} incidencia(s) Alta vencida(s) — {$ciudad}";
            $mensaje = "El Superadmin solicita atención urgente en {$ciudad}:\n{$lista}{$extra}";

            $destIds = collect();
            if (Schema::hasTable('sucursal_responsables')) {
                $destIds = $destIds->merge(
                    DB::table('sucursal_responsables')->where('id_ciudad', $idCiudad)->pluck('id_usuario')
                );
            }
            if (Schema::hasTable('departamento_responsables') && Schema::hasColumn('departamento_responsables', 'id_ciudad')) {
                $destIds = $destIds->merge(
                    DB::table('departamento_responsables')->where('id_ciudad', $idCiudad)->pluck('id_usuario')
                );
            }
            $destIds = $destIds->merge(
                Usuario::where('activo', 1)->whereIn('rol', ['admin', 'encargado'])
                    ->where('id_ciudad', $idCiudad)->pluck('id_usuario')
            );
            $destIds = $destIds->unique()->filter()->values();

            foreach ($destIds as $uid) {
                try {
                    Notificacion::create([
                        'id_usuario'    => $uid,
                        'id_incidencia' => $items->first()->id_incidencia,
                        'titulo'        => $titulo,
                        'mensaje'       => $mensaje,
                        'leida'         => false,
                    ]);
                    $notificados++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return response()->json([
            'ok' => true,
            'mensaje' => "Recordatorios enviados ({$notificados} notificación/es) a responsables de sucursal.",
            'notificados' => $notificados,
            'sucursales' => $porCiudad->count(),
            'incidencias' => $vencidas->count(),
        ]);
    }

}
