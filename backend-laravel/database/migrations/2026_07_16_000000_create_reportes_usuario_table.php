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
            $table->foreignId('id_usuario_reportado')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_usuario_reportante')->constrained('usuarios', 'id_usuario');
            $table->string('motivo', 40); // acoso | spam | contenido_inapropiado | comportamiento_sospechoso | otro
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | revisado | descartado
            $table->timestamp('revisado_at')->nullable();
            $table->foreignId('id_admin_revisor')->nullable()->constrained('usuarios', 'id_usuario');
            $table->timestamps();

            $table->index('id_usuario_reportado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_usuario');
    }
};
