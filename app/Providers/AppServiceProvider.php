<?php

namespace App\Providers;

use App\Http\Controllers\OAuth\ApproveMcpAuthorizationController;
use App\Models\McpApprovedApp;
use App\Support\Credentials;
use App\Support\TrustedProxies;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The hosted MCP server registers only the OAuth routes it needs, in
        // routes/ai.php.
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Signing in with GitHub uses its own OAuth app, which reads only email addresses
        Socialite::extend('github-login', fn () => Socialite::buildProvider(GithubProvider::class, config('services.github_login')));

        // An empty public/hot (left by a dev server that didn't finish
        // starting) would send every page to a dev server that isn't there.
        // Use the built assets instead.
        $hot = public_path('hot');
        if (is_file($hot) && filesize($hot) === 0) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }

        // Fill in this server's credentials saved on the admin setup page,
        // wherever the environment leaves them unset
        Credentials::apply();

        // Proxies from config, so TRUSTED_PROXIES in .env works with a cached config too
        TrustProxies::at(TrustedProxies::resolve(config('framework.trusted_proxies')));

        $this->configurePassport();
    }

    /**
     * OAuth for the hosted MCP server: short access tokens, a month of
     * refresh, and our own consent screen with the access choice.
     */
    private function configurePassport(): void
    {
        Passport::tokensCan(['mcp:use' => 'Use Framework Cloud from an AI app']);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));

        // Keys from PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY (Forge), or
        // storage/oauth-*.key files made by passport:keys.
        Passport::authorizationView(function (array $parameters) {
            $redirectHost = parse_url((string) $parameters['request']->query('redirect_uri'), PHP_URL_HOST) ?: null;

            // A web app the person approved before connects again with the same access, without asking
            $approved = McpApprovedApp::for($parameters['user']->id, $parameters['client']->name, $redirectHost);
            if ($approved !== null) {
                $request = $parameters['request'];
                $request->merge(['auth_token' => $parameters['authToken'], 'access' => $approved->access]);

                return app()->call([app(ApproveMcpAuthorizationController::class), '__invoke'], ['request' => $request]);
            }

            return Inertia::render('OAuth/Authorize', [
                'client' => ['name' => $parameters['client']->name],
                'user' => ['name' => $parameters['user']->name, 'email' => $parameters['user']->email],
                'authToken' => $parameters['authToken'],
                'csrfToken' => csrf_token(),
                'defaultAccess' => $parameters['user']->agent_access ?? 'full',
                'redirectHost' => $redirectHost,
                'redirectIsLocal' => in_array($redirectHost, ['localhost', '127.0.0.1', '[::1]'], true),
            ]);
        });

        RateLimiter::for('mcp-oauth', fn (Request $request) => Limit::perMinute(20)->by('mcp-oauth:'.$request->ip()));

        RateLimiter::for('mcp', function (Request $request) {
            $connection = $request->attributes->get('mcp_connection');

            return Limit::perMinute(120)->by($connection ? 'mcp:'.$connection->id : 'mcp-ip:'.$request->ip());
        });
    }
}
