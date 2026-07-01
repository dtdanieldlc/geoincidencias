<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_permisos', function (Blueprint $table) {
            $table->id();
            $table->integer('id_admin_solicitante');   // admin que hace la solicitud
            $table->integer('id_usuario_objetivo');    // usuario al que se quiere dar permisos
            $table->json('permisos_solicitados');      // [{modulo, puede_ver, puede_editar, puede_eliminar}]
            $table->text('motivo');                    // por qué se solicitan esos permisos
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'modificado'])->default('pendiente');
            $table->text('respuesta_superadmin')->nullable(); // comentario del superadmin
            $table->json('permisos_aprobados')->nullable();   // puede diferir de los solicitados
            $table->integer('revisado_por')->nullable();      // id superadmin que revisó
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            $table->index('id_admin_solicitante');
            $table->index('id_usuario_objetivo');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_permisos');
    }
};
