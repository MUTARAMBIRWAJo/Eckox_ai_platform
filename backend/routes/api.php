<?php

use App\Http\Controllers\Api\AIChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailWebhookController;
use App\Http\Controllers\Api\LeadController;
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
    // Lead CRUD
    Route::get('/leads', [LeadController::class, 'index']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::get('/leads/{id}', [LeadController::class, 'show']);
    Route::patch('/leads/{id}', [LeadController::class, 'update']);
    Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
    Route::post('/leads/{id}/activity', [LeadController::class, 'logActivity']);

    // AI Chat Streaming
    Route::post('/ai/chat/stream', [AIChatController::class, 'stream'])->middleware('throttle:ai_streaming');
});

// ─── EASE Webhook Endpoints (no Sanctum auth — signed via verify token / webhook secret) ───
Route::prefix('webhooks')->group(function () {
    // WhatsApp (360Dialog)
    Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/whatsapp', [WhatsAppWebhookController::class, 'receive']);

    // Email inbound parse (Mailgun / SendGrid / Postmark compatible)
    Route::post('/email', [EmailWebhookController::class, 'receive']);
});

