<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_usuario', function (Blueprint $table) {
            $table->id('id_reporte');

            // OJO: usuarios.id_usuario es un INT normal (no BIGINT), así que
            // no se puede usar foreignId() acá — crea BIGINT y MySQL rechaza
            // la llave foránea por incompatibilidad de tipos (error 3780).
            $table->integer('id_usuario_reportado');
            $table->integer('id_usuario_reportante');
            $table->string('motivo', 40); // acoso | spam | contenido_inapropiado | comportamiento_sospechoso | otro
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | revisado | descartado
            $table->timestamp('revisado_at')->nullable();
            $table->integer('id_admin_revisor')->nullable();
            $table->timestamps();

            $table->foreign('id_usuario_reportado')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_reportante')->references('id_usuario')->on('usuarios');
            $table->foreign('id_admin_revisor')->references('id_usuario')->on('usuarios');

            $table->index('id_usuario_reportado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_usuario');
    }
};
