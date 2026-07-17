<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            // Igual que en reportes_usuario: usuarios.id_usuario es un INT
            // normal, así que se usa integer() en vez de foreignId() para
            // que el tipo combine y no truene la llave foránea.
            $table->integer('id_usuario_reportante')->nullable()->after('reportante_contacto');
            $table->foreign('id_usuario_reportante')->references('id_usuario')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropForeign(['id_usuario_reportante']);
            $table->dropColumn('id_usuario_reportante');
        });
    }
};
