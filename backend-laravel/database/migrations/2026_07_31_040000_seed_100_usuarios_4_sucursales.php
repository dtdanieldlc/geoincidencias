<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Asegura ~25 usuarios por cada una de las 4 sucursales (~100 en total).
 * Mezcla: usuarios finales + admins de sede (encargados ya existen por depto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuarios') || ! Schema::hasTable('ciudades')) {
            return;
        }

        $nombresSuc = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];
        $ciudades = DB::table('ciudades')->whereIn('nombre', $nombresSuc)->get(['id_ciudad', 'nombre']);
        if ($ciudades->count() < 4) {
            // Intentar crear faltantes
            $idProv = Schema::hasTable('provincias')
                ? (DB::table('provincias')->value('id_provincia'))
                : null;
            foreach ($nombresSuc as $nom) {
                if (! DB::table('ciudades')->where('nombre', $nom)->exists() && $idProv) {
                    $row = ['nombre' => $nom, 'id_provincia' => $idProv];
                    if (Schema::hasColumn('ciudades', 'activo')) {
                        $row['activo'] = 1;
                    }
                    DB::table('ciudades')->insert($row);
                }
            }
            $ciudades = DB::table('ciudades')->whereIn('nombre', $nombresSuc)->get(['id_ciudad', 'nombre']);
        }

        $nombres = [
            'Ana', 'Luis', 'María', 'Carlos', 'Diana', 'Pedro', 'Sofía', 'Jorge', 'Elena', 'Miguel',
            'Laura', 'Andrés', 'Paula', 'Diego', 'Carmen', 'José', 'Lucía', 'Fernando', 'Valeria', 'Ricardo',
            'Gabriela', 'Héctor', 'Patricia', 'Raúl', 'Monica', 'Felipe', 'Andrea', 'Sergio', 'Natalia', 'Iván',
        ];
        $apellidos = [
            'García', 'Rodríguez', 'Martínez', 'López', 'González', 'Pérez', 'Sánchez', 'Ramírez',
            'Torres', 'Flores', 'Rivera', 'Gómez', 'Díaz', 'Cruz', 'Morales', 'Reyes', 'Ortiz', 'Vargas',
        ];

        $passwordUser = Hash::make('Usuario123!');
        $passwordAdmin = Hash::make('Admin123!');

        $slug = function (string $s): string {
            $s = mb_strtolower($s);
            $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
            return preg_replace('/[^a-z0-9]+/', '', $s) ?: 'x';
        };

        foreach ($ciudades as $ciudad) {
            $idCiudad = (int) $ciudad->id_ciudad;
            $slugC = $slug($ciudad->nombre);

            // Contar usuarios actuales de esta sucursal
            $countQ = DB::table('usuarios')->where('activo', 1);
            if (Schema::hasColumn('usuarios', 'id_ciudad')) {
                $countQ->where('id_ciudad', $idCiudad);
            }
            $actual = (int) $countQ->count();
            $faltan = max(0, 25 - $actual);
            if ($faltan === 0) {
                continue;
            }

            // 2 admins por sucursal si no hay
            $adminsActuales = 0;
            if (Schema::hasColumn('usuarios', 'id_ciudad')) {
                $adminsActuales = (int) DB::table('usuarios')
                    ->where('rol', 'admin')
                    ->where('id_ciudad', $idCiudad)
                    ->count();
            }
            $adminsCrear = max(0, min(2, 2 - $adminsActuales));

            for ($i = 0; $i < $faltan; $i++) {
                $esAdmin = $i < $adminsCrear;
                $nom = $nombres[($actual + $i) % count($nombres)];
                $ape = $apellidos[($actual + $i * 3) % count($apellidos)];
                $n = $actual + $i + 1;
                $correo = $esAdmin
                    ? sprintf('admin.%s.%02d@domuscenter.ec', $slugC, $n)
                    : sprintf('usuario.%s.%02d@domuscenter.ec', $slugC, $n);

                if (DB::table('usuarios')->where('correo', $correo)->exists()) {
                    // variante
                    $correo = str_replace('@', ".{$idCiudad}@", $correo);
                    if (DB::table('usuarios')->where('correo', $correo)->exists()) {
                        continue;
                    }
                }

                $data = [
                    'nombre'   => $esAdmin ? 'Admin' : $nom,
                    'apellido' => $esAdmin ? ($ciudad->nombre . ' ' . $n) : ($ape . ' ' . $ciudad->nombre),
                    'correo'   => $correo,
                    'password' => $esAdmin ? $passwordAdmin : $passwordUser,
                    'rol'      => $esAdmin ? 'admin' : 'usuario',
                    'activo'   => 1,
                ];
                if (Schema::hasColumn('usuarios', 'id_ciudad')) {
                    $data['id_ciudad'] = $idCiudad;
                }
                if (Schema::hasColumn('usuarios', 'correo_verificado')) {
                    $data['correo_verificado'] = 1;
                }
                if (Schema::hasColumn('usuarios', 'telefono')) {
                    $data['telefono'] = '09' . str_pad((string) (800000000 + $idCiudad * 100 + $n), 8, '0', STR_PAD_LEFT);
                }

                try {
                    $uid = DB::table('usuarios')->insertGetId($data);
                } catch (\Throwable $e) {
                    continue;
                }

                // Vincular admin a sucursal_responsables
                if ($esAdmin && Schema::hasTable('sucursal_responsables')) {
                    $exists = DB::table('sucursal_responsables')
                        ->where('id_usuario', $uid)
                        ->where('id_ciudad', $idCiudad)
                        ->exists();
                    if (! $exists) {
                        try {
                            DB::table('sucursal_responsables')->insert([
                                'id_usuario' => $uid,
                                'id_ciudad'  => $idCiudad,
                            ]);
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // no borra datos de prueba automáticamente
    }
};
