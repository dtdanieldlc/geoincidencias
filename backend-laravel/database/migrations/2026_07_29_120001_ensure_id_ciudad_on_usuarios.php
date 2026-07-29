<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('usuarios', 'id_ciudad')) {
            return;
        }

        $colCiudad = DB::selectOne("SHOW COLUMNS FROM ciudades WHERE Field = 'id_ciudad'");
        $tipoCiudad = strtolower($colCiudad->Type ?? 'bigint unsigned');

        Schema::table('usuarios', function (Blueprint $table) use ($tipoCiudad) {
            if (str_contains($tipoCiudad, 'bigint')) {
                str_contains($tipoCiudad, 'unsigned')
                    ? $table->unsignedBigInteger('id_ciudad')->nullable()->after('telefono')
                    : $table->bigInteger('id_ciudad')->nullable()->after('telefono');
            } else {
                str_contains($tipoCiudad, 'unsigned')
                    ? $table->unsignedInteger('id_ciudad')->nullable()->after('telefono')
                    : $table->integer('id_ciudad')->nullable()->after('telefono');
            }
        });

        try {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->foreign('id_ciudad')
                    ->references('id_ciudad')->on('ciudades')
                    ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('usuarios', 'id_ciudad')) {
            return;
        }
        try {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropForeign(['id_ciudad']);
            });
        } catch (\Throwable $e) {
            // ignore
        }
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('id_ciudad');
        });
    }
};
