<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_global_dashboard_rejects_non_superadmins(): void
    {
        $producer = new User;
        $producer->id = 42;
        $producer->role = UserRole::Productor;
        Sanctum::actingAs($producer, ['api:access']);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_superadmin_receives_aggregates_from_real_records_without_join_duplication(): void
    {
        config(['dashboard.sensor_stale_minutes' => 45]);

        $owner = User::factory()->create(['role' => UserRole::Productor]);
        DB::table('fincas')->insert([['user_id' => $owner->id, 'nombre' => 'Primera', 'estado' => 'aprobado'], ['user_id' => $owner->id, 'nombre' => 'Segunda', 'estado' => 'pendiente']]);
        DB::table('lotes')->insert([
            ['finca_id' => 1, 'nombre' => 'Uno', 'estado' => 'en_uso', 'hectareas' => 1.25, 'tipo_cultivo' => 'cacao'],
            ['finca_id' => 1, 'nombre' => 'Dos', 'estado' => 'en_uso', 'hectareas' => 2.50, 'tipo_cultivo' => 'cacao'],
            ['finca_id' => 2, 'nombre' => 'Tres', 'estado' => 'disponible', 'hectareas' => 8.00, 'tipo_cultivo' => 'café'],
        ]);
        DB::table('actividades')->insert([
            ['lote_id' => 1, 'tipo_actividad' => 'riego', 'fecha' => Carbon::today()->toDateString(), 'costo' => 12.35],
            ['lote_id' => 2, 'tipo_actividad' => 'poda', 'fecha' => Carbon::today()->toDateString(), 'costo' => 7.65],
        ]);
        DB::table('lecturas_iot')->insert([
            ['lote_id' => 1, 'mac_dispositivo' => 'sensor-1', 'tipo_medicion' => 'temperatura', 'unidad' => '°C', 'valor' => 25.50, 'fecha_medicion' => Carbon::now()->subDay()],
            ['lote_id' => 2, 'mac_dispositivo' => 'sensor-2', 'tipo_medicion' => 'humedad_suelo', 'unidad' => '%', 'valor' => 45.00, 'fecha_medicion' => Carbon::now()->subMinutes(10)],
        ]);

        $admin = new User;
        $admin->id = 1;
        $admin->role = UserRole::Superadmin;
        Sanctum::actingAs($admin, ['api:access']);

        $this->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('data.kpis.fincas', 2)
            ->assertJsonPath('data.produccion.costos_produccion', 20)
            ->assertJsonPath('data.kpis.lotes_activos', 2)
            ->assertJsonPath('data.kpis.hectareas_cultivadas', 3.75)
            ->assertJsonPath('data.kpis.inversion_total', 20)
            ->assertJsonPath('data.kpis.registros_iot', 2)
            ->assertJsonPath('data.kpis.actividades', 2)
            ->assertJsonPath('data.cultivos_hectareas.0.hectareas', 3.75)
            ->assertJsonPath('data.metadata.moneda', 'COP')
            ->assertJsonPath('data.estado_sensores.criterio_disponible', true)
            ->assertJsonPath('data.estado_sensores.umbral_vigencia_minutos', 45)
            ->assertJsonPath('data.estado_sensores.reporte_reciente', 1)
            ->assertJsonPath('data.estado_sensores.sin_reporte_reciente', 1)
            ->assertJsonFragment(['mac_dispositivo' => 'sensor-1', 'estado' => 'sin_reporte_reciente'])
            ->assertJsonFragment(['mac_dispositivo' => 'sensor-2', 'estado' => 'reporte_reciente']);
    }
}
