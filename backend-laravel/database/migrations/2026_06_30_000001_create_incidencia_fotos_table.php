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
            $table->foreignId('id_incidencia')->constrained('incidencias', 'id_incidencia')->onDelete('cascade');
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario')->onDelete('set null');
            $table->string('ruta', 255);
            $table->enum('tipo', ['antes', 'despues'])->default('antes');
            $table->timestamp('fecha')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_fotos');
    }
};
