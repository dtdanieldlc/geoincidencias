<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ampliar el ENUM para incluir superadmin
        DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('superadmin','admin','usuario') NOT NULL DEFAULT 'usuario'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin','usuario') NOT NULL DEFAULT 'usuario'");
    }
};
