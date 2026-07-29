<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ciudad;
use App\Models\HistorialActividad;
use App\Models\SucursalResponsable;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SucursalResponsableController extends Controller
{
    // GET /api/admin/sucursales-responsables
    public function index()
    {
        try {
            $sucursales = Ciudad::query()
                ->orderBy('nombre')
                ->get(['id_ciudad', 'id_provincia', 'nombre', 'latitud_ref', 'longitud_ref']);

            $asignaciones = collect();
            if (Schema::hasTable('sucursal_responsables')) {
                $asignaciones = DB::table('sucursal_responsables as sr')
                    ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'sr.id_usuario')
                    ->select([
                        'sr.id_asignacion',
                        'sr.id_ciudad',
                        'sr.id_usuario',
                        'u.nombre',
                        'u.apellido',
                        'u.correo',
                        'u.rol',
                    ])
                    ->get();
            }

            $datos = $sucursales->map(function ($s) use ($asignaciones) {
                $responsables = $asignaciones->where('id_ciudad', $s->id_ciudad)->map(fn ($a) => [
                    'id_asignacion' => $a->id_asignacion,
                    'id_usuario'    => $a->id_usuario,
                    'nombre'        => trim(($a->nombre ?? '') . ' ' . ($a->apellido ?? '')) ?: 'Usuario eliminado',
                    'correo'        => $a->correo ?? null,
                    'rol'           => $a->rol ?? null,
                ])->values();

                return [
                    'id_ciudad'    => $s->id_ciudad,
                    'nombre'       => $s->nombre,
                    'ciudad'       => $s->nombre,
                    'provincia'    => null,
                    'latitud'      => $s->latitud_ref,
                    'longitud'     => $s->longitud_ref,
                    'responsables' => $responsables,
                ];
            });

            return response()->json($datos);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al listar sucursales: ' . $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/admin/sucursales-responsables/candidatos
    public function candidatos()
    {
        try {
            $lista = Usuario::where('activo', true)
                ->whereIn('rol', ['admin', 'superadmin'])
                ->orderBy('nombre')
                ->get(['id_usuario', 'nombre', 'apellido', 'correo', 'rol'])
                ->map(fn ($u) => [
                    'id_usuario' => $u->id_usuario,
                    'nombre'     => trim($u->nombre . ' ' . ($u->apellido ?? '')),
                    'correo'     => $u->correo,
                    'rol'        => $u->rol,
                ]);

            return response()->json($lista);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    // POST /api/superadmin/sucursales-responsables
    public function asignar(Request $request)
    {
        try {
            if (! Schema::hasTable('sucursal_responsables')) {
                return response()->json([
                    'ok'      => false,
                    'mensaje' => 'La tabla sucursal_responsables no existe. Ejecuta: php artisan migrate --force',
                ], 500);
            }

            $validator = Validator::make($request->all(), [
                'id_ciudad'  => 'required|integer|exists:ciudades,id_ciudad',
                'id_usuario' => 'required|integer|exists:usuarios,id_usuario',
            ]);
            if ($validator->fails()) {
                return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
            }

            $idCiudad  = (int) $request->id_ciudad;
            $idUsuario = (int) $request->id_usuario;

            $usuario = Usuario::find($idUsuario);
            if (! $usuario || ! in_array($usuario->rol, ['admin', 'superadmin'], true)) {
                return response()->json([
                    'ok'      => false,
                    'mensaje' => 'Solo se pueden asignar administradores como encargados de sucursal.',
                ], 400);
            }

            $yaExiste = DB::table('sucursal_responsables')
                ->where('id_ciudad', $idCiudad)
                ->where('id_usuario', $idUsuario)
                ->exists();
            if ($yaExiste) {
                return response()->json([
                    'ok'      => false,
                    'mensaje' => 'Ese administrador ya es responsable de esta sucursal.',
                ], 409);
            }

            $id = DB::table('sucursal_responsables')->insertGetId([
                'id_ciudad'  => $idCiudad,
                'id_usuario' => $idUsuario,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_asignacion');

            HistorialActividad::registrar(
                $request->user()->id_usuario,
                null,
                'asignar_responsable_sucursal',
                "Se asignó a {$usuario->nombre} como responsable de la sucursal #{$idCiudad}",
                $request->ip()
            );

            return response()->json([
                'ok'      => true,
                'mensaje' => 'Responsable asignado correctamente.',
                'data'    => [
                    'id_asignacion' => $id,
                    'id_ciudad'     => $idCiudad,
                    'id_usuario'    => $idUsuario,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al asignar: ' . $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /api/superadmin/sucursales-responsables/{idAsignacion}
    public function quitar(int $idAsignacion)
    {
        try {
            $deleted = DB::table('sucursal_responsables')
                ->where('id_asignacion', $idAsignacion)
                ->delete();

            if (! $deleted) {
                return response()->json(['ok' => false, 'mensaje' => 'Asignación no encontrada.'], 404);
            }

            return response()->json(['ok' => true, 'mensaje' => 'Responsable quitado.']);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al quitar: ' . $e->getMessage(),
            ], 500);
        }
    }
}
