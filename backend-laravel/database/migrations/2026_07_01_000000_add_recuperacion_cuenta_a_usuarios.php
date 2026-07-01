<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Dato que solo el dueño de la cuenta conoce (cédula ecuatoriana, 10 dígitos)
            $table->string('cedula', 10)->nullable()->unique()->after('telefono');

            // Pregunta de seguridad + respuesta (hasheada, igual que el password)
            $table->string('pregunta_secreta', 150)->nullable()->after('cedula');
            $table->string('respuesta_secreta', 255)->nullable()->after('pregunta_secreta');

            // Token temporal de un solo uso para el paso final del cambio de clave
            $table->string('reset_token', 64)->nullable()->after('respuesta_secreta');
            $table->timestamp('reset_token_expira')->nullable()->after('reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'cedula',
                'pregunta_secreta',
                'respuesta_secreta',
                'reset_token',
                'reset_token_expira',
            ]);
        });
    }
};
