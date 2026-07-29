<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\HistorialActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartamentoController extends Controller
{
    // GET /api/departamentos  (cualquier autenticado, para poblar selects)
    public function index(Request $request)
    {
        $query = Departamento::query();
        if (! $request->boolean('todos')) {
            $query->where('activo', true);
        }
        return response()->json($query->orderBy('nombre')->get());
    }

    // POST /api/admin/departamentos
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $depto = Departamento::create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo'      => true,
        ]);

        HistorialActividad::registrar(
            $request->user()->id_usuario, null, 'crear_departamento',
            "Se creó el departamento \"{$depto->nombre}\"", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Departamento creado.', 'data' => $depto], 201);
    }

    // PUT /api/admin/departamentos/{id}
    public function update(Request $request, int $id)
    {
        $depto = Departamento::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $depto->update($request->only(['nombre', 'descripcion']));

        return response()->json(['ok' => true, 'mensaje' => 'Departamento actualizado.', 'data' => $depto]);
    }

    // PUT /api/admin/departamentos/{id}/activo
    public function toggleActivo(Request $request, int $id)
    {
        $depto = Departamento::findOrFail($id);
        $depto->activo = ! $depto->activo;
        $depto->save();

        return response()->json([
            'ok' => true,
            'mensaje' => $depto->activo ? 'Departamento activado.' : 'Departamento desactivado.',
            'data' => $depto,
        ]);
    }
}
