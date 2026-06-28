<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentivos_prioridad', function (Blueprint $table) {
            $table->id('id_incentivo');
            $table->enum('prioridad', ['Baja', 'Media', 'Alta'])->unique();
            $table->decimal('monto', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentivos_prioridad');
    }
};
