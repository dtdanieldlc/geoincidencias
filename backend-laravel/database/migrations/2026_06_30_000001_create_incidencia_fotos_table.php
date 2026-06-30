<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencia_fotos', function (Blueprint $table) {
            $table->id('id_foto');
            $table->unsignedBigInteger('id_incidencia');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('ruta', 255);
            $table->enum('tipo', ['antes', 'despues'])->default('antes');
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('id_incidencia')->references('id_incidencia')->on('incidencias')->onDelete('cascade');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_fotos');
    }
};
