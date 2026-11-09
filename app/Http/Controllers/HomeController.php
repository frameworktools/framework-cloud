<?php

namespace App\Http\Controllers;

use App\Support\DeferHidden;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The home page. It reads only code and config, so for signed-out visitors
 * it is the same page until the next deploy: in production it is rendered
 * once per release and served from the cache. Signed-in visitors see their
 * own buttons and header, so theirs is rendered each time.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->user() || ! app()->isProduction()) {
            return response(self::render());
        }

        return response(Cache::remember('home:'.self::release(), now()->addWeek(), fn () => self::render()));
    }

    /** Hidden demo panels go out as templates, so the browser parses them when shown */
    public static function render(): string
    {
        return DeferHidden::apply(view('welcome')->render());
    }

    /**
     * Changes with every deploy: each release has its own path, built assets,
     * and cached config.
     */
    public static function release(): string
    {
        $stamp = fn (string $path) => is_file($path) ? (string) filemtime($path) : '';

        return hash('xxh128', base_path()
            .$stamp(public_path('build/manifest.json'))
            .$stamp(base_path('bootstrap/cache/config.php')));
    }
}
