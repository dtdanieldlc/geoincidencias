<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Código de 6 dígitos enviado al correo
            $table->string('codigo_verificacion', 6)->nullable()->after('activo');
            // Fecha de expiración del código (válido 15 minutos)
            $table->timestamp('codigo_verificacion_expira')->nullable()->after('codigo_verificacion');
            // Si el correo fue verificado
            $table->boolean('correo_verificado')->default(false)->after('codigo_verificacion_expira');
            // Fecha en que se verificó
            $table->timestamp('correo_verificado_at')->nullable()->after('correo_verificado');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_verificacion',
                'codigo_verificacion_expira',
                'correo_verificado',
                'correo_verificado_at',
            ]);
        });
    }
};
