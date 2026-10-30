<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Services\WebSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class WebSessionTest extends TestCase
{
    use RefreshDatabase;

    private function producer(): User
    {
        $user = User::factory()->create(['role' => UserRole::Productor]);
        AccessRequest::create(['firstname' => 'Prueba', 'lastname' => 'Web', 'id_type' => 'CC', 'id_number' => '001234567', 'landname' => 'Finca', 'country' => '', 'department' => '', 'city' => '', 'email' => $user->email, 'status' => 'approved']);

        return $user;
    }

    private function headers(array $extra = []): array
    {
        return $extra + ['Origin' => 'http://localhost:4200', 'X-Aggrio-Web' => '1'];
    }

    private function login(User $user)
    {
        $this->withCredentials();

        return $this->postJson('/api/web/auth/login', ['email' => $user->email, 'password' => 'password'], $this->headers())->assertOk();
    }

    private function cookie($response): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === app(WebSession::class)->cookieName()) {
                return $cookie->getValue();
            }
        }
        $this->fail('Missing refresh cookie.');
    }

    public function test_web_login_uses_httponly_cookie_without_exposing_the_refresh_credential(): void
    {
        $response = $this->login($this->producer());
        $response->assertJsonMissingPath('data.refresh_token')->assertJsonMissingPath('data.token')->assertJsonStructure(['data' => ['access_token', 'csrf_token', 'user']]);
        $cookie = $response->headers->getCookies()[0];
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $token = PersonalAccessToken::findToken(Crypt::decryptString($this->cookie($response)));
        $this->assertContains('web:session', $token->abilities);
        $this->assertNotSame($this->cookie($response), $response->json('data.access_token'));
    }

    public function test_refresh_rotates_cookie_rejects_replay_and_does_not_accept_bearer_as_cookie(): void
    {
        $first = $this->login($this->producer());
        $old = $this->cookie($first);
        $csrf = $first->json('data.csrf_token');
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $old);
        $second = $this->postJson('/api/web/auth/refresh', [], $this->headers(['X-CSRF-Token' => $csrf]))->assertOk()->assertJsonMissingPath('data.refresh_token');
        $this->assertNotSame($old, $this->cookie($second));
        $this->assertSame($csrf, $second->json('data.csrf_token'));
        $this->postJson('/api/web/auth/refresh', [], $this->headers(['X-CSRF-Token' => $csrf]))->assertUnauthorized();
    }

    public function test_refresh_requires_csrf_and_trusted_origin(): void
    {
        $first = $this->login($this->producer());
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($first));
        $this->postJson('/api/web/auth/refresh', [], $this->headers())->assertStatus(419);
        $this->postJson('/api/web/auth/refresh', [], $this->headers(['X-CSRF-Token' => 'forged']))->assertStatus(419);
        $this->postJson('/api/web/auth/refresh', [], $this->headers(['Origin' => 'https://evil.example', 'X-CSRF-Token' => $first->json('data.csrf_token')]))->assertForbidden();
        $this->postJson('/api/web/auth/refresh', [], $this->headers(['Origin' => 'null', 'X-CSRF-Token' => $first->json('data.csrf_token')]))->assertForbidden();
    }

    public function test_login_rejects_untrusted_or_missing_browser_origin_and_unsupported_content_type(): void
    {
        $user = $this->producer();
        $body = ['email' => $user->email, 'password' => 'password'];
        $this->postJson('/api/web/auth/login', $body)->assertForbidden();
        $this->postJson('/api/web/auth/login', $body, $this->headers(['Origin' => 'https://evil.example']))->assertForbidden();
        $this->post('/api/web/auth/login', $body, $this->headers())->assertStatus(415);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_csrf_endpoint_restores_the_session_bound_token_without_returning_refresh_value(): void
    {
        $first = $this->login($this->producer());
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($first));
        $this->getJson('/api/web/auth/csrf', $this->headers())->assertOk()->assertJsonPath('data.csrf_token', $first->json('data.csrf_token'))->assertJsonMissingPath('data.refresh_token');
    }

    public function test_cookie_cannot_authenticate_business_routes_and_web_tokens_cannot_refresh_through_native_api(): void
    {
        $first = $this->login($this->producer());
        $plain = Crypt::decryptString($this->cookie($first));
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($first));
        $this->getJson('/api/campo/perfil')->assertUnauthorized();
        $this->withToken($plain)->postJson('/api/auth/refresh')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($first->json('data.access_token'))->putJson('/api/auth/password', ['password' => 'ClavePersonal123', 'password_confirmation' => 'ClavePersonal123'])->assertForbidden();
    }

    public function test_native_mobile_login_and_refresh_keep_the_existing_token_contract(): void
    {
        $user = $this->producer();
        $first = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'flutter'])->assertOk();
        $first->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'token']]);
        $this->assertCount(0, $first->headers->getCookies());
        $this->withToken($first->json('data.refresh_token'))->postJson('/api/auth/refresh')->assertOk()->assertJsonStructure(['data' => ['refresh_token']]);
    }

    public function test_logout_revokes_the_cookie_session_and_clears_the_cookie(): void
    {
        $user = $this->producer();
        $first = $this->login($user);
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($first));
        $this->postJson('/api/web/auth/logout', [], $this->headers(['X-CSRF-Token' => $first->json('data.csrf_token')]))->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->getJson('/api/web/auth/csrf', $this->headers())->assertUnauthorized();
    }

    public function test_suspended_accounts_cannot_restore_or_refresh_an_existing_cookie(): void
    {
        $user = $this->producer();
        $first = $this->login($user);
        $user->forceFill(['account_status' => 'suspended'])->save();
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($first));
        $this->getJson('/api/web/auth/csrf', $this->headers())->assertForbidden()->assertJsonPath('code', 'account_suspended');
        $this->postJson('/api/web/auth/refresh', [], $this->headers(['X-CSRF-Token' => $first->json('data.csrf_token')]))->assertForbidden();
    }

    public function test_production_cookie_is_secure_host_only_and_http_requests_are_rejected(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['web_session.origins' => ['https://app.aggrio.test']]);
        $user = $this->producer();
        $headers = ['Origin' => 'https://app.aggrio.test', 'X-Aggrio-Web' => '1'];
        $this->postJson('http://api.aggrio.test/api/web/auth/login', ['email' => $user->email, 'password' => 'password'], $headers)->assertStatus(400);
        $response = $this->postJson('https://api.aggrio.test/api/web/auth/login', ['email' => $user->email, 'password' => 'password'], $headers)->assertOk();
        $cookie = $response->headers->getCookies()[0];
        $this->assertSame('__Host-aggrio_refresh', $cookie->getName());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    public function test_web_first_access_changes_password_without_repeating_the_temporary_key_and_keeps_cookie_protection(): void
    {
        $user = $this->producer();
        $user->forceFill(['must_change_password' => true, 'temporary_password_expires_at' => now()->addHour()])->save();
        $first = $this->login($user);
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($first));
        $response = $this->putJson('/api/web/auth/password', ['password' => 'MiClavePersonal123', 'password_confirmation' => 'MiClavePersonal123'], $this->headers(['Authorization' => 'Bearer '.$first->json('data.access_token'), 'X-CSRF-Token' => $first->json('data.csrf_token')]))->assertOk()->assertJsonPath('data.user.must_change_password', false)->assertJsonMissingPath('data.refresh_token');
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.access_token'))->getJson('/api/campo/perfil')->assertOk();
    }

    public function test_superadmin_completes_two_factor_before_receiving_the_web_cookie(): void
    {
        $this->withCredentials();
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $secret = app(TwoFactorService::class)->generateSecret();
        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $first = $this->postJson('/api/web/auth/login', ['email' => $admin->email, 'password' => 'password'], $this->headers())->assertOk()->assertJsonPath('data.requires_two_factor', true)->assertJsonMissingPath('data.access_token');
        $this->assertCount(0, $first->headers->getCookies());
        $challenge = $first->json('data.challenge_token');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->postJson('/api/login/2fa/verify', ['challenge_token' => $challenge, 'code' => $code])->assertUnauthorized();
        $this->postJson('/api/web/auth/2fa/verify', ['challenge_token' => $challenge, 'code' => $code], $this->headers())->assertOk()->assertJsonMissingPath('data.refresh_token')->assertJsonPath('data.user.is_superadmin', true);
    }

    public function test_csrf_from_another_cookie_session_is_rejected(): void
    {
        $a = $this->login($this->producer());
        $b = $this->login($this->producer());
        $this->withUnencryptedCookie(app(WebSession::class)->cookieName(), $this->cookie($a));
        $this->postJson('/api/web/auth/refresh', [], $this->headers(['X-CSRF-Token' => $b->json('data.csrf_token')]))->assertStatus(419);
    }

    public function test_cors_allows_credentials_only_for_the_configured_frontend(): void
    {
        $allowed = $this->options('/api/web/auth/refresh', [], ['Origin' => 'http://localhost:4200', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'x-aggrio-web,x-csrf-token,content-type']);
        $allowed->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200')->assertHeader('Access-Control-Allow-Credentials','true');
        $blocked = $this->options('/api/web/auth/refresh',[],['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST']);
        $this->assertNotSame('https://evil.example',$blocked->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*',$blocked->headers->get('Access-Control-Allow-Origin'));
    }
}
