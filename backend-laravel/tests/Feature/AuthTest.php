<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_backend_responde_en_health(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_login_correcto_devuelve_token(): void
    {
        Usuario::create([
            'nombre'   => 'Prueba',
            'apellido' => 'Uno',
            'correo'   => 'prueba@test.com',
            'password' => 'clave12345', // el cast 'hashed' lo encripta solo
            'rol'      => 'usuario',
            'activo'   => true,
        ]);

        $r = $this->postJson('/api/auth/login', [
            'correo'   => 'prueba@test.com',
            'password' => 'clave12345',
        ]);

        $r->assertOk()->assertJsonStructure(['ok', 'token', 'usuario']);
    }

    public function test_login_con_password_incorrecta_falla(): void
    {
        Usuario::create([
            'nombre'   => 'Prueba',
            'apellido' => 'Dos',
            'correo'   => 'prueba2@test.com',
            'password' => 'clave12345',
            'rol'      => 'usuario',
            'activo'   => true,
        ]);

        $r = $this->postJson('/api/auth/login', [
            'correo'   => 'prueba2@test.com',
            'password' => 'clave-equivocada',
        ]);

        $r->assertStatus(401);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        Usuario::create([
            'nombre'   => 'Inactivo',
            'apellido' => 'Test',
            'correo'   => 'inactivo@test.com',
            'password' => 'clave12345',
            'rol'      => 'usuario',
            'activo'   => false,
        ]);

        $r = $this->postJson('/api/auth/login', [
            'correo'   => 'inactivo@test.com',
            'password' => 'clave12345',
        ]);

        // El login excluye usuarios inactivos de la búsqueda, así que
        // recibe el mismo 401 genérico que una contraseña incorrecta
        // (a propósito: no revela si la cuenta existe pero está inactiva).
        $r->assertStatus(401);
    }
}
