<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursal_responsables', function (Blueprint $table) {
            $table->id('id_asignacion');
            $table->foreignId('id_ciudad')->constrained('ciudades', 'id_ciudad'); // "sucursal" = ciudades en este proyecto

            // usuarios.id_usuario es un INT normal (no BIGINT), así que acá
            // se usa integer() + foreign() en vez de foreignId().
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');

            $table->timestamps();
            $table->unique(['id_ciudad', 'id_usuario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_responsables');
    }
};
