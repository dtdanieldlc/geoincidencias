<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id('id_zona');
            $table->foreignId('id_ciudad')->constrained('ciudades', 'id_ciudad');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('latitud_ref', 10, 6)->nullable();
            $table->decimal('longitud_ref', 10, 6)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
