<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permisos', function (Blueprint $table) {
            $table->id();
            $table->integer('id_usuario');          // admin que tiene los permisos
            $table->string('modulo', 50);           // dashboard, incidencias, usuarios, etc.
            $table->boolean('puede_ver')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_eliminar')->default(false);
            $table->integer('otorgado_por');        // id del superadmin que otorgó
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            $table->unique(['id_usuario', 'modulo']); // un registro por usuario+modulo
            $table->index('id_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permisos');
    }
};
