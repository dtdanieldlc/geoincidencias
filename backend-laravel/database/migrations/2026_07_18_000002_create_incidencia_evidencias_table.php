<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpia tabla a medias de un intento fallido anterior
        Schema::dropIfExists('incidencia_evidencias');

        $colInc = DB::selectOne("SHOW COLUMNS FROM incidencias WHERE Field = 'id_incidencia'");
        $tipoInc = strtolower($colInc->Type ?? 'bigint unsigned');

        $colUsu = DB::selectOne("SHOW COLUMNS FROM usuarios WHERE Field = 'id_usuario'");
        $tipoUsu = strtolower($colUsu->Type ?? 'int');

        Schema::create('incidencia_evidencias', function (Blueprint $table) use ($tipoInc, $tipoUsu) {
            $table->id('id_evidencia');

            // id_incidencia: mismo tipo que incidencias.id_incidencia
            if (str_contains($tipoInc, 'bigint')) {
                str_contains($tipoInc, 'unsigned')
                    ? $table->unsignedBigInteger('id_incidencia')
                    : $table->bigInteger('id_incidencia');
            } else {
                str_contains($tipoInc, 'unsigned')
                    ? $table->unsignedInteger('id_incidencia')
                    : $table->integer('id_incidencia');
            }

            // id_usuario: mismo tipo que usuarios.id_usuario
            if (str_contains($tipoUsu, 'bigint')) {
                str_contains($tipoUsu, 'unsigned')
                    ? $table->unsignedBigInteger('id_usuario')
                    : $table->bigInteger('id_usuario');
            } else {
                str_contains($tipoUsu, 'unsigned')
                    ? $table->unsignedInteger('id_usuario')
                    : $table->integer('id_usuario');
            }

            $table->string('tipo', 20); // imagen | documento | comentario
            $table->string('archivo_url')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();
            $table->index('id_incidencia');
        });

        Schema::table('incidencia_evidencias', function (Blueprint $table) {
            $table->foreign('id_incidencia')
                ->references('id_incidencia')->on('incidencias')
                ->cascadeOnDelete();
            $table->foreign('id_usuario')
                ->references('id_usuario')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_evidencias');
    }
};
