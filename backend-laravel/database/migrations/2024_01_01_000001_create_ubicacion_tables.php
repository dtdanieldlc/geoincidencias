<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->id('id_pais');
            $table->string('nombre', 100)->unique();
            $table->string('codigo_iso', 5)->nullable();
        });

        Schema::create('provincias', function (Blueprint $table) {
            $table->id('id_provincia');
            $table->foreignId('id_pais')->constrained('paises', 'id_pais');
            $table->string('nombre', 100);
            $table->unique(['id_pais', 'nombre']);
        });

        Schema::create('ciudades', function (Blueprint $table) {
            $table->id('id_ciudad');
            $table->foreignId('id_provincia')->constrained('provincias', 'id_provincia');
            $table->string('nombre', 100);
            $table->decimal('latitud_ref', 10, 6)->nullable();
            $table->decimal('longitud_ref', 10, 6)->nullable();
            $table->unique(['id_provincia', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudades');
        Schema::dropIfExists('provincias');
        Schema::dropIfExists('paises');
    }
};
