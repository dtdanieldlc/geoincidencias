<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_incidencia', function (Blueprint $table) {
            $table->id('id_tipo');
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('icono', 50)->nullable()->default('bi-tag');
            $table->string('color', 20)->nullable()->default('#6366f1');
            $table->boolean('activo')->default(true);
        });

        Schema::create('subtipos_incidencia', function (Blueprint $table) {
            $table->id('id_subtipo');
            $table->foreignId('id_tipo')->constrained('tipos_incidencia', 'id_tipo');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unique(['id_tipo', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtipos_incidencia');
        Schema::dropIfExists('tipos_incidencia');
    }
};
