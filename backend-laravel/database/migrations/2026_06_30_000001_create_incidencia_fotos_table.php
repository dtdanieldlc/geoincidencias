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
            $table->integer('id_incidencia');
            $table->integer('id_usuario')->nullable();
            $table->string('ruta', 255);
            $table->enum('tipo', ['antes', 'despues'])->default('antes');
            $table->timestamp('fecha')->useCurrent();

            $table->index('id_incidencia');
            $table->index('id_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_fotos');
    }
};
