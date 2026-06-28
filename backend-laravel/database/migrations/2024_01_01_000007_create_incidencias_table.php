<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id('id_incidencia');

            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->enum('prioridad', ['Baja', 'Media', 'Alta'])->default('Media');

            $table->foreignId('id_tipo')->constrained('tipos_incidencia', 'id_tipo');
            $table->foreignId('id_subtipo')->nullable()->constrained('subtipos_incidencia', 'id_subtipo');
            $table->foreignId('id_estado_actual')->constrained('estados', 'id_estado');

            $table->enum('estado_aprobacion', ['pendiente_revision', 'aprobada', 'rechazada'])->default('pendiente_revision');
            $table->foreignId('id_admin_revisor')->nullable()->constrained('usuarios', 'id_usuario');
            $table->timestamp('fecha_revision')->nullable();
            $table->string('motivo_rechazo', 255)->nullable();

            $table->foreignId('id_zona')->constrained('zonas', 'id_zona');
            $table->decimal('latitud', 10, 6)->nullable();
            $table->decimal('longitud', 10, 6)->nullable();
            $table->string('direccion_texto', 255)->nullable();

            $table->date('fecha_ocurrencia');
            $table->time('hora_ocurrencia')->nullable();
            $table->dateTime('fecha_resolucion')->nullable();
            $table->decimal('tiempo_resolucion_horas', 10, 2)->nullable();

            $table->string('reportante_nombre', 100)->nullable();
            $table->string('reportante_contacto', 150)->nullable();

            $table->foreignId('id_usuario_creador')->nullable()->constrained('usuarios', 'id_usuario');
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamp('fecha_actualizacion')->useCurrent()->useCurrentOnUpdate();

            $table->index('id_estado_actual');
            $table->index('id_tipo');
            $table->index('id_zona');
            $table->index('fecha_ocurrencia');
            $table->index('prioridad');
            $table->index('estado_aprobacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
