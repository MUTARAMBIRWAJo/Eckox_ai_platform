<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitorMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('start_time', microtime(true));
        return $next($request);
    }

    /**
     * Perform any tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->attributes->get('start_time');
        if ($startTime) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            
            // Only log if request is slow (e.g. > 1000ms) to avoid duplicate telemetry noise
            if ($durationMs > 1000) {
                $memoryMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
                $traceId = class_exists(Context::class) ? Context::get('trace_id') : null;
                $user = $request->user();

                Log::warning('Slow API Request Detected', [
                    'uri' => $request->getRequestUri(),
                    'method' => $request->getMethod(),
                    'status' => $response->getStatusCode(),
                    'latency_ms' => $durationMs,
                    'memory_usage_mb' => $memoryMb,
                    'user_id' => $user ? $user->id : null,
                    'trace_id' => $traceId,
                ]);
            }
        }
    }
}
