<?php

namespace Tests\Feature\Field;

use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\Actividad;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionManagementTest extends TestCase
{
    use RefreshDatabase;

    private Finca $farm;

    private Lote $plot;

    private array $crop;

    private array $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => UserRole::Productor]);
        AccessRequest::create(['firstname' => 'Ana', 'lastname' => 'Campo', 'id_type' => 'CC', 'id_number' => '123', 'landname' => 'Finca', 'country' => 'Colombia', 'department' => 'Santander', 'city' => 'Socorro', 'email' => $user->email, 'status' => 'approved']);
        Sanctum::actingAs($user, ['api:access']);
        $this->farm = Finca::create(['user_id' => $user->id, 'nombre' => 'Finca', 'estado' => 'aprobado', 'latitud' => 6, 'longitud' => -73, 'hectareas_totales' => 10]);
        $this->plot = Lote::create(['finca_id' => $this->farm->id, 'nombre' => 'Lote', 'hectareas' => 2, 'tipo_cultivo' => 'Maíz', 'estado' => 'en_uso']);
        $this->crop = $this->createRecord('cultivos', ['lote_id' => $this->plot->id, 'tipo' => 'transitorio', 'especie' => 'Maíz', 'area' => 2, 'sistema' => 'monocultivo', 'responsable' => 'Ana']);
        $this->cycle = $this->createRecord('ciclos', ['lote_id' => $this->plot->id, 'cultivo_id' => $this->crop['id'], 'fecha_cosecha_estimada' => '2026-12-01', 'area' => 2, 'unidad' => 'kg', 'rendimiento_esperado' => 100]);
    }

    private function base(array $data = []): array
    {
        return $data + ['finca_id' => $this->farm->id, 'nombre' => 'Registro', 'fecha' => '2026-10-01', 'estado' => 'activo'];
    }

    private function createRecord(string $type, array $data): array
    {
        return $this->postJson('/api/produccion/registros/'.$type, $this->base($data))->assertCreated()->json('data');
    }

    private function context(): array
    {
        return ['lote_id' => $this->plot->id, 'cultivo_id' => $this->crop['id'], 'ciclo_id' => $this->cycle['id']];
    }

    private function harvest(float $quantity = 100): array
    {
        return $this->createRecord('cosechas', $this->context() + ['cantidad' => $quantity, 'perdida' => 5, 'unidad' => 'kg', 'calidad' => 'Primera', 'lote_producto' => 'LOT-'.uniqid()]);
    }

    private function sale(array $harvest, float $quantity = 20): array
    {
        return $this->createRecord('ventas', ['cosecha_id' => $harvest['id'], 'cantidad' => $quantity, 'precio_unitario' => 1000, 'comprador' => 'Comprador', 'cobrado' => 1000, 'fecha_cobro' => '2026-10-01']);
    }

    public function test_full_production_flow_cost_breakdown_cash_and_profit(): void
    {
        $labor = $this->createRecord('labores', $this->context() + ['nombre' => 'Preparación', 'estado' => 'terminado', 'tipo_actividad' => 'preparacion', 'responsable' => 'Ana', 'fecha_real' => '2026-10-01', 'presupuesto' => 4000, 'costo_real' => 9000]);
        $this->createRecord('costos', $this->context() + ['actividad_id' => $labor['actividad_id'], 'naturaleza' => 'produccion', 'categoria' => 'mano_obra', 'recurso' => 'Jornal', 'cantidad' => 2, 'unidad' => 'jornal', 'precio_unitario' => 1500]);
        $this->createRecord('costos', $this->context() + ['naturaleza' => 'inversion', 'categoria' => 'equipos', 'recurso' => 'Bomba', 'cantidad' => 1, 'unidad' => 'unidad', 'precio_unitario' => 5000]);
        $sale = $this->sale($this->harvest());
        $this->createRecord('cobros', ['venta_id' => $sale['id'], 'importe' => 3000, 'medio_pago' => 'transferencia', 'fecha' => '2026-10-02']);
        $this->getJson('/api/produccion/reporte')->assertOk()->assertJsonPath('data.costos_produccion', 3000)->assertJsonPath('data.inversion', 5000)
            ->assertJsonPath('data.ventas', 20000)->assertJsonPath('data.cobrado', 4000)->assertJsonPath('data.resultado', 17000)->assertJsonPath('data.por_cobrar_ventas_periodo', 16000)
            ->assertJsonPath('data.rendimientos.0.real', 95);
        $this->getJson('/api/produccion/reporte?desde=2026-10-02&hasta=2026-10-02')->assertOk()->assertJsonPath('data.ventas', 0)->assertJsonPath('data.cobrado', 3000);
        $otherPlot = Lote::create(['finca_id' => $this->farm->id, 'nombre' => 'Otro cultivo', 'hectareas' => 1, 'tipo_cultivo' => 'Café']);
        $otherCrop = $this->createRecord('cultivos', ['lote_id' => $otherPlot->id, 'tipo' => 'permanente', 'especie' => 'Café', 'area' => 1, 'sistema' => 'monocultivo', 'responsable' => 'Ana']);
        $this->createRecord('costos', ['lote_id' => $otherPlot->id, 'cultivo_id' => $otherCrop['id'], 'naturaleza' => 'produccion', 'categoria' => 'fertilizantes', 'recurso' => 'Abono de otro cultivo', 'cantidad' => 1, 'unidad' => 'unidad', 'precio_unitario' => 700]);
        $this->getJson('/api/produccion/reporte?finca_id='.$this->farm->id.'&cultivo_id='.$this->crop['id'])
            ->assertOk()->assertJsonPath('data.costos_produccion', 3000)->assertJsonPath('data.inversion', 5000)
            ->assertJsonPath('data.desembolso_total', 8000)->assertJsonPath('data.ventas', 20000)->assertJsonPath('data.resultado', 17000);
    }

    public function test_sales_cannot_exceed_net_harvest_and_payments_cannot_exceed_balance(): void
    {
        $harvest = $this->harvest(30);
        $sale = $this->sale($harvest);
        $this->postJson('/api/produccion/registros/ventas', $this->base(['cosecha_id' => $harvest['id'], 'cantidad' => 10, 'precio_unitario' => 1, 'comprador' => 'Otro', 'cobrado' => 0]))->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        $this->postJson('/api/produccion/registros/cobros', $this->base(['venta_id' => $sale['id'], 'importe' => 20000, 'medio_pago' => 'efectivo']))->assertUnprocessable()->assertJsonValidationErrors('importe');
    }

    public function test_shared_expenses_are_allocated_without_double_counting(): void
    {
        $cost = $this->createRecord('costos', ['naturaleza' => 'produccion', 'categoria' => 'energia', 'recurso' => 'Luz', 'cantidad' => 1, 'unidad' => 'unidad', 'precio_unitario' => 100]);
        $this->createRecord('repartos', $this->context() + ['costo_id' => $cost['id'], 'porcentaje' => 60, 'criterio' => 'area']);
        $this->getJson('/api/produccion/reporte')->assertOk()->assertJsonPath('data.costos_produccion', 100);
        $this->getJson('/api/produccion/reporte?cultivo_id='.$this->crop['id'])->assertOk()->assertJsonPath('data.costos_produccion', 60);
        $this->postJson('/api/produccion/registros/repartos', $this->base($this->context() + ['costo_id' => $cost['id'], 'porcentaje' => 50, 'criterio' => 'manual']))->assertUnprocessable();
    }

    public function test_tiny_shared_cost_rounding_conserves_the_original_total(): void
    {
        $cost = $this->createRecord('costos', ['naturaleza' => 'produccion', 'categoria' => 'otros', 'recurso' => 'Pequeño', 'cantidad' => 1, 'unidad' => 'unidad', 'precio_unitario' => 0.01]);
        foreach ([50, 50] as $percent) {
            $this->createRecord('repartos', $this->context() + ['costo_id' => $cost['id'], 'porcentaje' => $percent, 'criterio' => 'manual']);
        }
        $this->getJson('/api/produccion/reporte')->assertOk()->assertJsonPath('data.costos_produccion', 0.01);
    }

    public function test_record_versions_and_lineage_are_preserved(): void
    {
        $harvest = $this->harvest();
        $sale = $this->sale($harvest);
        $payload = $this->base($sale['datos'] + ['cosecha_id' => $harvest['id'], 'version' => 1, 'nombre' => 'Venta corregida']);
        $this->putJson('/api/produccion/registros/ventas/'.$sale['id'], $payload)->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson('/api/produccion/registros/ventas/'.$sale['id'], $payload)->assertConflict();
        $this->getJson('/api/produccion/historial/registro/'.$sale['id'])->assertOk()->assertJsonCount(2, 'data.history')->assertJsonStructure(['data' => ['qr', 'url']]);
        $this->assertDatabaseHas('audit_entries', ['resource_type' => ProductionRecord::class, 'resource_id' => $sale['id'], 'action' => 'updated']);
    }

    public function test_ownership_is_enforced_for_read_write_export_and_evidence(): void
    {
        $other = User::factory()->create(['role' => UserRole::Productor]);
        AccessRequest::create(['firstname' => 'Otro', 'lastname' => 'Productor', 'id_type' => '', 'id_number' => '', 'landname' => 'Otra', 'country' => '', 'department' => '', 'city' => '', 'email' => $other->email, 'status' => 'approved']);
        Sanctum::actingAs($other, ['api:access']);
        $this->getJson('/api/produccion/inicio')->assertOk()->assertJsonCount(0, 'data.records');
        $this->getJson('/api/produccion/historial/registro/'.$this->crop['id'])->assertNotFound();
        $this->putJson('/api/produccion/parametros/finca/'.$this->farm->id, ['tenencia' => 'propietario'])->assertNotFound();
        $this->postJson('/api/multimedia/subir', ['modelo_tipo' => 'registro', 'modelo_id' => $this->crop['id'], 'texto' => 'No autorizado'])->assertNotFound();
        $this->getJson('/api/produccion/reporte?finca_id='.$this->farm->id)->assertOk()->assertJsonPath('data.ventas', 0);
    }

    public function test_parameters_validate_areas_geojson_and_preserve_history(): void
    {
        $this->putJson('/api/produccion/parametros/finca/'.$this->farm->id, ['area_productiva' => 9, 'area_conservacion' => 3])->assertUnprocessable();
        $this->putJson('/api/produccion/parametros/finca/'.$this->farm->id, ['area_productiva' => 8, 'area_conservacion' => 2, 'tenencia' => 'propietario'])->assertOk();
        $this->putJson('/api/produccion/parametros/lote/'.$this->plot->id, ['ambiente' => 'invernadero', 'area_cubierta' => 30000])->assertUnprocessable();
        $this->putJson('/api/produccion/parametros/lote/'.$this->plot->id, ['poligono' => '{"type":"Point","coordinates":[0,0]}'])->assertUnprocessable();
        $this->putJson('/api/produccion/parametros/lote/'.$this->plot->id, ['ambiente' => 'invernadero', 'area_cubierta' => 5000, 'cubierta' => 'plastico'])->assertOk();
        $this->assertSame('invernadero', $this->plot->fresh()->parametros['ambiente']);
    }

    public function test_guide_is_idempotent_and_creates_seven_planned_tasks(): void
    {
        $payload = ['cultivo_id' => $this->crop['id'], 'ciclo_id' => $this->cycle['id']];
        $this->postJson('/api/produccion/guia', $payload)->assertCreated()->assertJsonCount(7, 'data');
        $this->postJson('/api/produccion/guia', $payload)->assertCreated()->assertJsonCount(0, 'data');
        $this->assertSame(7, ProductionRecord::where('tipo', 'labores')->count());
        $this->assertSame(0, Actividad::count());
    }

    public function test_closed_cycles_reject_new_operations_and_history_cannot_be_archived(): void
    {
        $this->putJson('/api/produccion/registros/ciclos/'.$this->cycle['id'], $this->base($this->context() + $this->cycle['datos'] + ['estado' => 'cerrado', 'version' => 1]))->assertOk();
        $this->postJson('/api/produccion/registros/cosechas', $this->base($this->context() + ['cantidad' => 1, 'unidad' => 'kg', 'calidad' => 'Primera', 'lote_producto' => 'Cerrado']))->assertUnprocessable();
        $this->deleteJson('/api/produccion/registros/'.$this->crop['id'], ['version' => 1, 'motivo' => 'Prueba'])->assertUnprocessable();
    }

    public function test_evidence_can_be_attached_to_a_production_record(): void
    {
        $this->postJson('/api/multimedia/subir', ['modelo_tipo' => 'registro', 'modelo_id' => $this->crop['id'], 'texto' => 'Primera siembra', 'latitud' => 6, 'longitud' => -73])->assertCreated();
        $this->getJson('/api/campo/evidencia/registro/'.$this->crop['id'])->assertOk()->assertJsonPath('data.data.0.contenido_texto', 'Primera siembra');
    }

    public function test_unavailable_farms_and_foreign_lineage_are_rejected(): void
    {
        $otherPlot = Lote::create(['finca_id' => $this->farm->id, 'nombre' => 'Otro lote', 'hectareas' => 1, 'tipo_cultivo' => 'Frijol']);
        $this->postJson('/api/produccion/registros/cosechas', $this->base(array_merge($this->context(), ['lote_id' => $otherPlot->id, 'cantidad' => 1, 'unidad' => 'kg', 'calidad' => 'Primera', 'lote_producto' => 'X'])))->assertUnprocessable();
        $this->farm->update(['estado' => 'pendiente']);
        $this->postJson('/api/produccion/guia', ['cultivo_id' => $this->crop['id']])->assertUnprocessable();
    }

    public function test_export_has_scoped_financial_movements(): void
    {
        $this->sale($this->harvest());
        $this->get('/api/produccion/exportar')->assertOk()->assertDownload('aggrio-produccion.csv');
    }

    public function test_archiving_a_cost_updates_legacy_totals_and_preserves_accessible_history(): void
    {
        $labor = $this->createRecord('labores', $this->context() + ['estado' => 'terminado', 'tipo_actividad' => 'riego', 'responsable' => 'Ana', 'fecha_real' => '2026-10-01', 'costo_real' => 100]);
        $cost = $this->createRecord('costos', $this->context() + ['actividad_id' => $labor['actividad_id'], 'naturaleza' => 'produccion', 'categoria' => 'agua', 'recurso' => 'Agua', 'cantidad' => 2, 'unidad' => 'm3', 'precio_unitario' => 40]);
        $this->assertSame(80.0, (float) Actividad::findOrFail($labor['actividad_id'])->costo);
        $this->assertSame(80.0, (float) ProductionRecord::findOrFail($labor['id'])->datos['costo_real']);
        $this->deleteJson('/api/produccion/registros/'.$cost['id'], ['version' => $cost['version'], 'motivo' => 'Factura anulada'])->assertOk();
        $this->assertSame(0.0, (float) Actividad::findOrFail($labor['actividad_id'])->costo);
        $this->assertSame(0.0, (float) ProductionRecord::findOrFail($labor['id'])->datos['costo_real']);
        $this->getJson('/api/produccion/reporte')->assertOk()->assertJsonPath('data.costos_produccion', 0);
        $this->getJson('/api/produccion/historial/registro/'.$cost['id'])->assertOk()->assertJsonPath('data.entity.id', $cost['id']);
    }

    public function test_custom_dropdown_values_are_remembered_with_their_parent(): void
    {
        $this->createRecord('cultivos', ['lote_id' => $this->plot->id, 'tipo' => 'permanente', 'especie' => 'Fruta propia', 'variedad' => 'Selección local', 'area' => 1, 'sistema' => 'asociado', 'responsable' => 'Ana']);
        $option = ProductionRecord::where('tipo', 'catalogos')->where('nombre', 'Selección local')->firstOrFail();
        $this->assertSame('variedades', $option->datos['catalogo']);
        $this->assertSame('Fruta propia', $option->datos['padre']);
    }

    public function test_a_producer_can_create_a_lot_before_choosing_a_crop(): void
    {
        $this->postJson('/api/campo/fincas/'.$this->farm->id.'/lotes', ['nombre' => 'Lote nuevo', 'hectareas' => 1])->assertCreated()->assertJsonPath('data.tipo_cultivo', 'Sin cultivo');
    }

    public function test_guided_planting_creates_the_crop_and_first_cycle_in_one_operation(): void
    {
        $data = $this->base(['nombre' => 'Frijol nuevo', 'lote_id' => $this->plot->id, 'tipo' => 'transitorio', 'especie' => 'Frijol', 'area' => 1, 'sistema' => 'monocultivo', 'responsable' => 'Ana', 'fecha_cosecha_estimada' => '2026-12-01', 'unidad' => 'kg']);
        $crop = $this->postJson('/api/produccion/siembras', $data, ['Idempotency-Key' => 'guided-planting'])->assertCreated()->assertJsonPath('data.ciclo_inicial.tipo', 'ciclos')->json('data');
        $this->assertSame($crop['id'], $crop['ciclo_inicial']['cultivo_id']);
        $this->postJson('/api/produccion/siembras', $data, ['Idempotency-Key' => 'guided-planting'])->assertCreated()->assertJsonPath('data.id', $crop['id']);
        $this->assertSame(1, ProductionRecord::where('tipo', 'cultivos')->where('nombre', 'Frijol nuevo')->count());
    }

    public function test_invalid_first_cycle_does_not_leave_a_half_created_crop(): void
    {
        $before = ProductionRecord::count();
        $this->postJson('/api/produccion/siembras', $this->base(['nombre' => 'No debe quedar', 'lote_id' => $this->plot->id, 'tipo' => 'transitorio', 'especie' => 'Frijol', 'area' => 1, 'sistema' => 'monocultivo', 'responsable' => 'Ana', 'fecha_cosecha_estimada' => '2026-01-01', 'unidad' => 'kg']))->assertUnprocessable();
        $this->assertSame($before, ProductionRecord::count());
    }

    private function sanitaryCase(): array
    {
        return $this->createRecord('sanidad_casos', $this->context() + ['estado' => 'abierto', 'clase_problema' => 'plaga', 'problema' => 'Problema de prueba', 'diagnostico' => 'sospecha', 'sintomas' => 'Hojas afectadas', 'responsable' => 'Ana', 'metodo_muestreo' => '20 plantas en zigzag', 'unidad_muestreo' => 'planta', 'muestra_total' => 20, 'muestra_afectada' => 8]);
    }

    public function test_sanitary_observations_keep_structured_measurements_and_related_history(): void
    {
        $case = $this->sanitaryCase();
        $this->assertSame(40, $case['datos']['incidencia_porcentaje']);
        $measure = $this->createRecord('sanidad_tratamientos', $this->context() + ['estado' => 'no_realizado', 'caso_id' => $case['id'], 'metodo' => 'monitoreo', 'accion' => 'Continuar observando', 'motivo_decision' => 'Pendiente de confirmación técnica', 'responsable' => 'Ana']);
        $follow = $this->createRecord('sanidad_seguimientos', $this->context() + ['estado' => 'registrado', 'caso_id' => $case['id'], 'tratamiento_id' => $measure['id'], 'resultado_observado' => 'mejoro', 'metodo_muestreo' => '20 plantas en zigzag', 'unidad_muestreo' => 'planta', 'muestra_total' => 20, 'muestra_afectada' => 2, 'responsable' => 'Ana', 'siguiente_accion' => 'Repetir visita']);
        $this->assertSame(10, $follow['datos']['incidencia_porcentaje']);
        $history = $this->getJson('/api/produccion/historial/registro/'.$case['id'])->assertOk()->json('data.related');
        $this->assertContains($follow['id'], array_column($history, 'id'));
        $this->assertSame(0, ProductionRecord::where('tipo', 'costos')->count());
    }

    public function test_sanitary_sample_and_application_validation_rejects_incomplete_or_impossible_data(): void
    {
        $case = $this->sanitaryCase();
        $this->postJson('/api/produccion/registros/sanidad_seguimientos', $this->base($this->context() + ['estado' => 'registrado', 'caso_id' => $case['id'], 'resultado_observado' => 'mejoro', 'metodo_muestreo' => 'Zigzag', 'unidad_muestreo' => 'planta', 'muestra_total' => 20, 'muestra_afectada' => 21, 'responsable' => 'Ana', 'siguiente_accion' => 'Revisar']))->assertUnprocessable()->assertJsonValidationErrors('muestra_afectada');
        $this->postJson('/api/produccion/registros/sanidad_tratamientos', $this->base($this->context() + ['estado' => 'aplicado', 'caso_id' => $case['id'], 'metodo' => 'quimico', 'accion' => 'Aplicación', 'motivo_decision' => 'Recomendación técnica', 'responsable' => 'Ana']))->assertUnprocessable()->assertJsonValidationErrors('producto');
        $this->postJson('/api/produccion/registros/sanidad_tratamientos', $this->base($this->context() + ['estado' => 'no_realizado', 'caso_id' => $case['id'], 'metodo' => 'monitoreo', 'accion' => 'Esperar', 'responsable' => 'Ana']))->assertUnprocessable()->assertJsonValidationErrors('motivo_decision');
    }

    public function test_sanitary_case_cannot_be_reused_in_another_crop(): void
    {
        $case = $this->sanitaryCase();
        $other = $this->createRecord('cultivos', ['lote_id' => $this->plot->id, 'tipo' => 'permanente', 'especie' => 'Café', 'area' => 1, 'sistema' => 'asociado', 'responsable' => 'Ana']);
        $this->postJson('/api/produccion/registros/sanidad_tratamientos', $this->base(['lote_id' => $this->plot->id, 'cultivo_id' => $other['id'], 'estado' => 'no_realizado', 'caso_id' => $case['id'], 'metodo' => 'monitoreo', 'accion' => 'Esperar', 'motivo_decision' => 'Observar', 'responsable' => 'Ana']))->assertUnprocessable()->assertJsonValidationErrors('cultivo_id');
    }
}
