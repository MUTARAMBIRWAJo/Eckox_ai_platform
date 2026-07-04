<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('db.connector.pgsql', function () {
            return new class extends \Illuminate\Database\Connectors\PostgresConnector {
                public function connect(array $config) {
                    return retry(3, function () use ($config) {
                        return parent::connect($config);
                    }, 100);
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai_streaming', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return Limit::perMinute(10)->by($request->ip());
            }

            if ($user->hasAnyRole(['admin', 'super-admin'])) {
                return Limit::perMinute(60)->by($user->id);
            }

            return Limit::perMinute(10)->by($user->id);
        });

        \Illuminate\Support\Facades\DB::listen(function ($query) {
            if ($query->time > 500) {
                $userId = auth()->id();
                $route = request() ? request()->getRequestUri() : 'console';
                $traceId = class_exists(\Illuminate\Support\Facades\Context::class)
                    ? \Illuminate\Support\Facades\Context::get('trace_id')
                    : null;

                // Scrub raw values to generate a structural signature
                $sqlSignature = preg_replace([
                    '/\b(values|in)\s*\([^\)]+\)/i',
                    '/\b(password|token|secret|key|auth|api_key)\s*=\s*[\'"][^\'"]+[\'"]/i',
                    '/[\'"][^\'"]+[\'"]/',
                    '/\b\d+\b/'
                ], [
                    '$1(?)',
                    '$1 = ?',
                    '?',
                    '?'
                ], $query->sql);

                \Illuminate\Support\Facades\Log::warning('Slow database query signature detected', [
                    'sql_signature' => $sqlSignature,
                    'time_ms' => $query->time,
                    'route' => $route,
                    'user_id' => $userId,
                    'trace_id' => $traceId,
                ]);
            }
        });
    }
}
