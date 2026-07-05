<?php

// ── Health check endpoint (public - used by Fly.io health checks) ─────────────
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $db = 'connected';
    } catch (\Exception $e) {
        $db = 'disconnected: ' . $e->getMessage();
    }
    $status = str_contains($db, 'connected') ? 'ok' : 'degraded';
    $code   = $status === 'ok' ? 200 : 500;
    return response()->json([
        'status'    => $status,
        'database'  => $db,
        'timestamp' => now()->toISOString(),
        'app'       => config('app.name'),
    ], $code);
});


use App\Http\Controllers\Api\AIChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardStatsController;
use App\Http\Controllers\Api\EmailWebhookController;
use App\Http\Controllers\Api\EscalationController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MarketingApprovalController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TraceController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'profile']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // ── Lead CRUD ──────────────────────────────────────────────────────────────
    Route::get('/leads', [LeadController::class, 'index']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::get('/leads/{id}', [LeadController::class, 'show']);
    Route::patch('/leads/{id}', [LeadController::class, 'update']);
    Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
    Route::post('/leads/{id}/activity', [LeadController::class, 'logActivity']);

    // ── AI Chat Streaming ──────────────────────────────────────────────────────
    Route::post('/ai/chat/stream', [AIChatController::class, 'stream'])->middleware('throttle:ai_streaming');

    // ── Escalation Queue ───────────────────────────────────────────────────────
    Route::get('/escalations', [EscalationController::class, 'index']);
    Route::post('/escalations/{traceId}/takeover', [EscalationController::class, 'takeover']);

    // ── Observability / Trace Viewer ───────────────────────────────────────────
    Route::get('/traces/{traceId}', [TraceController::class, 'show']);

    // ── Knowledge Base (CRUD + Semantic Query Tester) ─────────────────────────
    // Note: /test must be defined before /{knowledgeBase} to avoid route collision
    Route::post('/knowledge-base/test', [KnowledgeBaseController::class, 'test']);
    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index']);
    Route::post('/knowledge-base', [KnowledgeBaseController::class, 'store']);
    Route::put('/knowledge-base/{knowledgeBase}', [KnowledgeBaseController::class, 'update']);
    Route::delete('/knowledge-base/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy']);

    // ── Product Catalog + Audit Logs ───────────────────────────────────────────
    // Note: /audit-logs must be defined before /{product} to avoid route collision
    Route::get('/products/audit-logs', [ProductController::class, 'auditLogs']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // ── Dashboard Stats & Provider Health ─────────────────────────────────────
    Route::get('/dashboard/stats', [DashboardStatsController::class, 'stats']);
    Route::get('/dashboard/provider-health', [DashboardStatsController::class, 'providerHealth']);

    // ── Marketing Approvals ────────────────────────────────────────────────────
    Route::get('/marketing-approvals', [MarketingApprovalController::class, 'index']);
    Route::post('/marketing-approvals/{id}/approve', [MarketingApprovalController::class, 'approve']);
    Route::post('/marketing-approvals/{id}/reject', [MarketingApprovalController::class, 'reject']);
});

// ─── Webhook Endpoints (no Sanctum auth — signed via verify token / webhook secret) ───
Route::prefix('webhooks')->group(function () {
    // WhatsApp (360Dialog)
    Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/whatsapp', [WhatsAppWebhookController::class, 'receive']);

    // Email inbound parse (Mailgun / SendGrid / Postmark compatible)
    Route::post('/email', [EmailWebhookController::class, 'receive']);
});
