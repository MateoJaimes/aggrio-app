<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\AuthenticatorDevice;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class SecurityDevicesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $secret;

    private string $base = '/api/admin/settings/security';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->secret = app(TwoFactorService::class)->generateSecret();
        $this->admin->forceFill(['two_factor_secret' => $this->secret, 'two_factor_confirmed_at' => now()])->save();
    }

    private function code(?string $secret = null): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret ?? $this->secret);
    }

    private function headers(): array
    {
        return ['Origin' => 'http://localhost:4200', 'X-Aggrio-Web' => '1', 'User-Agent' => 'Mozilla/5.0 Windows Chrome/131.0'];
    }

    private function credentials(): array
    {
        return ['password' => 'password', 'code' => $this->code()];
    }

    private function login()
    {
        $this->withCredentials();
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/web/auth/login', ['email' => $this->admin->email, 'password' => 'password'], $this->headers())->assertOk();
    }

    private function remember(): string
    {
        $challenge = $this->login()->assertJsonPath('data.requires_two_factor', true)->json('data.challenge_token');
        $response = $this->postJson('/api/web/auth/2fa/verify', ['challenge_token' => $challenge, 'code' => $this->code(), 'remember_device' => true, 'trusted_device_name' => 'Mi equipo'], $this->headers())->assertOk();
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === app(TrustedDeviceService::class)->cookieName()) {
                $this->assertTrue($cookie->isHttpOnly());
                $this->assertSame('strict', $cookie->getSameSite());
                $this->assertNull($cookie->getDomain());

                return $cookie->getValue();
            }
        }
        $this->fail('No se emitió cookie de dispositivo.');
    }

    public function test_remembered_browser_requires_password_then_skips_mfa_and_rotates_its_cookie(): void
    {
        $cookie = $this->remember();
        $record = TrustedDevice::firstOrFail();
        $this->assertStringNotContainsString($record->secret_hash, $cookie);
        $this->withUnencryptedCookie(app(TrustedDeviceService::class)->cookieName(), $cookie);
        $response = $this->login()->assertJsonPath('data.requires_two_factor', false)->assertJsonPath('data.trusted_device', true)->assertJsonMissingPath('data.refresh_token');
        $new = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === app(TrustedDeviceService::class)->cookieName())->getValue();
        $this->assertNotSame($new, $cookie);
        $this->withUnencryptedCookie(app(TrustedDeviceService::class)->cookieName(), $cookie);
        $this->login()->assertJsonPath('data.requires_two_factor', true);
        $this->postJson('/api/web/auth/login', ['email' => $this->admin->email, 'password' => 'wrong'], $this->headers())->assertUnauthorized();
    }

    public function test_expired_revoked_changed_agent_and_native_clients_cannot_reuse_browser_trust(): void
    {
        $cookie = $this->remember();
        $this->withUnencryptedCookie(app(TrustedDeviceService::class)->cookieName(), $cookie);
        $this->postJson('/api/login', ['email' => $this->admin->email, 'password' => 'password', 'device_name' => 'web-panel'], $this->headers())->assertOk()->assertJsonPath('data.requires_two_factor', true);
        $this->postJson('/api/web/auth/login', ['email' => $this->admin->email, 'password' => 'password'], array_merge($this->headers(), ['User-Agent' => 'Different browser']))->assertOk()->assertJsonPath('data.requires_two_factor', true);
        TrustedDevice::query()->update(['expires_at' => now()->subSecond()]);
        $this->login()->assertJsonPath('data.requires_two_factor', true);
        TrustedDevice::query()->delete();
        $this->login()->assertJsonPath('data.requires_two_factor', true);
    }

    public function test_failed_mfa_never_remembers_a_browser_and_success_without_checkbox_does_not_either(): void
    {
        $challenge = $this->login()->json('data.challenge_token');
        $bad = str_pad((string) (((int) $this->code() + 123) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->postJson('/api/web/auth/2fa/verify', ['challenge_token' => $challenge, 'code' => $bad, 'remember_device' => true], $this->headers())->assertUnprocessable();
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->postJson('/api/web/auth/2fa/verify', ['challenge_token' => $challenge, 'code' => $this->code()], $this->headers())->assertOk();
        $this->assertDatabaseCount('trusted_devices', 0);
    }

    public function test_additional_authenticator_is_encrypted_and_inactive_until_confirmed(): void
    {
        Sanctum::actingAs($this->admin, ['api:access']);
        $pending = $this->postJson($this->base.'/authenticators', $this->credentials() + ['name' => 'Segundo teléfono', 'mode' => 'add'])->assertOk()->json('data');
        $this->assertNotSame($pending['secret'], DB::table('authenticator_devices')->value('secret'));
        $this->assertFalse(app(TwoFactorService::class)->verifyCode($this->admin, $this->code($pending['secret'])));
        $this->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $this->code($pending['secret'])])->assertOk();
        $this->assertTrue(app(TwoFactorService::class)->verifyCode($this->admin->fresh(), $this->code($pending['secret'])));
        $this->getJson($this->base)->assertOk()->assertJsonCount(2, 'data.authenticators')->assertJsonMissingPath('data.authenticators.1.secret');
        $this->assertDatabaseHas('security_events', ['action' => 'authenticator_added']);
        $this->assertStringNotContainsString($pending['secret'], json_encode(DB::table('security_events')->get()));
    }

    public function test_replacing_the_primary_app_keeps_old_one_until_confirmation_then_revokes_it(): void
    {
        $this->remember();
        Sanctum::actingAs($this->admin, ['api:access']);
        $pending = $this->postJson($this->base.'/authenticators', $this->credentials() + ['name' => 'Nueva aplicación', 'mode' => 'replace'])->assertOk()->json('data');
        $this->assertTrue(app(TwoFactorService::class)->verifyCode($this->admin->fresh(), $this->code()));
        $this->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $this->code($pending['secret'])])->assertOk();
        $this->assertSame($pending['secret'], $this->admin->fresh()->two_factor_secret);
        $this->assertFalse(app(TwoFactorService::class)->verifyCode($this->admin->fresh(), $this->code()));
        $this->assertDatabaseCount('trusted_devices', 0);
    }

    public function test_last_authenticator_cannot_be_removed_and_wrong_credentials_cannot_add_one(): void
    {
        Sanctum::actingAs($this->admin, ['api:access']);
        $this->deleteJson($this->base.'/authenticators/principal', $this->credentials())->assertUnprocessable();
        $this->postJson($this->base.'/authenticators', ['name' => 'No', 'mode' => 'add', 'password' => 'wrong', 'code' => $this->code()])->assertUnprocessable();
        $this->assertDatabaseCount('authenticator_devices', 0);
        $this->assertSame($this->secret, $this->admin->fresh()->two_factor_secret);
    }

    public function test_pending_device_expiry_and_failed_attempts_are_enforced(): void
    {
        Sanctum::actingAs($this->admin, ['api:access']);
        $pending = $this->postJson($this->base.'/authenticators', $this->credentials() + ['name' => 'Prueba', 'mode' => 'add'])->assertOk()->json('data');
        $wrong = str_pad((string) (((int) $this->code($pending['secret']) + 123) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $wrong])->assertUnprocessable();
        $this->assertDatabaseHas('authenticator_devices', ['id' => $pending['id'], 'attempts' => 1]);
        AuthenticatorDevice::whereKey($pending['id'])->update(['expires_at' => now()->subSecond()]);
        $this->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $this->code($pending['secret'])])->assertConflict();
    }

    public function test_settings_are_superadmin_only_and_recovery_regeneration_requires_real_second_factor(): void
    {
        Sanctum::actingAs($this->admin, ['api:access']);
        $this->postJson('/api/2fa/recovery-codes', ['password' => 'password'])->assertUnprocessable();
        $response = $this->postJson($this->base.'/recovery-codes', $this->credentials())->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->assertTrue(app(TwoFactorService::class)->consumeRecoveryCode($this->admin->fresh(), $response->json('data.recovery_codes.0')));
        $this->assertFalse(app(TwoFactorService::class)->consumeRecoveryCode($this->admin->fresh(), $response->json('data.recovery_codes.0')));
        $producer = User::factory()->create(['role' => UserRole::Productor]);
        AccessRequest::create(['firstname' => 'Productor', 'lastname' => 'Prueba', 'id_type' => '', 'id_number' => '', 'landname' => '', 'country' => '', 'department' => '', 'city' => '', 'email' => $producer->email, 'status' => 'approved']);
        Sanctum::actingAs($producer, ['api:access']);
        $this->getJson($this->base)->assertForbidden();
        $this->postJson($this->base.'/authenticators', $this->credentials() + ['name' => 'No', 'mode' => 'add'])->assertForbidden();
    }

    public function test_logout_all_and_password_changes_revoke_trust(): void
    {
        $cookie = $this->remember();
        app(ApiTokenService::class)->revokeAll($this->admin);
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->withUnencryptedCookie(app(TrustedDeviceService::class)->cookieName(), $cookie);
        $this->login()->assertJsonPath('data.requires_two_factor', true);
    }

    public function test_browser_trust_is_bound_to_the_account_and_password_version(): void
    {
        $cookie = $this->remember();
        $this->withUnencryptedCookie(app(TrustedDeviceService::class)->cookieName(), $cookie);
        $other = User::factory()->create(['role' => UserRole::Superadmin]);
        $other->forceFill(['two_factor_secret' => $this->secret, 'two_factor_confirmed_at' => now()])->save();
        $this->postJson('/api/web/auth/login', ['email' => $other->email, 'password' => 'password'], $this->headers())->assertOk()->assertJsonPath('data.requires_two_factor', true);
        $this->admin->forceFill(['password' => 'NewPassword123'])->save();
        $this->postJson('/api/web/auth/login', ['email' => $this->admin->email, 'password' => 'NewPassword123'], $this->headers())->assertOk()->assertJsonPath('data.requires_two_factor', true);
    }

    public function test_factor_change_preserves_only_the_current_session_and_pending_registration_is_session_bound(): void
    {
        $current = app(ApiTokenService::class)->issue($this->admin, 'Current');
        $other = app(ApiTokenService::class)->issue($this->admin, 'Other');
        $this->withToken($current['access_token']);
        $pending = $this->postJson($this->base.'/authenticators', $this->credentials() + ['name' => 'Segundo teléfono', 'mode' => 'add'])->assertOk()->json('data');
        $this->app['auth']->forgetGuards();
        $this->withToken($other['access_token'])->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $this->code($pending['secret'])])->assertConflict();
        $this->app['auth']->forgetGuards();
        $this->withToken($current['access_token'])->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $this->code($pending['secret'])])->assertOk();
        $this->assertNotNull(PersonalAccessToken::findToken($current['access_token']));
        $this->assertNotNull(PersonalAccessToken::findToken($current['refresh_token']));
        $this->assertNull(PersonalAccessToken::findToken($other['access_token']));
        $this->assertNull(PersonalAccessToken::findToken($other['refresh_token']));
    }

    public function test_removing_the_primary_promotes_a_confirmed_device_without_disabling_mfa(): void
    {
        Sanctum::actingAs($this->admin, ['api:access']);
        $pending = $this->postJson($this->base.'/authenticators', $this->credentials() + ['name' => 'Teléfono de respaldo', 'mode' => 'add'])->assertOk()->json('data');
        $this->postJson($this->base.'/authenticators/'.$pending['id'].'/confirm', ['code' => $this->code($pending['secret'])])->assertOk();
        $this->deleteJson($this->base.'/authenticators/principal', $this->credentials())->assertOk();
        $this->assertTrue($this->admin->fresh()->hasConfirmedTwoFactor());
        $this->assertSame($pending['secret'], $this->admin->fresh()->two_factor_secret);
        $this->assertSame('Teléfono de respaldo', $this->admin->fresh()->two_factor_label);
    }
}
