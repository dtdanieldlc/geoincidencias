<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Rol "encargado": personal de un departamento (TI, Mantenimiento, etc.)
 * que recibe y resuelve incidencias de su área.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ampliar enum de roles
        try {
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('superadmin','admin','usuario','encargado') NOT NULL DEFAULT 'usuario'");
        } catch (\Throwable $e) {
            // Algunos motores / versiones: intentar con check distinto
            report($e);
        }

        if (! Schema::hasTable('departamento_responsables')) {
            Schema::create('departamento_responsables', function (Blueprint $table) {
                $table->id('id_asignacion');
                $table->unsignedBigInteger('id_departamento');
                $table->unsignedBigInteger('id_usuario');
                $table->timestamps();
                $table->unique(['id_departamento', 'id_usuario']);
            });

            // FKs tolerantes a tipos
            try {
                Schema::table('departamento_responsables', function (Blueprint $table) {
                    $table->foreign('id_departamento')
                        ->references('id_departamento')->on('departamentos')
                        ->cascadeOnDelete();
                    $table->foreign('id_usuario')
                        ->references('id_usuario')->on('usuarios')
                        ->cascadeOnDelete();
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Crear un encargado por departamento existente + asignarlo
        $departamentos = DB::table('departamentos')->where('activo', true)->get();
        $password = Hash::make('Encargado123!');

        foreach ($departamentos as $d) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $d->nombre) ?: $d->nombre));
            $slug = substr($slug ?: 'depto', 0, 20);
            $correo = "encargado.{$slug}@domuscenter.local";

            $existe = DB::table('usuarios')->where('correo', $correo)->first();
            if ($existe) {
                $idUsuario = $existe->id_usuario;
                DB::table('usuarios')->where('id_usuario', $idUsuario)->update(['rol' => 'encargado']);
            } else {
                $idUsuario = DB::table('usuarios')->insertGetId([
                    'nombre'            => 'Encargado',
                    'apellido'          => $d->nombre,
                    'correo'            => $correo,
                    'password'          => $password,
                    'rol'               => 'encargado',
                    'activo'            => 1,
                    'correo_verificado' => 1,
                    'created_at'        => now(),
                ], 'id_usuario');
            }

            $ya = DB::table('departamento_responsables')
                ->where('id_departamento', $d->id_departamento)
                ->where('id_usuario', $idUsuario)
                ->exists();
            if (! $ya) {
                DB::table('departamento_responsables')->insert([
                    'id_departamento' => $d->id_departamento,
                    'id_usuario'      => $idUsuario,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_responsables');
        try {
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('superadmin','admin','usuario') NOT NULL DEFAULT 'usuario'");
        } catch (\Throwable $e) {
            report($e);
        }
    }
};
