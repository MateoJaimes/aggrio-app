<?php

namespace Tests\Feature\Iot;

use App\Enums\UserRole;
use App\Models\DispositivoIot;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registro de dispositivos IoT y sus API keys desde el panel Superadmin (RF002).
 */
class DispositivoIotAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_superadmin_registra_un_dispositivo_y_recibe_la_key_una_vez(): void
    {
        $this->comoSuperadmin();
        $lote = $this->lote();

        $response = $this->postJson('/api/admin/dispositivos-iot', [
            'lote_id' => $lote->id,
            'nombre' => 'ESP32 norte',
            'mac_dispositivo' => 'AA:BB:CC:DD:EE:01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.dispositivo.lote_id', $lote->id)
            ->assertJsonPath('data.dispositivo.activo', true)
            ->assertJsonMissingPath('data.dispositivo.api_key_hash');

        $key = $response->json('data.api_key');
        $this->assertStringStartsWith('iot_', $key);

        // En la base sólo queda el hash, nunca la key en claro.
        $this->assertDatabaseMissing('dispositivos_iot', ['api_key_hash' => $key]);

        // La key no vuelve a aparecer al consultar el dispositivo.
        $id = $response->json('data.dispositivo.id');
        $this->getJson("/api/admin/dispositivos-iot/{$id}")
            ->assertOk()
            ->assertJsonMissingPath('data.api_key')
            ->assertJsonMissingPath('data.api_key_hash');
    }

    public function test_la_key_entregada_sirve_para_enviar_lecturas(): void
    {
        $this->comoSuperadmin();
        $lote = $this->lote();

        $key = $this->postJson('/api/admin/dispositivos-iot', ['lote_id' => $lote->id, 'nombre' => 'ESP32'])
            ->json('data.api_key');

        $this->withHeader(DispositivoIot::KEY_HEADER, $key)
            ->postJson('/api/iot/lecturas', [
                'tipo_medicion' => 'humedad_suelo',
                'valor' => 41.2,
                'unidad' => '%',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lote_id', $lote->id);
    }

    public function test_regenerar_la_key_invalida_la_anterior(): void
    {
        $this->comoSuperadmin();
        $lote = $this->lote();

        $alta = $this->postJson('/api/admin/dispositivos-iot', ['lote_id' => $lote->id, 'nombre' => 'ESP32']);
        $keyVieja = $alta->json('data.api_key');
        $id = $alta->json('data.dispositivo.id');

        $keyNueva = $this->postJson("/api/admin/dispositivos-iot/{$id}/regenerar-key")
            ->assertOk()
            ->json('data.api_key');

        $this->assertNotSame($keyVieja, $keyNueva);
        $this->assertNull(DispositivoIot::buscarActivoPorKey($keyVieja));
        $this->assertNotNull(DispositivoIot::buscarActivoPorKey($keyNueva));
    }

    public function test_desactivar_y_eliminar_un_dispositivo(): void
    {
        $this->comoSuperadmin();
        $lote = $this->lote();

        $alta = $this->postJson('/api/admin/dispositivos-iot', ['lote_id' => $lote->id, 'nombre' => 'ESP32']);
        $id = $alta->json('data.dispositivo.id');
        $key = $alta->json('data.api_key');

        $this->putJson("/api/admin/dispositivos-iot/{$id}", ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertNull(DispositivoIot::buscarActivoPorKey($key));

        $this->deleteJson("/api/admin/dispositivos-iot/{$id}")->assertOk();
        $this->assertDatabaseMissing('dispositivos_iot', ['id' => $id]);
    }

    public function test_la_mac_no_se_puede_repetir(): void
    {
        $this->comoSuperadmin();
        $lote = $this->lote();
        $datos = ['lote_id' => $lote->id, 'nombre' => 'ESP32', 'mac_dispositivo' => 'AA:BB:CC:DD:EE:01'];

        $this->postJson('/api/admin/dispositivos-iot', $datos)->assertCreated();
        $this->postJson('/api/admin/dispositivos-iot', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mac_dispositivo']);
    }

    public function test_un_productor_no_puede_gestionar_dispositivos(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['api:access']);
        $lote = $this->lote();

        $this->getJson('/api/admin/dispositivos-iot')->assertForbidden();
        $this->postJson('/api/admin/dispositivos-iot', ['lote_id' => $lote->id, 'nombre' => 'ESP32'])
            ->assertForbidden();

        $this->assertDatabaseCount('dispositivos_iot', 0);
    }

    public function test_sin_token_no_se_puede_gestionar_dispositivos(): void
    {
        $this->getJson('/api/admin/dispositivos-iot')->assertUnauthorized();
    }

    private function comoSuperadmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Superadmin]), ['api:access']);
    }

    private function lote(): Lote
    {
        $finca = Finca::create([
            'user_id' => User::factory()->create()->id,
            'estado' => 'aprobado',
            'nombre' => 'Finca de prueba',
            'hectareas_totales' => 10,
        ]);

        return Lote::create([
            'finca_id' => $finca->id,
            'nombre' => 'Lote 1',
            'hectareas' => 2,
            'tipo_cultivo' => 'Cacao',
        ]);
    }
}
