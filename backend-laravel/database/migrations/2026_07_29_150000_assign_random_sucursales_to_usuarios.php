<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asigna una sucursal (ciudad) aleatoria a todos los usuarios
 * que aún no tengan id_ciudad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuarios', 'id_ciudad')) {
            return;
        }

        $ciudades = DB::table('ciudades')->pluck('id_ciudad')->all();
        if (empty($ciudades)) {
            return;
        }

        $usuarios = DB::table('usuarios')
            ->whereNull('id_ciudad')
            ->pluck('id_usuario');

        foreach ($usuarios as $idUsuario) {
            $idCiudad = $ciudades[array_rand($ciudades)];
            DB::table('usuarios')
                ->where('id_usuario', $idUsuario)
                ->update(['id_ciudad' => $idCiudad]);
        }
    }

    public function down(): void
    {
        // No revertimos asignaciones aleatorias
    }
};
