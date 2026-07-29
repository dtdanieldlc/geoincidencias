<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Sucursal a la que pertenece el usuario (ciudad del catálogo).
            // nullable para no romper usuarios ya existentes.
            $table->unsignedBigInteger('id_ciudad')->nullable()->after('telefono');
            $table->foreign('id_ciudad')->references('id_ciudad')->on('ciudades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['id_ciudad']);
            $table->dropColumn('id_ciudad');
        });
    }
};
