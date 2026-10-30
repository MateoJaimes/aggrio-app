<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Jobs\SendProducerInvitation;
use App\Models\AccessRequest;
use App\Models\Finca;
use App\Models\User;
use App\Services\ProducerOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function setupAccounts(): array
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $user = User::factory()->create(['role' => UserRole::Productor]);
        $r = AccessRequest::create(['firstname' => 'Productor', 'lastname' => 'Prueba', 'id_type' => 'CC', 'id_number' => '00123456', 'landname' => 'Finca', 'country' => '', 'department' => '', 'city' => '', 'email' => $user->email, 'status' => 'approved']);
        Sanctum::actingAs($admin, ['api:access']);

        return [$admin, $user, $r];
    }

    public function test_suspension_revokes_sessions_keeps_farms_and_records_the_actor_and_reason(): void
    {
        [$admin,$user,$r] = $this->setupAccounts();
        $user->createToken('old', ['api:access']);
        $farm = Finca::create(['user_id' => $user->id, 'nombre' => 'Finca propia', 'estado' => 'aprobado']);
        $this->postJson('/api/admin/accounts/'.$user->id.'/actions', ['action' => 'suspend', 'reason' => 'Solicitud de revisión de acceso'])->assertOk();
        $this->assertSame('suspended', $user->fresh()->account_status);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('fincas', ['id' => $farm->id, 'estado' => 'aprobado']);
        $this->assertSame('approved', $r->fresh()->status);
        $this->assertDatabaseHas('account_events', ['user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'suspend', 'reason' => 'Solicitud de revisión de acceso']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden()->assertJsonPath('code', 'account_suspended');
    }

    public function test_reactivation_keeps_password_and_does_not_restore_old_sessions_or_approve_farms(): void
    {
        [$admin,$user] = $this->setupAccounts();
        $hash = $user->password;
        $old = $user->createToken('old', ['api:access'])->plainTextToken;
        $farm = Finca::create(['user_id' => $user->id, 'nombre' => 'Pendiente', 'estado' => 'pendiente']);
        foreach (['suspend', 'reactivate'] as $action) {
            $this->postJson('/api/admin/accounts/'.$user->id.'/actions', ['action' => $action, 'reason' => 'Cambio autorizado de acceso'])->assertOk();
        }
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue($user->fresh()->isActive());
        $this->assertSame('pendiente', $farm->fresh()->estado);
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('/api/campo/fincas')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_recovery_generates_temporary_access_without_revealing_secrets_in_the_api_or_audit(): void
    {
        [$admin,$user,$r] = $this->setupAccounts();
        $user->createToken('old', ['api:access']);
        $response = $this->postJson('/api/admin/accounts/'.$user->id.'/actions', ['action' => 'recover', 'reason' => 'El productor solicitó recuperar acceso'])->assertOk();
        $r->refresh();
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check($r->invitation_secret, $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertStringNotContainsString($r->invitation_secret, $response->getContent());
        $this->assertStringNotContainsString($r->invitation_secret, json_encode(DB::table('account_events')->get()));
    }

    public function test_recovery_cannot_reactivate_a_suspension_and_invalid_actions_are_rejected(): void
    {
        [, $user] = $this->setupAccounts();
        $user->forceFill(['account_status' => 'suspended'])->save();
        $this->postJson('/api/admin/accounts/'.$user->id.'/actions', ['action' => 'recover', 'reason' => 'Recuperación solicitada por la persona'])->assertConflict();
        $this->postJson('/api/admin/accounts/'.$user->id.'/actions', ['action' => 'reactivate', 'reason' => 'corto'])->assertUnprocessable();
        $this->assertSame('suspended', $user->fresh()->account_status);
    }

    public function test_producers_and_admin_accounts_are_protected_from_account_management_changes(): void
    {
        [$admin,$user] = $this->setupAccounts();
        $this->postJson('/api/admin/accounts/'.$admin->id.'/actions', ['action' => 'suspend', 'reason' => 'Intento de suspender administración'])->assertForbidden();
        Sanctum::actingAs($user, ['api:access']);
        $this->getJson('/api/admin/accounts')->assertForbidden();
        $this->getJson('/api/admin/accounts/'.$admin->id.'/history')->assertForbidden();
        $this->postJson('/api/admin/accounts/'.$admin->id.'/actions', ['action' => 'recover', 'reason' => 'Intento sin permisos suficientes'])->assertForbidden();
        $this->assertDatabaseCount('account_events', 0);
    }

    public function test_suspension_cancels_pending_invitations_without_changing_approval(): void
    {
        [, $user,$r] = $this->setupAccounts();
        DB::transaction(function () use ($r, $user) {
            app(ProducerOnboarding::class)->prepare($r, $user, true);
        });
        $r->refresh();
        $job = new SendProducerInvitation($r->id, $r->invitation_version);
        $this->postJson('/api/admin/accounts/'.$user->id.'/actions', ['action' => 'suspend', 'reason' => 'Revisión antes de permitir acceso'])->assertOk();
        Mail::fake();
        $job->handle();
        Mail::assertNothingSent();
        $this->assertNull($r->fresh()->invitation_secret);
        $this->assertSame('approved', $r->fresh()->status);
    }

    public function test_retry_after_a_network_failure_does_not_duplicate_the_account_action(): void
    {
        [, $user] = $this->setupAccounts();
        $url = '/api/admin/accounts/'.$user->id.'/actions';
        $body = ['action' => 'suspend', 'reason' => 'Revisión de cuenta solicitada por soporte'];
        $headers = ['Idempotency-Key' => 'account-action-test'];
        $this->postJson($url, $body, $headers)->assertOk();
        $this->postJson($url, $body, $headers)->assertOk();
        $this->assertDatabaseCount('account_events', 1);
        $this->assertSame('suspended', $user->fresh()->account_status);
    }

    public function test_public_password_reset_cannot_reactivate_a_suspended_account(): void
    {
        [, $user] = $this->setupAccounts();
        $hash = $user->password;
        $token = Password::createToken($user);
        $user->forceFill(['account_status' => 'suspended'])->save();
        $this->postJson('/api/password/reset', ['email' => $user->email, 'token' => $token, 'password' => 'NuevaClaveSegura123', 'password_confirmation' => 'NuevaClaveSegura123'])->assertForbidden();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame('suspended', $user->fresh()->account_status);
    }
}
