<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialActividad;
use App\Models\Usuario;
use App\Notifications\VerificarCorreoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/login
    // ──────────────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'   => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Correo y contraseña son obligatorios.'], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->where('activo', 1)->first();

        if (! $usuario || ! Hash::check($request->password, $usuario->password)) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales incorrectas.'], 401);
        }

        // Bloquear acceso si el correo no está verificado
        if (! $usuario->correo_verificado) {
            return response()->json([
                'ok'                 => false,
                'mensaje'            => 'Debes verificar tu correo electrónico antes de iniciar sesión.',
                'requiere_verificacion' => true,
                'correo'             => $usuario->correo,
            ], 403);
        }

        $token = $usuario->createToken('auth_token', ['*'], now()->addHours(8))->plainTextToken;

        $datosUsuario = [
            'id_usuario' => $usuario->id_usuario,
            'correo'     => $usuario->correo,
            'nombre'     => $usuario->nombre_completo,
            'rol'        => $usuario->rol,
        ];

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'login',
            "Usuario {$datosUsuario['nombre']} inició sesión", $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'token'   => $token,
            'usuario' => $datosUsuario,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/registro
    // ──────────────────────────────────────────────────────────────
    public function registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'   => 'required|string|max:100',
            'apellido' => 'nullable|string|max:100',
            'correo'   => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        // Generar código de 6 dígitos
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $usuario = Usuario::create([
            'nombre'                       => $request->nombre,
            'apellido'                     => $request->apellido,
            'correo'                       => $request->correo,
            'password'                     => Hash::make($request->password),
            'rol'                          => 'usuario',
            'telefono'                     => $request->telefono,
            'activo'                       => true,
            'correo_verificado'            => false,
            'codigo_verificacion'          => $codigo,
            'codigo_verificacion_expira'   => now()->addMinutes(15),
        ]);

        // Enviar correo con el código
        try {
            $usuario->notify(new VerificarCorreoNotification($codigo, $usuario->nombre));
        } catch (\Throwable $e) {
            // Si falla el envío, eliminar usuario para que pueda reintentar
            $usuario->delete();
            return response()->json([
                'ok'     => false,
                'mensaje' => 'No se pudo enviar el correo de verificación. Verifica que el correo sea válido e intenta de nuevo.',
            ], 422);
        }

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'registro_usuario',
            "Nuevo usuario registrado: {$usuario->nombre} ({$usuario->correo})", $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Cuenta creada. Te enviamos un código de 6 dígitos a tu correo para verificarla.',
            'correo'  => $usuario->correo,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/verificar-correo
    // ──────────────────────────────────────────────────────────────
    public function verificarCorreo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'codigo' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)
            ->where('correo_verificado', false)
            ->first();

        if (! $usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'Correo no encontrado o ya verificado.'], 404);
        }

        // Verificar expiración
        if (now()->isAfter($usuario->codigo_verificacion_expira)) {
            return response()->json([
                'ok'       => false,
                'mensaje'  => 'El código ha expirado. Solicita uno nuevo.',
                'expirado' => true,
            ], 400);
        }

        // Verificar código
        if ($usuario->codigo_verificacion !== $request->codigo) {
            return response()->json(['ok' => false, 'mensaje' => 'Código incorrecto.'], 400);
        }

        // Marcar como verificado
        $usuario->correo_verificado          = true;
        $usuario->correo_verificado_at       = now();
        $usuario->codigo_verificacion        = null;
        $usuario->codigo_verificacion_expira = null;
        $usuario->save();

        HistorialActividad::registrar(
            $usuario->id_usuario, null, 'verificar_correo',
            "Usuario verificó su correo: {$usuario->correo}", $request->ip()
        );

        return response()->json([
            'ok'      => true,
            'mensaje' => '¡Correo verificado! Ya puedes iniciar sesión.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/reenviar-codigo
    // ──────────────────────────────────────────────────────────────
    public function reenviarCodigo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)
            ->where('correo_verificado', false)
            ->first();

        if (! $usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'Correo no encontrado o ya verificado.'], 404);
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $usuario->codigo_verificacion        = $codigo;
        $usuario->codigo_verificacion_expira = now()->addMinutes(15);
        $usuario->save();

        try {
            $usuario->notify(new VerificarCorreoNotification($codigo, $usuario->nombre));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => 'No se pudo reenviar el correo. Intenta de nuevo.'], 422);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Nuevo código enviado a tu correo.']);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET /api/auth/perfil
    // ──────────────────────────────────────────────────────────────
    public function perfil(Request $request)
    {
        $usuario = $request->user();

        return response()->json([
            'id_usuario'         => $usuario->id_usuario,
            'nombre'             => $usuario->nombre,
            'apellido'           => $usuario->apellido,
            'correo'             => $usuario->correo,
            'rol'                => $usuario->rol,
            'telefono'           => $usuario->telefono,
            'saldo_incentivos'   => $usuario->saldo_incentivos,
            'created_at'         => $usuario->created_at,
            'correo_verificado'  => $usuario->correo_verificado,
            'foto_url'           => $usuario->foto_url ? Storage::url($usuario->foto_url) : null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/auth/perfil
    // ──────────────────────────────────────────────────────────────
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

        return response()->json(['ok' => true, 'mensaje' => 'Perfil actualizado correctamente.']);
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /api/auth/cambiar-password
    // ──────────────────────────────────────────────────────────────
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

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/foto
    // ──────────────────────────────────────────────────────────────
    public function subirFoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validator->errors()->first()], 400);
        }

        $usuario = $request->user();

        if ($usuario->foto_url) {
            Storage::disk('public')->delete($usuario->foto_url);
        }

        $ruta = $request->file('foto')->store('fotos_perfil', 'public');
        $usuario->foto_url = $ruta;
        $usuario->save();

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Foto actualizada correctamente.',
            'foto_url' => Storage::url($ruta),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /api/auth/logout
    // ──────────────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true, 'mensaje' => 'Sesión cerrada.']);
    }
}
