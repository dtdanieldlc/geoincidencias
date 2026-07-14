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
            $table->integer('id_usuario_uno');
            $table->integer('id_usuario_dos');
            $table->text('ultimo_mensaje_texto')->nullable();
            $table->integer('ultimo_mensaje_id_usuario')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();

            $table->foreign('id_usuario_uno')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_dos')->references('id_usuario')->on('usuarios');
            $table->foreign('ultimo_mensaje_id_usuario')->references('id_usuario')->on('usuarios');

            $table->index(['id_usuario_uno', 'id_usuario_dos']);
        });

        Schema::create('mensajes', function (Blueprint $table) {
            $table->id('id_mensaje');
            $table->foreignId('id_conversacion')->constrained('conversaciones', 'id_conversacion')->cascadeOnDelete();
            $table->integer('id_usuario_emisor');
            $table->foreign('id_usuario_emisor')->references('id_usuario')->on('usuarios');
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
