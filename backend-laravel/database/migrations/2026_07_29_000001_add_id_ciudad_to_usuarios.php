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
                if (str_contains($tipoCiudad, 'unsigned')) {
                    $table->unsignedBigInteger('id_ciudad')->nullable()->after('telefono');
                } else {
                    $table->bigInteger('id_ciudad')->nullable()->after('telefono');
                }
            } else {
                if (str_contains($tipoCiudad, 'unsigned')) {
                    $table->unsignedInteger('id_ciudad')->nullable()->after('telefono');
                } else {
                    $table->integer('id_ciudad')->nullable()->after('telefono');
                }
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreign('id_ciudad')
                ->references('id_ciudad')->on('ciudades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('usuarios', 'id_ciudad')) {
            return;
        }
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['id_ciudad']);
            $table->dropColumn('id_ciudad');
        });
    }
};
