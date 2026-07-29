<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencia_evidencias', function (Blueprint $table) {
            $table->id('id_evidencia');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->cascadeOnDelete();

            // usuarios.id_usuario es INT normal, no BIGINT (ver notas de
            // migraciones anteriores) → integer() + foreign() a mano.
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');

            $table->string('tipo', 20); // imagen | documento | comentario
            $table->string('archivo_url')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->index('id_incidencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_evidencias');
    }
};
