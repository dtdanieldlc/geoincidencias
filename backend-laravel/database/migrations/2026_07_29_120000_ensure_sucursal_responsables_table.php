<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La migración original pudo marcarse como "ran" sin dejar la tabla
 * (fallos de FK / DROP parciales). Esta migración es idempotente:
 * crea sucursal_responsables solo si no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sucursal_responsables')) {
            return;
        }

        $colCiudad = DB::selectOne("SHOW COLUMNS FROM ciudades WHERE Field = 'id_ciudad'");
        $tipoCiudad = strtolower($colCiudad->Type ?? 'bigint unsigned');

        $colUsuario = DB::selectOne("SHOW COLUMNS FROM usuarios WHERE Field = 'id_usuario'");
        $tipoUsuario = strtolower($colUsuario->Type ?? 'bigint unsigned');

        Schema::create('sucursal_responsables', function (Blueprint $table) use ($tipoCiudad, $tipoUsuario) {
            $table->id('id_asignacion');

            if (str_contains($tipoCiudad, 'bigint')) {
                str_contains($tipoCiudad, 'unsigned')
                    ? $table->unsignedBigInteger('id_ciudad')
                    : $table->bigInteger('id_ciudad');
            } else {
                str_contains($tipoCiudad, 'unsigned')
                    ? $table->unsignedInteger('id_ciudad')
                    : $table->integer('id_ciudad');
            }

            if (str_contains($tipoUsuario, 'bigint')) {
                str_contains($tipoUsuario, 'unsigned')
                    ? $table->unsignedBigInteger('id_usuario')
                    : $table->bigInteger('id_usuario');
            } else {
                str_contains($tipoUsuario, 'unsigned')
                    ? $table->unsignedInteger('id_usuario')
                    : $table->integer('id_usuario');
            }

            $table->timestamps();
            $table->unique(['id_ciudad', 'id_usuario']);
        });

        try {
            Schema::table('sucursal_responsables', function (Blueprint $table) {
                $table->foreign('id_ciudad')
                    ->references('id_ciudad')->on('ciudades')
                    ->cascadeOnDelete();
                $table->foreign('id_usuario')
                    ->references('id_usuario')->on('usuarios')
                    ->cascadeOnDelete();
            });
        } catch (\Throwable $e) {
            // Tabla usable aunque la FK no se pueda crear (tipos raros en prod)
            report($e);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_responsables');
    }
};
