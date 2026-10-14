<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
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

        Schema::create('fincas', function (Blueprint $table): void {
            $table->id();
            $table->string('estado');
        });
        Schema::create('lotes', function (Blueprint $table): void {
            $table->id();
            $table->string('estado');
            $table->decimal('hectareas', 8, 2);
            $table->string('tipo_cultivo');
        });
        Schema::create('actividades', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_actividad');
            $table->date('fecha');
            $table->decimal('costo', 10, 2)->default(0);
        });
        Schema::create('lecturas_iot', function (Blueprint $table): void {
            $table->id();
            $table->string('mac_dispositivo')->nullable();
            $table->string('tipo_medicion');
            $table->string('unidad');
            $table->decimal('valor', 8, 2);
            $table->timestamp('fecha_medicion');
        });

        DB::table('fincas')->insert([['estado' => 'aprobado'], ['estado' => 'pendiente']]);
        DB::table('lotes')->insert([
            ['estado' => 'en_uso', 'hectareas' => 1.25, 'tipo_cultivo' => 'cacao'],
            ['estado' => 'en_uso', 'hectareas' => 2.50, 'tipo_cultivo' => 'cacao'],
            ['estado' => 'disponible', 'hectareas' => 8.00, 'tipo_cultivo' => 'café'],
        ]);
        DB::table('actividades')->insert([
            ['tipo_actividad' => 'riego', 'fecha' => Carbon::today()->toDateString(), 'costo' => 12.35],
            ['tipo_actividad' => 'poda', 'fecha' => Carbon::today()->toDateString(), 'costo' => 7.65],
        ]);
        DB::table('lecturas_iot')->insert([
            ['mac_dispositivo' => 'sensor-1', 'tipo_medicion' => 'temperatura', 'unidad' => '°C', 'valor' => 25.50, 'fecha_medicion' => Carbon::now()->subDay()],
            ['mac_dispositivo' => 'sensor-2', 'tipo_medicion' => 'humedad_suelo', 'unidad' => '%', 'valor' => 45.00, 'fecha_medicion' => Carbon::now()->subMinutes(10)],
        ]);

        $admin = new User;
        $admin->id = 1;
        $admin->role = UserRole::Superadmin;
        Sanctum::actingAs($admin, ['api:access']);

        $this->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('data.kpis.fincas', 2)
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
