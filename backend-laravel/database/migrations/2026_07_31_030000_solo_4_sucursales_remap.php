<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Fuerza solo 4 sucursales y remapea zonas, incidencias y encargados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ciudades')) {
            return;
        }

        $nombres = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];

        // Asegurar que existan
        $idProv = Schema::hasTable('provincias')
            ? (DB::table('provincias')->where('nombre', 'like', '%Santa Elena%')->value('id_provincia')
                ?? DB::table('provincias')->where('nombre', 'like', '%Pichincha%')->value('id_provincia')
                ?? DB::table('provincias')->value('id_provincia'))
            : null;

        foreach ($nombres as $nom) {
            $row = DB::table('ciudades')->where('nombre', $nom)->first();
            if (! $row && $idProv) {
                $data = ['nombre' => $nom, 'id_provincia' => $idProv];
                if (Schema::hasColumn('ciudades', 'activo')) {
                    $data['activo'] = 1;
                }
                // coords aproximadas
                $coords = [
                    'Salinas' => [-2.2145, -80.9510],
                    'La Libertad' => [-2.2333, -80.9100],
                    'Santa Elena' => [-2.2267, -80.8583],
                    'Quito' => [-0.1807, -78.4678],
                ];
                if (Schema::hasColumn('ciudades', 'latitud_ref') && isset($coords[$nom])) {
                    $data['latitud_ref'] = $coords[$nom][0];
                    $data['longitud_ref'] = $coords[$nom][1];
                }
                DB::table('ciudades')->insert($data);
            } elseif ($row && Schema::hasColumn('ciudades', 'activo')) {
                DB::table('ciudades')->where('id_ciudad', $row->id_ciudad)->update(['activo' => 1]);
            }
        }

        $idsOk = DB::table('ciudades')->whereIn('nombre', $nombres)->pluck('id_ciudad')->map(fn ($i) => (int) $i)->values()->all();
        if (empty($idsOk)) {
            return;
        }

        // Desactivar el resto y renombrar para que no confundan en listados viejos
        if (Schema::hasColumn('ciudades', 'activo')) {
            DB::table('ciudades')->whereNotIn('id_ciudad', $idsOk)->update(['activo' => 0]);
        }
        // Prefijo [INACTIVA] en nombre de las no permitidas (idempotente)
        $otrasCiudades = DB::table('ciudades')->whereNotIn('id_ciudad', $idsOk)->get(['id_ciudad', 'nombre']);
        foreach ($otrasCiudades as $oc) {
            $nom = (string) $oc->nombre;
            if (! str_starts_with($nom, '[INACTIVA]')) {
                DB::table('ciudades')->where('id_ciudad', $oc->id_ciudad)
                    ->update(['nombre' => '[INACTIVA] ' . $nom]);
            }
        }

        // Remapear zonas de otras ciudades → ciclo entre las 4
        if (Schema::hasTable('zonas')) {
            $otras = DB::table('zonas')->whereNotIn('id_ciudad', $idsOk)->get(['id_zona', 'id_ciudad']);
            $i = 0;
            $n = count($idsOk);
            foreach ($otras as $z) {
                $nuevo = $idsOk[$i % $n];
                DB::table('zonas')->where('id_zona', $z->id_zona)->update(['id_ciudad' => $nuevo]);
                $i++;
            }
        }

        // Usuarios con id_ciudad fuera de las 4 → reasignar
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'id_ciudad')) {
            $i = 0;
            $n = count($idsOk);
            $usuarios = DB::table('usuarios')
                ->whereNotNull('id_ciudad')
                ->whereNotIn('id_ciudad', $idsOk)
                ->get(['id_usuario']);
            foreach ($usuarios as $u) {
                DB::table('usuarios')->where('id_usuario', $u->id_usuario)
                    ->update(['id_ciudad' => $idsOk[$i % $n]]);
                $i++;
            }
        }

        // sucursal_responsables solo 4
        if (Schema::hasTable('sucursal_responsables')) {
            DB::table('sucursal_responsables')->whereNotIn('id_ciudad', $idsOk)->delete();
        }

        // departamento_responsables: limpiar id_ciudad inválido
        if (Schema::hasTable('departamento_responsables')
            && Schema::hasColumn('departamento_responsables', 'id_ciudad')) {
            DB::table('departamento_responsables')
                ->whereNotNull('id_ciudad')
                ->whereNotIn('id_ciudad', $idsOk)
                ->delete();
        }

        // Eliminar encargados cuyo correo no corresponda a las 4 sucursales
        if (Schema::hasTable('usuarios')) {
            $slugsOk = ['salinas', 'lalibertad', 'santaelena', 'quito'];
            $encargados = DB::table('usuarios')
                ->where('rol', 'encargado')
                ->where('correo', 'like', 'encargado.%@domuscenter.ec')
                ->get(['id_usuario', 'correo', 'id_ciudad']);
            foreach ($encargados as $e) {
                $correo = strtolower($e->correo ?? '');
                $ok = false;
                foreach ($slugsOk as $s) {
                    if (str_contains($correo, '.' . $s . '@')) {
                        $ok = true;
                        break;
                    }
                }
                // también válido si id_ciudad está en las 4
                if (! $ok && $e->id_ciudad && in_array((int) $e->id_ciudad, $idsOk, true)) {
                    $ok = true;
                }
                if (! $ok) {
                    if (Schema::hasTable('departamento_responsables')) {
                        DB::table('departamento_responsables')->where('id_usuario', $e->id_usuario)->delete();
                    }
                    DB::table('usuarios')->where('id_usuario', $e->id_usuario)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        // no-op
    }
};
