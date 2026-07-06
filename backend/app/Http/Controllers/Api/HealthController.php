<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\Router\LLMRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __construct(private readonly LLMRouter $llmRouter) {}

    /**
     * GET /api/health/ai
     */
    public function health(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // 1. LLM Providers Health
        foreach ($this->llmRouter->allProviders() as $name => $provider) {
            $health = $provider->health();
            $checks[$name] = $health;
            if (!$health['healthy']) {
                $status = 'degraded';
            }
        }

        // 2. Redis
        try {
            $redisPing = Redis::ping();
            $checks['redis'] = ['healthy' => true, 'ping' => $redisPing];
        } catch (\Throwable $e) {
            $checks['redis'] = ['healthy' => false, 'error' => $e->getMessage()];
            $status = 'degraded';
        }

        // 3. Supabase DB
        try {
            DB::connection()->getPdo();
            $checks['supabase_db'] = ['healthy' => true];
        } catch (\Throwable $e) {
            $checks['supabase_db'] = ['healthy' => false, 'error' => $e->getMessage()];
            $status = 'degraded';
        }

        // 4. Supabase Storage
        try {
            Storage::disk('supabase')->exists('health-check-temp.txt');
            $checks['supabase_storage'] = ['healthy' => true];
        } catch (\Throwable $e) {
            $checks['supabase_storage'] = ['healthy' => false, 'error' => $e->getMessage()];
        }

        // 5. Queues
        try {
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $checks['queues'] = [
                'healthy' => true,
                'pending_jobs' => $queueSize,
                'failed_jobs' => $failedJobs
            ];
        } catch (\Throwable $e) {
            $checks['queues'] = ['healthy' => false, 'error' => $e->getMessage()];
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'diagnostics' => $this->llmRouter->diagnostics(),
        ], $status === 'healthy' ? 200 : 500);
    }
}
