<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si un intento anterior dejó la tabla a medias, la recreamos limpia.
        Schema::dropIfExists('sucursal_responsables');

        // Detectar el tipo real de ciudades.id_ciudad en esta BD
        // (en algunos entornos es INT, en otros BIGINT UNSIGNED).
        $colCiudad = DB::selectOne("SHOW COLUMNS FROM ciudades WHERE Field = 'id_ciudad'");
        $tipoCiudad = strtolower($colCiudad->Type ?? 'bigint unsigned');

        $colUsuario = DB::selectOne("SHOW COLUMNS FROM usuarios WHERE Field = 'id_usuario'");
        $tipoUsuario = strtolower($colUsuario->Type ?? 'int');

        Schema::create('sucursal_responsables', function (Blueprint $table) use ($tipoCiudad, $tipoUsuario) {
            $table->id('id_asignacion');

            // id_ciudad: mismo tipo que ciudades.id_ciudad
            if (str_contains($tipoCiudad, 'bigint')) {
                if (str_contains($tipoCiudad, 'unsigned')) {
                    $table->unsignedBigInteger('id_ciudad');
                } else {
                    $table->bigInteger('id_ciudad');
                }
            } else {
                // int / mediumint / etc.
                if (str_contains($tipoCiudad, 'unsigned')) {
                    $table->unsignedInteger('id_ciudad');
                } else {
                    $table->integer('id_ciudad');
                }
            }

            // id_usuario: mismo tipo que usuarios.id_usuario
            if (str_contains($tipoUsuario, 'bigint')) {
                if (str_contains($tipoUsuario, 'unsigned')) {
                    $table->unsignedBigInteger('id_usuario');
                } else {
                    $table->bigInteger('id_usuario');
                }
            } else {
                if (str_contains($tipoUsuario, 'unsigned')) {
                    $table->unsignedInteger('id_usuario');
                } else {
                    $table->integer('id_usuario');
                }
            }

            $table->timestamps();
            $table->unique(['id_ciudad', 'id_usuario']);
        });

        // FKs en un paso separado (más fácil de depurar si algo falla)
        Schema::table('sucursal_responsables', function (Blueprint $table) {
            $table->foreign('id_ciudad')
                ->references('id_ciudad')->on('ciudades')
                ->cascadeOnDelete();
            $table->foreign('id_usuario')
                ->references('id_usuario')->on('usuarios')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_responsables');
    }
};
