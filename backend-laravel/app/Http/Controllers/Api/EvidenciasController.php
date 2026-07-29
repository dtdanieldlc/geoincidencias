<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Incidencia;
use App\Models\IncidenciaEvidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EvidenciasController extends Controller
{
    // GET /api/incidencias/{id}/evidencias
    public function index(int $id)
    {
        Incidencia::findOrFail($id);

        $lista = IncidenciaEvidencia::with('usuario:id_usuario,nombre,apellido')
            ->where('id_incidencia', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => [
                'id_evidencia' => $e->id_evidencia,
                'tipo'         => $e->tipo,
                'comentario'   => $e->comentario,
                'archivo_url'  => $e->archivo_url
                    ? (str_starts_with($e->archivo_url, 'http')
                        ? $e->archivo_url
                        : Storage::disk('public')->url($e->archivo_url))
                    : null,
                'usuario'      => $e->usuario
                    ? ['id_usuario' => $e->usuario->id_usuario, 'nombre' => trim($e->usuario->nombre . ' ' . ($e->usuario->apellido ?? ''))]
                    : null,
                'fecha'        => $e->created_at?->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'datos' => $lista]);
    }

    // POST /api/incidencias/{id}/evidencias
    // multipart: tipo (imagen|documento|comentario), comentario?, archivo?
    public function store(Request $request, int $id)
    {
        Incidencia::findOrFail($id);
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'tipo'       => 'required|in:imagen,documento,comentario',
            'comentario' => 'nullable|string|max:2000',
            'archivo'    => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $tipo = $request->tipo;
        if (in_array($tipo, ['imagen', 'documento']) && ! $request->hasFile('archivo')) {
            return response()->json(['ok' => false, 'mensaje' => 'Debes adjuntar un archivo para este tipo de evidencia.'], 400);
        }
        if ($tipo === 'comentario' && ! trim((string) $request->comentario)) {
            return response()->json(['ok' => false, 'mensaje' => 'El comentario es obligatorio.'], 400);
        }

        $ruta = null;
        if ($request->hasFile('archivo')) {
            $ruta = $request->file('archivo')->store('evidencias', 'public');
        }

        $evidencia = IncidenciaEvidencia::create([
            'id_incidencia' => $id,
            'id_usuario'    => $usuario->id_usuario,
            'tipo'          => $tipo,
            'archivo_url'   => $ruta,
            'comentario'    => $request->comentario,
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, $id, 'evidencia_incidencia',
            "{$usuario->nombre} agregó una evidencia ({$tipo}) en la incidencia #{$id}.",
            $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Evidencia registrada.',
            'data'    => [
                'id_evidencia' => $evidencia->id_evidencia,
                'tipo'         => $evidencia->tipo,
                'comentario'   => $evidencia->comentario,
                'archivo_url'  => $ruta ? Storage::disk('public')->url($ruta) : null,
                'fecha'        => $evidencia->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    // DELETE /api/incidencias/{id}/evidencias/{idEvidencia}
    public function destroy(Request $request, int $id, int $idEvidencia)
    {
        $usuario = $request->user();
        $evidencia = IncidenciaEvidencia::where('id_incidencia', $id)
            ->where('id_evidencia', $idEvidencia)
            ->first();

        if (! $evidencia) {
            return response()->json(['ok' => false, 'mensaje' => 'Evidencia no encontrada.'], 404);
        }

        $esAdmin = in_array($usuario->rol, ['admin', 'superadmin'], true);
        if (! $esAdmin && $evidencia->id_usuario !== $usuario->id_usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No tienes permiso para eliminar esta evidencia.'], 403);
        }

        if ($evidencia->archivo_url) {
            Storage::disk('public')->delete($evidencia->archivo_url);
        }
        $evidencia->delete();

        return response()->json(['ok' => true, 'mensaje' => 'Evidencia eliminada.']);
    }
}
