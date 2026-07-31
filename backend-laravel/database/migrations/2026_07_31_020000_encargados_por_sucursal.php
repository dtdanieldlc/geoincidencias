<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * - Deja activas solo 4 sucursales: Salinas, La Libertad, Santa Elena, Quito
 * - departamento_responsables.id_ciudad (encargado por depto × sucursal)
 * - Crea cuentas de encargado para cada departamento en cada sucursal
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1) Asegurar 4 sucursales ─────────────────────────────
        if (Schema::hasTable('ciudades')) {
            $nombres = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];
            $idProv = null;
            if (Schema::hasTable('provincias')) {
                $idProv = DB::table('provincias')->where('nombre', 'like', '%Santa Elena%')->value('id_provincia')
                    ?? DB::table('provincias')->where('nombre', 'like', '%Pichincha%')->value('id_provincia')
                    ?? DB::table('provincias')->value('id_provincia');
            }
            foreach ($nombres as $nom) {
                $existe = DB::table('ciudades')->where('nombre', $nom)->first();
                if (! $existe && $idProv) {
                    $row = ['nombre' => $nom, 'id_provincia' => $idProv];
                    if (Schema::hasColumn('ciudades', 'activo')) {
                        $row['activo'] = 1;
                    }
                    DB::table('ciudades')->insert($row);
                } elseif ($existe && Schema::hasColumn('ciudades', 'activo')) {
                    DB::table('ciudades')->where('id_ciudad', $existe->id_ciudad)->update(['activo' => 1]);
                }
            }
            // Desactivar otras si hay columna activo
            if (Schema::hasColumn('ciudades', 'activo')) {
                DB::table('ciudades')->whereNotIn('nombre', $nombres)->update(['activo' => 0]);
            }
        }

        // ── 2) id_ciudad en departamento_responsables ────────────
        if (Schema::hasTable('departamento_responsables')
            && ! Schema::hasColumn('departamento_responsables', 'id_ciudad')) {
            Schema::table('departamento_responsables', function (Blueprint $table) {
                $table->unsignedBigInteger('id_ciudad')->nullable()->after('id_departamento');
                $table->index(['id_usuario', 'id_departamento', 'id_ciudad'], 'dr_user_dept_ciudad');
            });
        }

        if (! Schema::hasTable('usuarios') || ! Schema::hasTable('departamentos')) {
            return;
        }

        $ciudades = DB::table('ciudades')
            ->whereIn('nombre', ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'])
            ->get(['id_ciudad', 'nombre']);
        $departamentos = DB::table('departamentos')->get(['id_departamento', 'nombre']);
        if ($ciudades->isEmpty() || $departamentos->isEmpty()) {
            return;
        }

        $slug = function (string $s): string {
            $s = mb_strtolower($s);
            $s = strtr($s, [
                'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
                'ü'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u',
            ]);
            $s = preg_replace('/[^a-z0-9]+/', '', $s);
            return $s ?: 'depto';
        };

        // Eliminar encargados viejos genéricos (sin sucursal en correo)
        try {
            DB::table('departamento_responsables')->delete();
            DB::table('usuarios')
                ->where('rol', 'encargado')
                ->where('correo', 'like', 'encargado.%@domuscenter.ec')
                ->delete();
        } catch (\Throwable $e) {
            // continuar
        }

        $password = Hash::make('Encargado123!');

        foreach ($ciudades as $ciudad) {
            $slugCiudad = $slug($ciudad->nombre);
            foreach ($departamentos as $dept) {
                $slugDept = $slug($dept->nombre);
                // acortar si es muy largo
                if (strlen($slugDept) > 18) {
                    $slugDept = substr($slugDept, 0, 18);
                }
                $correo = "encargado.{$slugDept}.{$slugCiudad}@domuscenter.ec";

                $uid = DB::table('usuarios')->where('correo', $correo)->value('id_usuario');
                if (! $uid) {
                    $data = [
                        'nombre'   => 'Encargado',
                        'apellido' => $dept->nombre . ' · ' . $ciudad->nombre,
                        'correo'   => $correo,
                        'password' => $password,
                        'rol'      => 'encargado',
                        'activo'   => 1,
                    ];
                    if (Schema::hasColumn('usuarios', 'id_ciudad')) {
                        $data['id_ciudad'] = $ciudad->id_ciudad;
                    }
                    if (Schema::hasColumn('usuarios', 'correo_verificado')) {
                        $data['correo_verificado'] = 1;
                    }
                    $uid = DB::table('usuarios')->insertGetId($data);
                } else {
                    $upd = [
                        'apellido' => $dept->nombre . ' · ' . $ciudad->nombre,
                        'rol'      => 'encargado',
                        'activo'   => 1,
                        'password' => $password,
                    ];
                    if (Schema::hasColumn('usuarios', 'id_ciudad')) {
                        $upd['id_ciudad'] = $ciudad->id_ciudad;
                    }
                    DB::table('usuarios')->where('id_usuario', $uid)->update($upd);
                }

                $exists = DB::table('departamento_responsables')
                    ->where('id_usuario', $uid)
                    ->where('id_departamento', $dept->id_departamento)
                    ->when(
                        Schema::hasColumn('departamento_responsables', 'id_ciudad'),
                        fn ($q) => $q->where('id_ciudad', $ciudad->id_ciudad)
                    )
                    ->exists();
                if (! $exists) {
                    $row = [
                        'id_usuario'      => $uid,
                        'id_departamento' => $dept->id_departamento,
                    ];
                    if (Schema::hasColumn('departamento_responsables', 'id_ciudad')) {
                        $row['id_ciudad'] = $ciudad->id_ciudad;
                    }
                    DB::table('departamento_responsables')->insert($row);
                }
            }
        }
    }

    public function down(): void
    {
        // no-op
    }
};
