<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Desactiva zonas/ciudades fuera de las 4 sucursales y limpia nombre Ballenita. */
return new class extends Migration
{
    public function up(): void
    {
        $permitidas = ['Salinas', 'La Libertad', 'Santa Elena', 'Quito'];
        if (! Schema::hasTable('ciudades')) {
            return;
        }
        $idsOk = DB::table('ciudades')->whereIn('nombre', $permitidas)->pluck('id_ciudad')->all();
        if (empty($idsOk)) {
            return;
        }

        if (Schema::hasColumn('ciudades', 'activo')) {
            DB::table('ciudades')->whereNotIn('id_ciudad', $idsOk)->update(['activo' => 0]);
        }
        DB::table('ciudades')->whereNotIn('id_ciudad', $idsOk)
            ->where('nombre', 'not like', '[INACTIVA]%')
            ->update(['nombre' => DB::raw("CONCAT('[INACTIVA] ', nombre)")]);

        if (Schema::hasTable('zonas')) {
            if (Schema::hasColumn('zonas', 'activo')) {
                DB::table('zonas')->whereNotIn('id_ciudad', $idsOk)->update(['activo' => 0]);
            }
            $zonas = DB::table('zonas')->where('nombre', 'like', '%Ballenita%')->get(['id_zona', 'nombre', 'id_ciudad']);
            foreach ($zonas as $z) {
                $nuevo = trim(str_ireplace(['- Ballenita', 'Ballenita', '  '], ['', '', ' '], $z->nombre));
                $nuevo = trim($nuevo, ' -');
                if ($nuevo === '') {
                    $nuevo = 'Área general';
                }
                $upd = ['nombre' => $nuevo];
                if (! in_array((int) $z->id_ciudad, array_map('intval', $idsOk), true) && Schema::hasColumn('zonas', 'activo')) {
                    $upd['activo'] = 0;
                }
                DB::table('zonas')->where('id_zona', $z->id_zona)->update($upd);
            }
        }
    }

    public function down(): void {}
};
