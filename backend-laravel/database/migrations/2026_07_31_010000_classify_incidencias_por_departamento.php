<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asigna id_departamento a incidencias existentes según tipo y texto.
 * Así cada encargado ve su cola de trabajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incidencias') || ! Schema::hasTable('departamentos')) {
            return;
        }
        if (! Schema::hasColumn('incidencias', 'id_departamento')) {
            return;
        }

        $deptos = DB::table('departamentos')->get()->keyBy(function ($d) {
            return mb_strtolower($d->nombre);
        });

        $find = function (string $needle) use ($deptos) {
            $needle = mb_strtolower($needle);
            foreach ($deptos as $nombre => $d) {
                if (str_contains($nombre, $needle) || str_contains($needle, $nombre)) {
                    return $d->id_departamento;
                }
            }
            // búsqueda parcial por palabra
            foreach ($deptos as $nombre => $d) {
                foreach (preg_split('/\s+/', $needle) as $w) {
                    if (strlen($w) > 3 && str_contains($nombre, $w)) {
                        return $d->id_departamento;
                    }
                }
            }
            return null;
        };

        $mapTipo = [
            'equipos ti'           => 'tecnolog',
            'tecnolog'             => 'tecnolog',
            'red y conectividad'   => 'tecnolog',
            'infraestructura'      => 'mantenim',
            'servicios básicos'    => 'mantenim',
            'seguridad'            => 'seguridad',
            'accidentes'           => 'seguridad',
            'suministros'          => 'inventario',
        ];

        $rules = [
            ['dept' => 'tecnolog',   'keys' => ['pc','computador','laptop','impresora','software','sistema','red','internet','wifi','servidor','monitor','teclado','ti','tic','correo','email','router']],
            ['dept' => 'seguridad',  'keys' => ['robo','alarma','camara','cámara','seguridad','acceso','vandal','hurto','vigilanc','resbal']],
            ['dept' => 'mantenim',   'keys' => ['luz','alumbrado','gotera','filtraci','puerta','aire','plomer','tuber','electric','reparaci','averia','avería','techo','pared','grieta','mobiliario']],
            ['dept' => 'limpieza',   'keys' => ['limpieza','aseo','basura','baño','bano','higiene','desecho','sucio','piso mojado']],
            ['dept' => 'atención',   'keys' => ['cliente','queja','reclamo','atencion','atención','espera']],
            ['dept' => 'inventario', 'keys' => ['inventario','bodega','stock','mercader','pedido','logistica','logística','insumo','falta de']],
            ['dept' => 'recursos',   'keys' => ['personal','empleado','rrhh','ausent','conflicto laboral']],
            ['dept' => 'finanza',    'keys' => ['caja','pago','factura','arqueo','dinero','cobro','finanza','contab']],
            ['dept' => 'calidad',    'keys' => ['norma','auditor','calidad','protocolo','cumplimiento']],
            ['dept' => 'operacion',  'keys' => ['turno','operacion','operación','flujo','continuidad']],
        ];

        $incidencias = DB::table('incidencias as i')
            ->leftJoin('tipos_incidencia as ti', 'i.id_tipo', '=', 'ti.id_tipo')
            ->select('i.id_incidencia', 'i.titulo', 'i.descripcion', 'i.id_departamento', 'ti.nombre as tipo')
            ->get();

        $actualizadas = 0;
        foreach ($incidencias as $inc) {
            $idDept = null;
            $tipo = mb_strtolower((string) ($inc->tipo ?? ''));
            $texto = mb_strtolower(trim(($inc->titulo ?? '') . ' ' . ($inc->descripcion ?? '') . ' ' . $tipo));

            // 1) por tipo de incidencia
            foreach ($mapTipo as $frag => $deptKey) {
                if ($tipo !== '' && str_contains($tipo, $frag)) {
                    $idDept = $find($deptKey);
                    if ($idDept) break;
                }
            }

            // 2) por palabras clave en título/descripción
            if (! $idDept) {
                $bestScore = 0;
                $bestDept = null;
                foreach ($rules as $rule) {
                    $score = 0;
                    foreach ($rule['keys'] as $k) {
                        if (str_contains($texto, mb_strtolower($k))) {
                            $score++;
                        }
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestDept = $rule['dept'];
                    }
                }
                if ($bestDept && $bestScore > 0) {
                    $idDept = $find($bestDept);
                }
            }

            // 3) fallback por tipo genérico
            if (! $idDept && $tipo !== '') {
                if (str_contains($tipo, 'ti') || str_contains($tipo, 'red')) {
                    $idDept = $find('tecnolog');
                } elseif (str_contains($tipo, 'infra') || str_contains($tipo, 'servic')) {
                    $idDept = $find('mantenim');
                } elseif (str_contains($tipo, 'segur') || str_contains($tipo, 'accid')) {
                    $idDept = $find('seguridad');
                }
            }

            // 4) último recurso: Operaciones
            if (! $idDept) {
                $idDept = $find('operacion') ?? $find('mantenim') ?? $deptos->first()?->id_departamento;
            }

            if ($idDept && (int) ($inc->id_departamento ?? 0) !== (int) $idDept) {
                DB::table('incidencias')
                    ->where('id_incidencia', $inc->id_incidencia)
                    ->update(['id_departamento' => $idDept]);
                $actualizadas++;
            }
        }

        // Log en historial si existe
        try {
            if (Schema::hasTable('historial_actividad') && $actualizadas > 0) {
                DB::table('historial_actividad')->insert([
                    'id_usuario' => null,
                    'id_incidencia' => null,
                    'accion' => 'clasificar_departamentos',
                    'descripcion' => "Clasificación automática: {$actualizadas} incidencia(s) asignadas a departamentos",
                    'ip' => '127.0.0.1',
                    'fecha' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // no bloquear
        }
    }

    public function down(): void
    {
        // no revertimos clasificaciones
    }
};
