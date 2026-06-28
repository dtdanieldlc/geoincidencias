<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // POST /api/auth/login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Correo y contraseña son obligatorios.'], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->where('activo', 1)->first();

        if (! $usuario || ! Hash::check($request->password, $usuario->password)) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales incorrectas.'], 401);
        }

        $token = $usuario->createToken('auth_token', ['*'], now()->addHours(8))->plainTextToken;

        $datosUsuario = [
            'id_usuario' => $usuario->id_usuario,
            'correo' => $usuario->correo,
            'nombre' => $usuario->nombre_completo,
            'rol' => $usuario->rol,
        ];

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'login',
            "Usuario {$datosUsuario['nombre']} inició sesión", $request->ip()
        );

        return response()->json([
            'ok' => true,
            'token' => $token,
            'usuario' => $datosUsuario,
        ]);
    }

    // POST /api/auth/registro
    public function registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'apellido' => 'nullable|string|max:100',
            'correo' => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            $primerError = $validator->errors()->first();
            return response()->json(['ok' => false, 'mensaje' => $primerError], 400);
        }

        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo' => $request->correo,
            'password' => Hash::make($request->password),
            'rol' => 'usuario',
            'telefono' => $request->telefono,
        ]);

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'registro_usuario',
            "Nuevo usuario registrado: {$usuario->nombre} ({$usuario->correo})", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Cuenta creada correctamente. Ya puedes iniciar sesión.'], 201);
    }

    // GET /api/auth/perfil
    public function perfil(Request $request)
    {
        $usuario = $request->user();

        return response()->json([
            'id_usuario' => $usuario->id_usuario,
            'nombre' => $usuario->nombre,
            'apellido' => $usuario->apellido,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol,
            'telefono' => $usuario->telefono,
            'saldo_incentivos' => $usuario->saldo_incentivos,
            'created_at' => $usuario->created_at,
            'foto_url' => $usuario->foto_url ? Storage::url($usuario->foto_url) : null,
        ]);
    }

    // PUT /api/auth/perfil
    public function actualizarPerfil(Request $request)
    {
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'nombre'   => 'required|string|max:100',
            'apellido' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'correo'   => 'required|email|unique:usuarios,correo,' . $usuario->id_usuario . ',id_usuario',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario->nombre   = $request->nombre;
        $usuario->apellido = $request->apellido;
        $usuario->telefono = $request->telefono;
        $usuario->correo   = $request->correo;
        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'actualizar_perfil',
            "Usuario actualizó su perfil", $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Perfil actualizado correctamente.',
        ]);
    }

    // PUT /api/auth/cambiar-password
    public function cambiarPassword(Request $request)
    {
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'password_actual' => 'required|string',
            'password_nuevo'  => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        if (! Hash::check($request->password_actual, $usuario->password)) {
            return response()->json(['ok' => false, 'mensaje' => 'La contraseña actual es incorrecta.'], 400);
        }

        $usuario->password = Hash::make($request->password_nuevo);
        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'cambiar_password',
            "Usuario cambió su contraseña", $request->ip()
        );

        return response()->json(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
    }

    // POST /api/auth/foto
    public function subirFoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = $request->user();

        // Borra la foto anterior si existía
        if ($usuario->foto_url) {
            Storage::disk('public')->delete($usuario->foto_url);
        }

        $ruta = $request->file('foto')->store('fotos_perfil', 'public');
        $usuario->foto_url = $ruta;
        $usuario->save();

        return response()->json([
            'ok' => true,
            'mensaje' => 'Foto actualizada correctamente.',
            'foto_url' => Storage::url($ruta),
        ]);
    }

    // POST /api/auth/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true, 'mensaje' => 'Sesión cerrada.']);
    }
}