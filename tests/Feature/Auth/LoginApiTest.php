<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de POST /api/login (RF001).
 *
 * Lo consumen dos clientes: la app Flutter y el panel Angular. Si alguno de
 * estos tests falla, uno de los dos clientes deja de poder iniciar sesión.
 */
class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_productor_aprobado_recibe_el_par_de_tokens(): void
    {
        $user = $this->productor(status: 'approved');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'panel-angular',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_two_factor', false)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.role', 'productor')
            ->assertJsonPath('data.user.is_superadmin', false)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'is_superadmin', 'two_factor_enabled'],
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                    'expires_at',
                    'refresh_expires_in',
                    'refresh_expires_at',
                ],
            ]);

        $this->assertNotSame($response->json('data.access_token'), $response->json('data.refresh_token'));
    }

    /**
     * La app Flutter todavía lee `token` y `type`, los nombres de antes del RF004.
     * No borrar estos alias hasta que el móvil use access_token / token_type.
     */
    public function test_mantiene_los_alias_que_usa_flutter(): void
    {
        $user = $this->productor(status: 'approved');

        $data = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('data');

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertSame($data['access_token'], $data['token']);
        $this->assertSame('Bearer', $data['type']);

        // El alias debe ser un token utilizable, no sólo un campo con el mismo texto.
        $this->withToken($data['token'])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_device_name_es_opcional(): void
    {
        $user = $this->productor(status: 'approved');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        $this->assertStringStartsWith('api-client::', $user->tokens()->first()->name);
    }

    public function test_el_access_token_autentica_y_el_refresh_token_no(): void
    {
        $user = $this->productor(status: 'approved');

        $data = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data');

        $this->withToken($data['access_token'])->getJson('/api/me')->assertOk();

        // Sanctum cachea el usuario resuelto entre peticiones del mismo test.
        $this->app['auth']->forgetGuards();

        $this->withToken($data['refresh_token'])->getJson('/api/me')->assertForbidden();
    }

    public function test_credenciales_incorrectas_devuelven_401(): void
    {
        $user = $this->productor(status: 'approved');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'otra'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data.access_token');

        $this->postJson('/api/login', ['email' => 'nadie@example.com', 'password' => 'password'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_valida_los_campos_obligatorios(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_un_productor_sin_solicitud_aprobada_no_recibe_tokens(): void
    {
        $sinSolicitud = User::factory()->create();
        $pendiente = $this->productor(status: 'pending');
        $denegado = $this->productor(status: 'denied');

        foreach ([$sinSolicitud, $pendiente, $denegado] as $user) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
                ->assertForbidden()
                ->assertJsonPath('success', false);
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_el_superadmin_recibe_un_reto_de_doble_factor_y_no_tokens(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);

        $response = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_two_factor', true)
            ->assertJsonPath('data.two_factor_enrolled', false)
            ->assertJsonStructure(['data' => ['challenge_token', 'expires_in']])
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.token');

        // El challenge_token no sirve para autenticarse contra la API.
        $this->withToken($response->json('data.challenge_token'))
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $user = $this->productor(status: 'approved');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'otra'])
                ->assertUnauthorized();
        }

        // Ni siquiera la contraseña correcta pasa mientras dure el bloqueo.
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertTooManyRequests();
    }

    /**
     * RF006: la API se autentica sólo por token Bearer. Una sesión web de Laravel
     * no debe dar acceso a /api/*, porque el panel Angular no usa el modo SPA/cookie.
     */
    public function test_una_sesion_web_no_autentica_contra_la_api(): void
    {
        $user = $this->productor(status: 'approved');

        $this->actingAs($user, 'web')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    private function productor(string $status): User
    {
        $user = User::factory()->create();

        AccessRequest::create([
            'firstname' => 'Ana',
            'lastname' => 'Pérez',
            'id_type' => 'CC',
            'id_number' => fake()->unique()->numerify('##########'),
            'landname' => 'Finca de prueba',
            'country' => 'Colombia',
            'department' => 'Norte de Santander',
            'city' => 'Ocaña',
            'email' => $user->email,
            'status' => $status,
        ]);

        return $user;
    }
}
