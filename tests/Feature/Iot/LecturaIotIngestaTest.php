<?php

namespace Tests\Feature\Iot;

use App\Models\DispositivoIot;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de POST /api/iot/lecturas.
 *
 * RF001: endpoint público para los sensores (no usa Sanctum).
 * RF002: el sensor se identifica con X-Device-Key y sólo escribe en su propio lote.
 */
class LecturaIotIngestaTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- RF001: sin key

    public function test_acepta_una_lectura_sin_key_mientras_no_sea_obligatoria(): void
    {
        $lote = $this->loteEnFinca('aprobado');

        $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lote_id', $lote->id)
            ->assertJsonPath('data.tipo_medicion', 'temperatura');

        $this->assertDatabaseHas('lecturas_iot', ['lote_id' => $lote->id, 'valor' => 28.5]);
    }

    public function test_no_requiere_token_de_usuario(): void
    {
        $lote = $this->loteEnFinca('aprobado');

        // Sin Authorization: el sensor nunca maneja login de usuario.
        $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id]))
            ->assertCreated();
    }

    public function test_valida_los_campos_de_la_lectura(): void
    {
        $this->postJson('/api/iot/lecturas', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lote_id', 'tipo_medicion', 'valor', 'unidad']);

        $lote = $this->loteEnFinca('aprobado');

        $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id, 'tipo_medicion' => 'presion']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_medicion']);
    }

    /**
     * La columna es decimal(8,2). Antes un valor fuera de rango llegaba a la base
     * y PostgreSQL lo rechazaba con un 500.
     */
    public function test_un_valor_fuera_de_rango_da_422_y_no_500(): void
    {
        $lote = $this->loteEnFinca('aprobado');

        foreach ([10000000, -10000000] as $valor) {
            $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id, 'valor' => $valor]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['valor']);
        }

        $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id, 'valor' => 999999.99]))
            ->assertCreated();
    }

    public function test_rechaza_lotes_de_fincas_no_aprobadas_o_inexistentes_con_el_mismo_mensaje(): void
    {
        $pendiente = $this->loteEnFinca('pendiente');
        $rechazado = $this->loteEnFinca('rechazado');

        $mensajes = [];

        foreach ([$pendiente->id, $rechazado->id, 99999] as $loteId) {
            $mensajes[] = $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $loteId]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['lote_id'])
                ->json('errors.lote_id.0');
        }

        // Mismo mensaje en los tres casos: la respuesta no revela qué IDs existen.
        $this->assertCount(1, array_unique($mensajes));
        $this->assertDatabaseCount('lecturas_iot', 0);
    }

    public function test_limita_las_lecturas_por_minuto(): void
    {
        config(['iot.rate_limit_per_minute' => 3]);
        $lote = $this->loteEnFinca('aprobado');

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id]))->assertCreated();
        }

        $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id]))
            ->assertTooManyRequests();

        $this->assertDatabaseCount('lecturas_iot', 3);
    }

    // ------------------------------------------------- RF002: con API key

    public function test_con_key_la_lectura_va_al_lote_del_dispositivo_y_no_al_del_cuerpo(): void
    {
        $loteDelSensor = $this->loteEnFinca('aprobado');
        $loteAjeno = $this->loteEnFinca('aprobado');
        [$dispositivo, $key] = $this->dispositivoEn($loteDelSensor, 'AA:BB:CC:DD:EE:FF');

        $this->withHeader(DispositivoIot::KEY_HEADER, $key)
            ->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $loteAjeno->id, 'mac_dispositivo' => 'falsa']))
            ->assertCreated()
            ->assertJsonPath('data.lote_id', $loteDelSensor->id)
            ->assertJsonPath('data.mac_dispositivo', 'AA:BB:CC:DD:EE:FF');

        $this->assertDatabaseMissing('lecturas_iot', ['lote_id' => $loteAjeno->id]);
        $this->assertNotNull($dispositivo->fresh()->ultima_conexion);
    }

    public function test_con_key_el_lote_id_es_opcional(): void
    {
        $lote = $this->loteEnFinca('aprobado');
        [, $key] = $this->dispositivoEn($lote);

        $lectura = $this->lectura();
        unset($lectura['lote_id']);

        $this->withHeader(DispositivoIot::KEY_HEADER, $key)
            ->postJson('/api/iot/lecturas', $lectura)
            ->assertCreated()
            ->assertJsonPath('data.lote_id', $lote->id);
    }

    public function test_una_key_invalida_se_rechaza_siempre(): void
    {
        $lote = $this->loteEnFinca('aprobado');

        $this->withHeader(DispositivoIot::KEY_HEADER, 'iot_inventada')
            ->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id]))
            ->assertUnauthorized();

        $this->assertDatabaseCount('lecturas_iot', 0);
    }

    public function test_un_dispositivo_desactivado_no_puede_enviar_lecturas(): void
    {
        $lote = $this->loteEnFinca('aprobado');
        [$dispositivo, $key] = $this->dispositivoEn($lote);
        $dispositivo->update(['activo' => false]);

        $this->withHeader(DispositivoIot::KEY_HEADER, $key)
            ->postJson('/api/iot/lecturas', $this->lectura())
            ->assertUnauthorized();
    }

    public function test_un_dispositivo_cuya_finca_deja_de_estar_aprobada_es_rechazado(): void
    {
        $lote = $this->loteEnFinca('aprobado');
        [, $key] = $this->dispositivoEn($lote);
        $lote->finca->update(['estado' => 'rechazado']);

        $this->withHeader(DispositivoIot::KEY_HEADER, $key)
            ->postJson('/api/iot/lecturas', $this->lectura())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lote_id']);
    }

    public function test_con_la_key_obligatoria_rechaza_lecturas_sin_key(): void
    {
        config(['iot.require_device_key' => true]);
        $lote = $this->loteEnFinca('aprobado');
        [, $key] = $this->dispositivoEn($lote);

        $this->postJson('/api/iot/lecturas', $this->lectura(['lote_id' => $lote->id]))
            ->assertUnauthorized();

        $this->withHeader(DispositivoIot::KEY_HEADER, $key)
            ->postJson('/api/iot/lecturas', $this->lectura())
            ->assertCreated();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function lectura(array $overrides = []): array
    {
        return [
            'lote_id' => null,
            'mac_dispositivo' => null,
            'tipo_medicion' => 'temperatura',
            'valor' => 28.5,
            'unidad' => '°C',
            ...$overrides,
        ];
    }

    private function loteEnFinca(string $estadoFinca): Lote
    {
        $finca = Finca::create([
            'user_id' => User::factory()->create()->id,
            'estado' => $estadoFinca,
            'nombre' => 'Finca '.$estadoFinca,
            'hectareas_totales' => 10,
        ]);

        return Lote::create([
            'finca_id' => $finca->id,
            'nombre' => 'Lote 1',
            'hectareas' => 2,
            'tipo_cultivo' => 'Cacao',
        ]);
    }

    /**
     * @return array{0: DispositivoIot, 1: string}
     */
    private function dispositivoEn(Lote $lote, ?string $mac = null): array
    {
        $dispositivo = new DispositivoIot([
            'lote_id' => $lote->id,
            'nombre' => 'ESP32 de prueba',
            'mac_dispositivo' => $mac,
        ]);
        $key = $dispositivo->asignarNuevaKey();
        $dispositivo->save();

        return [$dispositivo, $key];
    }
}
