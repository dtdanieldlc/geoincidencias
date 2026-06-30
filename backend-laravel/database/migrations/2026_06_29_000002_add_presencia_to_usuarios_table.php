<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('ultima_presencia_at')->nullable()->after('correo_verificado_at');
            $table->string('ultima_pagina', 100)->nullable()->after('ultima_presencia_at');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['ultima_presencia_at', 'ultima_pagina']);
        });
    }
};
