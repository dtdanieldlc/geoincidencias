<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id('id_conversacion');
            $table->foreignId('id_usuario_uno')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_usuario_dos')->constrained('usuarios', 'id_usuario');
            $table->text('ultimo_mensaje_texto')->nullable();
            $table->foreignId('ultimo_mensaje_id_usuario')->nullable()->constrained('usuarios', 'id_usuario');
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();

            $table->index(['id_usuario_uno', 'id_usuario_dos']);
        });

        Schema::create('mensajes', function (Blueprint $table) {
            $table->id('id_mensaje');
            $table->foreignId('id_conversacion')->constrained('conversaciones', 'id_conversacion')->cascadeOnDelete();
            $table->foreignId('id_usuario_emisor')->constrained('usuarios', 'id_usuario');
            $table->text('contenido');
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->index('id_conversacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
        Schema::dropIfExists('conversaciones');
    }
};
