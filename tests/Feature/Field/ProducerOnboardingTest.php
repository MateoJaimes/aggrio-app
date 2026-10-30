<?php

namespace Tests\Feature\Field;

use App\Enums\UserRole;
use App\Jobs\SendProducerInvitation;
use App\Mail\ProducerInvitationMail;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProducerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function approve(): array
    {
        Queue::fake();
        $r = AccessRequest::create(['firstname' => 'Productor', 'lastname' => 'Prueba', 'id_type' => 'CC', 'id_number' => '0012345678',
            'landname' => 'Mi primera finca', 'country' => 'Colombia', 'department' => 'Santander', 'city' => 'San Vicente',
            'email' => 'producer@example.test', 'status' => 'pending']);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Superadmin]), ['api:access']);
        $response = $this->patchJson('/api/admin/access-requests/'.$r->id.'/status', ['action' => 'approve'])->assertOk();
        $this->assertArrayNotHasKey('invitation_secret', $response->json('data'));
        $this->assertDatabaseHas('fincas', ['nombre' => 'Mi primera finca', 'estado' => 'aprobado']);

        return [$r->fresh(), User::where('email', $r->email)->firstOrFail()];
    }

    public function test_approval_creates_a_private_temporary_password_and_sends_the_login_email_once(): void
    {
        [$r, $user] = $this->approve();
        $password = $r->invitation_secret;
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertStringNotContainsString($password, DB::table('access_requests')->where('id', $r->id)->value('invitation_secret'));
        $job = new SendProducerInvitation($r->id, $r->invitation_version);
        $this->assertStringNotContainsString($password, serialize($job));
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $job->handle();
        $job->handle();
        Mail::assertSent(ProducerInvitationMail::class, function ($mail) use ($password) {
            return $mail->hasTo('producer@example.test') && str_contains($mail->render(), $password) && str_contains($mail->url, '/auth/login');
        });
        Mail::assertSentCount(1);
        $this->assertSame('sent', $r->fresh()->invitation_status);
        $this->assertNull($r->fresh()->invitation_secret);
    }

    public function test_temporary_login_requires_password_change_then_accesses_only_own_data(): void
    {
        [$r, $user] = $this->approve();
        $this->app['auth']->forgetGuards();
        $pair = $this->postJson('/api/login', ['email' => $user->email, 'password' => $r->invitation_secret])->assertOk()->assertJsonPath('data.user.must_change_password', true)->json('data');
        $headers = ['Authorization' => 'Bearer '.$pair['access_token']];
        $this->getJson('/api/campo/fincas', $headers)->assertStatus(428);
        $this->putJson('/api/auth/password', ['password' => 'MiClavePersonal123', 'password_confirmation' => 'NoCoincide123'], $headers)->assertUnprocessable();
        $new = $this->putJson('/api/auth/password', ['password' => 'MiClavePersonal123', 'password_confirmation' => 'MiClavePersonal123'], $headers)->assertOk()->assertJsonPath('data.user.must_change_password', false)->json('data');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertFalse(Hash::check($r->invitation_secret, $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', explode('|', $pair['access_token'])[1])]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/campo/fincas', ['Authorization' => 'Bearer '.$new['access_token']])->assertOk()->assertJsonPath('data.0.nombre', 'Mi primera finca');
        $this->getJson('/api/campo/perfil', ['Authorization' => 'Bearer '.$new['access_token']])->assertOk()->assertJsonPath('data.registration.id_number', '0012345678');
        $this->putJson('/api/auth/password', ['password' => 'OtraClavePersonal123', 'password_confirmation' => 'OtraClavePersonal123'], ['Authorization' => 'Bearer '.$new['access_token']])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_expired_password_cannot_login_and_resend_invalidates_the_old_password(): void
    {
        [$r, $user] = $this->approve();
        $oldPassword = $r->invitation_secret;
        $this->travel(25)->hours();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => $oldPassword])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Superadmin]), ['api:access']);
        $this->postJson('/api/admin/access-requests/'.$r->id.'/invitation')->assertOk();
        $this->assertFalse(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertTrue(Hash::check($r->fresh()->invitation_secret, $user->fresh()->password));
    }

    public function test_smtp_failure_does_not_mark_delivery_as_successful(): void
    {
        [$r] = $this->approve();
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $job = new SendProducerInvitation($r->id, $r->invitation_version);
        try {
            $job->handle();
            $this->fail('Expected SMTP failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('queued', $r->fresh()->invitation_status);
            $job->failed($e);
        }
        $this->assertSame('failed', $r->fresh()->invitation_status);
        $this->assertNull($r->fresh()->invitation_sent_at);
        $this->assertNotNull($r->fresh()->invitation_secret);
    }

    public function test_local_preview_is_not_reported_as_a_real_email_and_is_private(): void
    {
        [$r, $user] = $this->approve();
        Storage::fake('local');
        $this->app->detectEnvironment(fn () => 'local');
        config(['mail.default' => 'log', 'invitations.console_enabled' => false]);
        Mail::fake();
        (new SendProducerInvitation($r->id, $r->invitation_version))->handle();
        Mail::assertNothingSent();
        $this->assertSame('preview', $r->fresh()->invitation_status);
        $this->getJson('/api/admin/access-requests/'.$r->id.'/invitation-preview')->assertOk();
        $user->forceFill(['must_change_password' => false])->save();
        Sanctum::actingAs($user, ['api:access']);
        $this->getJson('/api/admin/access-requests/'.$r->id.'/invitation-preview')->assertForbidden();
    }

    public function test_local_console_shows_the_temporary_login_after_processing_without_sending_email(): void
    {
        [$r] = $this->approve();
        $password = $r->invitation_secret;
        Storage::fake('local');
        $this->app->detectEnvironment(fn () => 'local');
        config(['mail.default' => 'log', 'invitations.console_enabled' => true]);
        Mail::fake();
        ob_start();
        try {
            (new SendProducerInvitation($r->id, $r->invitation_version))->handle();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString($password, $output);
        $this->assertStringContainsString('producer@example.test', $output);
        $this->assertStringContainsString('/auth/login', $output);
        $this->assertSame('preview', $r->fresh()->invitation_status);
        $this->assertNull($r->fresh()->invitation_secret);
        Mail::assertNothingSent();
    }

    public function test_smtp_never_prints_credentials_even_if_console_flag_is_enabled(): void
    {
        [$r] = $this->approve();
        $this->app->detectEnvironment(fn () => 'local');
        config(['mail.default' => 'smtp', 'invitations.console_enabled' => true]);
        Mail::fake();
        ob_start();
        try {
            (new SendProducerInvitation($r->id, $r->invitation_version))->handle();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame('', $output);
        $this->assertSame('sent', $r->fresh()->invitation_status);
        Mail::assertSentCount(1);
    }

    public function test_explicit_local_reset_replaces_an_existing_password_and_prints_the_new_temporary_one(): void
    {
        [$r, $user] = $this->approve();
        $user->forceFill(['must_change_password' => false, 'password' => Hash::make('MiClaveAnterior123')])->save();
        $user->createToken('previous-session', ['api:access']);
        $r->forceFill(['invitation_status' => 'preview', 'invitation_secret' => null])->save();
        Storage::fake('local');
        $this->app->detectEnvironment(fn () => 'local');
        config(['mail.default' => 'log', 'invitations.console_enabled' => true]);
        $this->postJson('/api/admin/access-requests/'.$r->id.'/invitation', ['reset_password' => true])->assertOk();
        $r->refresh();
        $this->assertTrue(Hash::check($r->invitation_secret, $user->fresh()->password));
        $this->assertFalse(Hash::check('MiClaveAnterior123', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        ob_start();
        try {
            (new SendProducerInvitation($r->id, $r->invitation_version))->handle();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString($r->invitation_secret, $output);
        $this->assertStringNotContainsString('Cuenta existente', $output);
    }

    public function test_normal_resend_preserves_personal_password_and_local_reset_is_forbidden_outside_local_testing(): void
    {
        [$r, $user] = $this->approve();
        $user->forceFill(['must_change_password' => false])->save();
        $hash = $user->fresh()->password;
        $r->forceFill(['invitation_status' => 'sent'])->save();
        $this->app->detectEnvironment(fn () => 'local');
        config(['mail.default' => 'log', 'invitations.console_enabled' => true]);
        $this->postJson('/api/admin/access-requests/'.$r->id.'/invitation')->assertOk();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertNull($r->fresh()->invitation_secret);
        config(['mail.default' => 'smtp']);
        $this->postJson('/api/admin/access-requests/'.$r->id.'/invitation', ['reset_password' => true])->assertForbidden();
        config(['mail.default' => 'log']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('/api/admin/access-requests/'.$r->id.'/invitation', ['reset_password' => true])->assertForbidden();
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_production_never_prints_credentials_when_mail_is_misconfigured(): void
    {
        [$r] = $this->approve();
        $this->app->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log', 'invitations.console_enabled' => true]);
        ob_start();
        try {
            (new SendProducerInvitation($r->id, $r->invitation_version))->handle();
            $this->fail('Production must require real mail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('correo real', $e->getMessage());
        } finally {
            $output = ob_get_clean();
        }
        $this->assertSame('', $output);
        $this->assertSame('queued', $r->fresh()->invitation_status);
    }
}
