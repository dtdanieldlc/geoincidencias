<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Asignaciones (responsable / apoyo) ──
        Schema::create('incidencia_asignaciones', function (Blueprint $table) {
            $table->id('id_asignacion');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->onDelete('cascade');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->enum('rol_asignacion', ['responsable', 'apoyo'])->default('responsable');
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->unique(['id_incidencia', 'id_usuario']);
        });

        // ── Apoyos voluntarios con incentivo económico ──
        Schema::create('incidencia_apoyos', function (Blueprint $table) {
            $table->id('id_apoyo');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->onDelete('cascade');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->decimal('monto_incentivo', 10, 2);
            $table->enum('estado_pago', ['pendiente_aprobacion', 'aprobado', 'rechazado', 'pagado'])->default('pendiente_aprobacion');
            $table->string('comentario_usuario', 255)->nullable();
            $table->foreignId('id_admin_revisor')->nullable()->constrained('usuarios', 'id_usuario');
            $table->string('comentario_admin', 255)->nullable();
            $table->timestamp('fecha_apoyo')->useCurrent();
            $table->timestamp('fecha_revision')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->unique(['id_incidencia', 'id_usuario']);
            $table->index('estado_pago');
        });

        // ── Historial de cambios de estado ──
        Schema::create('incidencia_estados_historial', function (Blueprint $table) {
            $table->id('id_historial');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->onDelete('cascade');
            $table->foreignId('id_estado_anterior')->nullable()->constrained('estados', 'id_estado');
            $table->foreignId('id_estado_nuevo')->constrained('estados', 'id_estado');
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario');
            $table->string('comentario', 255)->nullable();
            $table->timestamp('fecha_cambio')->useCurrent();
        });

        // ── Comentarios / seguimiento ──
        Schema::create('incidencia_comentarios', function (Blueprint $table) {
            $table->id('id_comentario');
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->onDelete('cascade');
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario');
            $table->text('comentario');
            $table->timestamp('fecha')->useCurrent();
        });

        // ── Notificaciones ──
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id('id_notificacion');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_incidencia')->nullable()->constrained('incidencias', 'id_incidencia')->onDelete('set null');
            $table->string('titulo', 150);
            $table->string('mensaje', 255)->nullable();
            $table->boolean('leida')->default(false);
            $table->timestamp('fecha')->useCurrent();
            $table->index(['id_usuario', 'leida']);
        });

        // ── Historial general de actividad (auditoría) ──
        Schema::create('historial_actividad', function (Blueprint $table) {
            $table->id('id_actividad');
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_incidencia')->nullable()->constrained('incidencias', 'id_incidencia')->onDelete('set null');
            $table->string('accion', 100);
            $table->string('detalle', 255)->nullable();
            $table->timestamp('fecha_hora')->useCurrent();
            $table->string('ip_origen', 45)->nullable();
            $table->index('fecha_hora');
            $table->index('id_usuario');
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_actividad');
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('incidencia_comentarios');
        Schema::dropIfExists('incidencia_estados_historial');
        Schema::dropIfExists('incidencia_apoyos');
        Schema::dropIfExists('incidencia_asignaciones');
    }
};
