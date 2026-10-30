<?php

namespace Tests\Feature\Field;

use App\Enums\UserRole;
use App\Jobs\TranscribeEvidence;
use App\Models\AccessRequest;
use App\Models\DispositivoIot;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\UploadTicket;
use App\Models\User;
use App\Services\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldFlowsTest extends TestCase
{
    use RefreshDatabase;

    private User $producer;

    private Finca $farm;

    private Lote $plot;

    protected function setUp(): void
    {
        parent::setUp();
        config(['evidence.disk' => 's3']);
        $this->producer = User::factory()->create(['role' => UserRole::Productor]);
        $this->requestFor($this->producer, 'approved');
        $this->farm = Finca::create(['user_id' => $this->producer->id, 'nombre' => 'Finca propia', 'estado' => 'aprobado', 'latitud' => 8.2, 'longitud' => -73.3, 'hectareas_totales' => 10]);
        $this->plot = $this->farm->lotes()->create(['nombre' => 'Lote propio', 'hectareas' => 2, 'tipo_cultivo' => 'Cacao', 'estado' => 'en_uso']);
        Sanctum::actingAs($this->producer, ['api:access']);
    }

    private function requestFor(User $user, string $status): AccessRequest
    {
        return AccessRequest::create(['firstname' => 'Productor', 'lastname' => 'Prueba', 'id_type' => '', 'id_number' => '', 'landname' => 'Finca', 'country' => '', 'department' => '', 'city' => '', 'email' => $user->email, 'status' => $status]);
    }

    private function activity(): array
    {
        return ['tipo_actividad' => 'riego', 'fecha' => now()->toDateString(), 'costo' => 1000, 'observaciones' => 'Riego de prueba'];
    }

    public function test_decimal_hectares_fit_exactly_without_float_overallocation(): void
    {
        $f = Finca::create(['user_id' => $this->producer->id, 'nombre' => 'Pequeña', 'estado' => 'aprobado', 'latitud' => 8, 'longitud' => -73, 'hectareas_totales' => 0.30]);
        foreach ([0.10, 0.20] as $area) {
            $this->postJson('/api/campo/fincas/'.$f->id.'/lotes', ['nombre' => 'Pequeño', 'hectareas' => $area, 'tipo_cultivo' => 'Cacao'])->assertCreated();
        }
        $this->postJson('/api/campo/fincas/'.$f->id.'/lotes', ['nombre' => 'Exceso', 'hectareas' => 0.01, 'tipo_cultivo' => 'Cacao'])->assertUnprocessable();
    }

    public function test_device_event_retries_do_not_duplicate_telemetry_or_change_original_value(): void
    {
        $device = new DispositivoIot(['nombre' => 'Prueba', 'lote_id' => $this->plot->id]);
        $key = $device->asignarNuevaKey();
        $device->save();
        $data = ['event_id' => 'reading-001', 'tipo_medicion' => 'temperatura', 'valor' => 22.5, 'unidad' => '°C', 'fecha_medicion' => now()->toIso8601String()];
        $first = $this->postJson('/api/iot/lecturas', $data, ['X-Device-Key' => $key])->assertCreated();
        $this->postJson('/api/iot/lecturas', $data, ['X-Device-Key' => $key])->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson('/api/iot/lecturas', array_replace($data, ['valor' => 23]), ['X-Device-Key' => $key])->assertConflict();
        $this->assertDatabaseCount('lecturas_iot', 1);
    }

    public function test_transcription_does_not_overwrite_original_notes_and_is_idempotent(): void
    {
        $a = $this->plot->archivos()->create(['tipo_archivo' => 'audio', 'ruta_archivo' => 'test.m4a', 'storage_disk' => 's3', 'contenido_texto' => 'Nota original', 'transcription_status' => 'pending']);
        $service = $this->mock(TranscriptionService::class);
        $service->shouldReceive('transcribeAudio')->once()->with('test.m4a', 's3')->andReturn('Transcripción de prueba');
        $job = new TranscribeEvidence($a->id);
        $job->handle($service);
        $job->handle($service);
        $this->assertSame('Nota original', $a->fresh()->contenido_texto);
        $this->assertSame('Transcripción de prueba', $a->fresh()->transcription_text);
        $this->assertSame('completed', $a->fresh()->transcription_status);
    }

    public function test_forged_file_metadata_and_expired_tickets_never_register_evidence(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('fake.png', "%PDF-1.4\n%%EOF");
        $t = UploadTicket::create(['id' => fake()->uuid(), 'user_id' => $this->producer->id, 'modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'file_key' => 'fake.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'expires_at' => now()->addMinutes(10)]);
        $body = ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'archivos_subidos' => [['upload_id' => $t->id, 'ruta_archivo' => 'fake.png', 'peso_bytes' => 1]]];
        $this->postJson('/api/multimedia/subir', $body)->assertUnprocessable();
        $t->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/multimedia/subir', $body)->assertUnprocessable();
        $this->assertDatabaseCount('archivos_multimedia', 0);
    }

    public function test_producer_can_follow_all_farm_states_without_editing_pending_farms(): void
    {
        $pending = $this->farm->replicate();
        $pending->estado = 'pendiente';
        $pending->save();
        $this->getJson('/api/campo/fincas')->assertOk()->assertJsonCount(2, 'data');
        $this->putJson('/api/fincas/'.$pending->id.'/completar', ['latitud' => 8, 'longitud' => -73, 'hectareas_totales' => 5])->assertForbidden();
    }

    public function test_ownership_is_enforced_for_reads_writes_notes_downloads_and_dashboard(): void
    {
        $other = User::factory()->create();
        $farm = Finca::create(['user_id' => $other->id, 'nombre' => 'Ajena', 'estado' => 'aprobado', 'hectareas_totales' => 100, 'latitud' => 8, 'longitud' => -73]);
        $plot = $farm->lotes()->create(['nombre' => 'Ajeno', 'hectareas' => 50, 'tipo_cultivo' => 'Café', 'estado' => 'en_uso']);
        $evidence = $plot->archivos()->create(['tipo_archivo' => 'imagen', 'ruta_archivo' => 'private.png']);
        $this->getJson('/api/campo/fincas/'.$farm->id.'/lotes')->assertNotFound();
        $this->postJson('/api/campo/lotes/'.$plot->id.'/actividades', $this->activity())->assertNotFound();
        $this->postJson('/api/multimedia/subir', ['modelo_tipo' => 'lote', 'modelo_id' => $plot->id, 'texto' => 'No permitido'])->assertNotFound();
        $this->getJson('/api/campo/archivos/'.$evidence->id.'/descargar')->assertNotFound();
        $this->getJson('/api/campo/dashboard')->assertOk()->assertJsonPath('data.kpis.fincas', 1)->assertJsonPath('data.kpis.lotes_activos', 1);
    }

    public function test_superadmin_also_cannot_overallocate_or_reduce_a_farm_below_allocated_area(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        Sanctum::actingAs($admin, ['api:access']);
        $this->postJson('/api/admin/lotes', ['finca_id' => $this->farm->id, 'nombre' => 'Exceso', 'hectareas' => 9, 'tipo_cultivo' => 'Cacao', 'estado' => 'disponible'])->assertUnprocessable();
        $this->putJson('/api/admin/fincas/'.$this->farm->id, ['user_id' => $this->producer->id, 'nombre' => 'Finca', 'latitud' => 8, 'longitud' => -73, 'hectareas_totales' => 1])->assertUnprocessable();
        $this->assertEquals(10, $this->farm->fresh()->hectareas_totales);
    }

    public function test_archive_keeps_history_and_does_not_release_area(): void
    {
        $activity = $this->plot->actividades()->create($this->activity());
        $this->deleteJson('/api/campo/lotes/'.$this->plot->id, ['motivo' => 'Cierre de operación'])->assertOk();
        $this->assertSoftDeleted('lotes', ['id' => $this->plot->id]);
        $this->assertDatabaseHas('actividades', ['id' => $activity->id, 'deleted_at' => null]);
        $this->assertEquals(8, $this->farm->fresh()->hectareasDisponibles());
        $this->getJson('/api/campo/dashboard')->assertOk()->assertJsonPath('data.kpis.inversion_total', 1000);
        $this->getJson('/api/campo/lotes/'.$this->plot->id.'/actividades')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/campo/fincas/'.$this->farm->id.'/lotes?archivados=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_field_writes_require_complete_approved_farm_and_available_plot(): void
    {
        $this->farm->update(['estado' => 'rechazado']);
        $this->postJson('/api/campo/lotes/'.$this->plot->id.'/actividades', $this->activity())->assertUnprocessable();
        $this->farm->update(['estado' => 'aprobado']);
        $this->plot->update(['estado' => 'no_disponible']);
        $this->postJson('/api/lotes/'.$this->plot->id.'/actividades', $this->activity())->assertUnprocessable();
    }

    public function test_idempotent_retry_does_not_duplicate_activity_and_conflicting_reuse_is_rejected(): void
    {
        $path = '/api/campo/lotes/'.$this->plot->id.'/actividades';
        $headers = ['Idempotency-Key' => 'repeatable-operation'];
        $first = $this->postJson($path, $this->activity(), $headers)->assertCreated();
        $this->postJson($path, $this->activity(), $headers)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson($path, array_replace($this->activity(), ['costo' => 2000]), $headers)->assertConflict();
        $this->assertDatabaseCount('actividades', 1);
    }

    public function test_profile_cannot_change_email_role_or_owner(): void
    {
        $this->putJson('/api/campo/perfil', ['name' => 'Nuevo nombre', 'phone' => '+57 3001234567', 'role' => 'superadmin', 'email' => 'attacker@example.test'])->assertOk()->assertJsonPath('data.phone', '+57 3001234567');
        $this->assertSame(UserRole::Productor, $this->producer->fresh()->role);
        $this->assertSame($this->producer->email, $this->producer->fresh()->email);
    }

    public function test_revoked_account_cannot_continue_with_an_existing_access_or_refresh_token(): void
    {
        AccessRequest::where('email', $this->producer->email)->update(['status' => 'denied']);
        $this->getJson('/api/campo/fincas')->assertForbidden();
    }

    public function test_manual_reading_records_origin_and_checks_units(): void
    {
        $payload = ['tipo_medicion' => 'temperatura', 'valor' => 25, 'unidad' => '°C', 'fecha_medicion' => now()->toIso8601String(), 'motivo' => 'Medición de contraste'];
        $this->postJson('/api/campo/lotes/'.$this->plot->id.'/lecturas', $payload)->assertCreated()->assertJsonPath('data.origen', 'manual');
        $this->postJson('/api/campo/lotes/'.$this->plot->id.'/lecturas', array_replace($payload, ['unidad' => '%']))->assertUnprocessable();
        $this->getJson('/api/campo/lotes/'.$this->plot->id.'/lecturas?tipo_medicion=temperatura')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_note_and_private_upload_are_available_only_after_real_content_validation(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $this->postJson('/api/multimedia/subir', ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'texto' => 'Seguimiento', 'categoria' => 'enfermedad'])->assertCreated()->assertJsonPath('data.0.categoria', 'enfermedad');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=');
        $response = $this->post('/api/campo/multimedia/archivo', ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'archivo' => UploadedFile::fake()->createWithContent('foto.png', $png)], ['Accept' => 'application/json'])->assertCreated();
        $this->get('/api/campo/archivos/'.$response->json('data.0.id').'/descargar')->assertOk();
        $this->assertDatabaseHas('archivos_multimedia', ['id' => $response->json('data.0.id'), 'peso_bytes' => strlen($png)]);
        $this->postJson('/api/multimedia/subir', ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'archivos_subidos' => [['ruta_archivo' => 'documentos/usuario_999/ajeno.png']]])->assertUnprocessable();
    }

    public function test_ticket_is_bound_to_resource_expires_and_cannot_be_consumed_twice(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('test.pdf', "%PDF-1.4\n%%EOF");
        $ticket = UploadTicket::create(['id' => fake()->uuid(), 'user_id' => $this->producer->id, 'modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'file_key' => 'test.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100, 'expires_at' => now()->addMinutes(10)]);
        $payload = ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'archivos_subidos' => [['upload_id' => $ticket->id, 'ruta_archivo' => 'test.pdf']]];
        $this->postJson('/api/multimedia/subir', $payload)->assertCreated();
        $this->postJson('/api/multimedia/subir', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('archivos_multimedia', 1);
    }

    public function test_private_local_signed_upload_works_without_s3_and_rejects_invalid_signatures(): void
    {
        config(['evidence.disk' => 'local']);
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=');
        $ticket = $this->postJson('/api/minio/presigned-url', ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'filename' => 'photo.png', 'mime_type' => 'image/png', 'size_bytes' => strlen($png)])->assertOk()->json('data');
        $this->call('PUT', $ticket['upload_url'], [], [], [], ['CONTENT_TYPE' => 'image/png'], $png)->assertOk();
        $this->putJson('/api/uploads/'.$ticket['upload_id'], [])->assertForbidden();
        $this->postJson('/api/multimedia/subir', ['modelo_tipo' => 'lote', 'modelo_id' => $this->plot->id, 'archivos_subidos' => [['upload_id' => $ticket['upload_id'], 'ruta_archivo' => $ticket['file_key']]]])->assertCreated()->assertJsonPath('data.0.storage_disk', 'local');
    }

    public function test_public_request_is_generic_deduplicated_and_not_an_account_creation(): void
    {
        $payload = ['firstname' => 'Nueva', 'lastname' => 'Persona', 'id_type' => 'CC', 'id_number' => '0012345678', 'email' => ' NEW@example.test ', 'landname' => 'Finca nueva', 'consent' => true];
        $payload['email'] = trim($payload['email']);
        $this->postJson('/api/access-requests', $payload)->assertStatus(202);
        $this->postJson('/api/access-requests', $payload)->assertStatus(202);
        $this->assertDatabaseHas('access_requests', ['email' => 'new@example.test', 'status' => 'pending', 'id_type' => 'CC', 'id_number' => '0012345678']);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    public function test_public_request_requires_a_valid_document_before_it_is_saved(): void
    {
        $payload = ['firstname' => 'Nueva', 'lastname' => 'Persona', 'email' => 'document@example.test', 'landname' => 'Finca nueva', 'consent' => true];
        $this->postJson('/api/access-requests', $payload)->assertUnprocessable()->assertJsonValidationErrors(['id_type', 'id_number']);
        $this->postJson('/api/access-requests', $payload + ['id_type' => 'INVALID', 'id_number' => '12.345'])->assertUnprocessable()->assertJsonValidationErrors(['id_type', 'id_number']);
        $this->assertDatabaseMissing('access_requests', ['email' => 'document@example.test']);
        $this->postJson('/api/access-requests', $payload + ['id_type' => 'PA', 'id_number' => 'ab012345'])->assertStatus(202);
        $this->assertDatabaseHas('access_requests', ['email' => 'document@example.test', 'id_type' => 'PA', 'id_number' => 'AB012345']);
    }

    public function test_approval_is_final_and_requires_a_temporary_password_even_for_an_existing_account(): void
    {
        Mail::fake();
        $new = User::factory()->create(['role' => UserRole::Productor]);
        $hash = $new->password;
        $req = $this->requestFor($new, 'pending');
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Superadmin]), ['api:access']);
        $path = '/api/admin/access-requests/'.$req->id.'/status';
        $this->patchJson($path, ['action' => 'approve'])->assertOk();
        $this->assertNotSame($hash, $new->fresh()->password);
        $this->assertTrue($new->fresh()->must_change_password);
        $this->assertTrue(Hash::check($req->fresh()->invitation_secret, $new->fresh()->password));
        $this->patchJson($path, ['action' => 'deny'])->assertConflict();
        $this->assertSame('approved', $req->fresh()->status);
    }
}
