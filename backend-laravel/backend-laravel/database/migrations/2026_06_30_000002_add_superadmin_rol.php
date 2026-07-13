<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Solo aplica en MySQL (producción). En SQLite (usado por los tests
        // automatizados) no existe ALTER ... MODIFY COLUMN, y no hace falta:
        // la migración base de 'usuarios' ya incluye 'superadmin' en el enum.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('superadmin','admin','usuario') NOT NULL DEFAULT 'usuario'");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin','usuario') NOT NULL DEFAULT 'usuario'");
    }
};
