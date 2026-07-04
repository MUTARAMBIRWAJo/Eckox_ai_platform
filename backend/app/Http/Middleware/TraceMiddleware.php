<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TraceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-ID') ?? (string) Str::uuid();

        // Inject trace ID into request headers so downstreams can access it
        $request->headers->set('X-Trace-ID', $traceId);

        // Share the trace ID globally via Laravel Context (propagates to queues)
        if (class_exists(Context::class)) {
            Context::add('trace_id', $traceId);
        }

        $startTime = microtime(true);
        $request->attributes->set('trace_start_time', $startTime);

        $response = $next($request);

        // Add trace ID to response header
        $response->headers->set('X-Trace-ID', $traceId);

        return $response;
    }

    /**
     * Perform any tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->attributes->get('trace_start_time');
        $durationMs = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0;
        
        $traceId = class_exists(Context::class) ? Context::get('trace_id') : null;
        $user = $request->user();

        Log::info('API Request Lifecycle', [
            'trace_id' => $traceId,
            'user_id' => $user ? $user->id : null,
            'route' => $request->route() ? $request->route()->getName() : 'anonymous',
            'method' => $request->method(),
            'duration_ms' => $durationMs,
            'memory_usage_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
            'status_code' => $response->getStatusCode(),
        ]);
    }
}
