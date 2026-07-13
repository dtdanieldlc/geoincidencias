<?php

namespace Tests\Feature;

use App\Models\AdminPermiso;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Estos tests existen porque en algún momento varias rutas (historial,
 * incidencias pendientes, exportar CSV) solo verificaban el ROL del
 * usuario ("¿es admin?") pero no el permiso granular que el superadmin
 * le había asignado — un admin al que se le quitaba el acceso a un
 * módulo seguía pudiendo usarlo igual. Este archivo prueba que, una vez
 * corregido, revocar un permiso realmente bloquea el acceso.
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Admin',
            'apellido' => 'Prueba',
            'correo'   => 'admin.prueba@test.com',
            'password' => 'clave12345',
            'rol'      => 'admin',
            'activo'   => true,
        ]);
    }

    public function test_admin_sin_permiso_no_puede_ver_historial(): void
    {
        $admin = $this->crearAdmin();
        Sanctum::actingAs($admin);

        // Sin ninguna fila en admin_permisos para 'historial': debe bloquear.
        $this->getJson('/api/historial')->assertStatus(403);
    }

    public function test_admin_con_permiso_otorgado_si_puede_ver_historial(): void
    {
        $admin = $this->crearAdmin();

        AdminPermiso::create([
            'id_usuario'    => $admin->id_usuario,
            'modulo'        => 'historial',
            'puede_ver'     => true,
            'puede_editar'  => false,
            'puede_eliminar'=> false,
            'otorgado_por'  => $admin->id_usuario,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/historial')->assertOk();
    }

    public function test_revocar_el_permiso_bloquea_el_acceso_de_inmediato(): void
    {
        $admin = $this->crearAdmin();

        $permiso = AdminPermiso::create([
            'id_usuario'    => $admin->id_usuario,
            'modulo'        => 'incidencias',
            'puede_ver'     => true,
            'puede_editar'  => false,
            'puede_eliminar'=> false,
            'otorgado_por'  => $admin->id_usuario,
        ]);

        Sanctum::actingAs($admin);

        // Con el permiso: puede ver las incidencias pendientes de aprobación.
        $this->getJson('/api/incidencias/pendientes-aprobacion')->assertOk();

        // El superadmin le quita el permiso (como si lo hiciera desde el panel)...
        $permiso->update(['puede_ver' => false]);

        // ...y el acceso se corta de inmediato, sin necesidad de cerrar sesión.
        $this->getJson('/api/incidencias/pendientes-aprobacion')->assertStatus(403);
    }

    public function test_superadmin_siempre_tiene_acceso_sin_necesidad_de_permisos(): void
    {
        $superadmin = Usuario::create([
            'nombre'   => 'Super',
            'apellido' => 'Admin',
            'correo'   => 'super.prueba@test.com',
            'password' => 'clave12345',
            'rol'      => 'superadmin',
            'activo'   => true,
        ]);

        Sanctum::actingAs($superadmin);

        // Sin ninguna fila en admin_permisos: el superadmin igual entra.
        $this->getJson('/api/historial')->assertOk();
    }

    public function test_usuario_regular_no_puede_acceder_a_rutas_de_admin(): void
    {
        $usuario = Usuario::create([
            'nombre'   => 'Usuario',
            'apellido' => 'Normal',
            'correo'   => 'usuario.prueba@test.com',
            'password' => 'clave12345',
            'rol'      => 'usuario',
            'activo'   => true,
        ]);

        Sanctum::actingAs($usuario);
        $this->getJson('/api/historial')->assertStatus(403);
    }
}
