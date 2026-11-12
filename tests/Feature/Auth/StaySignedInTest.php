<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class StaySignedInTest extends TestCase
{
    use RefreshDatabase;

    public function test_github_sign_in_keeps_you_signed_in_after_the_session_ends(): void
    {
        config([
            'services.github_login' => ['client_id' => 'Ov23.login', 'client_secret' => 'login-secret', 'redirect' => 'http://localhost/auth/github/login/callback'],
            'framework.github_login' => true,
        ]);
        $user = User::factory()->create(['password' => null, 'email' => 'ada@example.org']);
        $github = (new SocialiteUser)->map(['id' => 42, 'nickname' => 'ada', 'name' => 'Ada', 'email' => 'ada@example.org']);
        $github->setToken('gho_login');
        $provider = Mockery::mock(GithubProvider::class);
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://github.com/login/oauth/authorize'));
        $provider->shouldReceive('user')->andReturn($github);
        Socialite::shouldReceive('driver')->with('github-login')->andReturn($provider);

        $this->get('/auth/github/login')->assertRedirect();
        $response = $this->get('/auth/github/login/callback?code=x&state=y');
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'no remember cookie');
        $this->assertGreaterThan(now()->addDays(300)->timestamp, $cookie->getExpiresTime());

        // The session ends; only the remember cookie is left
        Auth::guard('web')->logoutCurrentDevice();
        $this->flushSession();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_an_account_without_a_password_cannot_sign_in_with_an_empty_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->assertFalse(Auth::validate(['email' => $user->email, 'password' => '']));
        $this->assertFalse(Auth::validate(['email' => $user->email, 'password' => 'anything']));
    }
}
