<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id('id_departamento');
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('incidencias', function (Blueprint $table) {
            // ciudades.id_ciudad es BIGINT (creado con $table->id()), así que
            // acá sí se puede usar foreignId() sin problema de tipos.
            $table->foreignId('id_departamento')->nullable()->after('id_usuario_reportante')
                  ->constrained('departamentos', 'id_departamento');
        });
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropForeign(['id_departamento']);
            $table->dropColumn('id_departamento');
        });
        Schema::dropIfExists('departamentos');
    }
};
