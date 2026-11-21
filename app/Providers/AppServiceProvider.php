<?php

namespace App\Providers;

use App\Services\HospitalAccessService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HospitalAccessService::class);
    }

    public function boot(): void
    {
        if (filter_var(env('BHN_PROFILE_DB', false), FILTER_VALIDATE_BOOLEAN)) {
            $queryCount = 0;
            $queryTime = 0.0;

            DB::listen(function ($query) use (&$queryCount, &$queryTime) {
                $queryCount++;
                $queryTime += $query->time;
            });

            register_shutdown_function(function () use (&$queryCount, &$queryTime) {
                error_log(sprintf('BHN_DB_PROFILE queries=%d db_ms=%.1f', $queryCount, $queryTime));
            });
        }

        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many login attempts. Please try again shortly.',
                ], 429, $headers);
            });
        });

        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
