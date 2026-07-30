<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\DepartamentoResponsable;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DepartamentoResponsableController extends Controller
{
    public function index()
    {
        $deptos = Departamento::orderBy('nombre')->get();
        $asig = collect();
        if (Schema::hasTable('departamento_responsables')) {
            $asig = DB::table('departamento_responsables as dr')
                ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'dr.id_usuario')
                ->select('dr.id_asignacion', 'dr.id_departamento', 'dr.id_usuario', 'u.nombre', 'u.apellido', 'u.correo', 'u.rol')
                ->get();
        }

        $datos = $deptos->map(function ($d) use ($asig) {
            $resp = $asig->where('id_departamento', $d->id_departamento)->map(fn ($a) => [
                'id_asignacion' => $a->id_asignacion,
                'id_usuario'    => $a->id_usuario,
                'nombre'        => trim(($a->nombre ?? '') . ' ' . ($a->apellido ?? '')),
                'correo'        => $a->correo,
                'rol'           => $a->rol,
            ])->values();
            return [
                'id_departamento' => $d->id_departamento,
                'nombre'          => $d->nombre,
                'descripcion'     => $d->descripcion,
                'activo'          => $d->activo,
                'responsables'    => $resp,
            ];
        });

        return response()->json($datos);
    }

    public function asignar(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id_departamento' => 'required|integer|exists:departamentos,id_departamento',
            'id_usuario'      => 'required|integer|exists:usuarios,id_usuario',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $v->errors()->first()], 400);
        }

        $u = Usuario::find($request->id_usuario);
        if (! $u || ! in_array($u->rol, ['encargado', 'admin', 'superadmin'], true)) {
            // Si es usuario normal, promoverlo a encargado
            if ($u && $u->rol === 'usuario') {
                $u->update(['rol' => 'encargado']);
            } else {
                return response()->json(['ok' => false, 'mensaje' => 'El usuario debe existir.'], 400);
            }
        } elseif ($u->rol === 'usuario') {
            $u->update(['rol' => 'encargado']);
        }

        $ya = DepartamentoResponsable::where('id_departamento', $request->id_departamento)
            ->where('id_usuario', $request->id_usuario)->exists();
        if ($ya) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya es responsable de este departamento.'], 409);
        }

        $row = DepartamentoResponsable::create([
            'id_departamento' => $request->id_departamento,
            'id_usuario'      => $request->id_usuario,
        ]);

        HistorialActividad::registrar(
            $request->user()->id_usuario, null, 'asignar_responsable_departamento',
            "Asignó responsable al departamento #{$request->id_departamento}", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Encargado asignado al departamento.', 'data' => $row]);
    }

    public function quitar(int $id)
    {
        $deleted = DepartamentoResponsable::where('id_asignacion', $id)->delete();
        if (! $deleted) {
            return response()->json(['ok' => false, 'mensaje' => 'Asignación no encontrada.'], 404);
        }
        return response()->json(['ok' => true, 'mensaje' => 'Encargado quitado del departamento.']);
    }
}
