<?php

namespace App\Providers;

use App\Events\ActionExecuted;
use App\Events\AIDecisionGenerated;
use App\Events\InboundMessageReceived;
use App\Events\LeadScored;
use App\Listeners\LogActionExecuted;
use App\Listeners\LogAIDecision;
use App\Listeners\UpdateLeadOnInbound;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        InboundMessageReceived::class => [
            UpdateLeadOnInbound::class,
        ],
        AIDecisionGenerated::class => [
            LogAIDecision::class,
        ],
        ActionExecuted::class => [
            LogActionExecuted::class,
        ],
        LeadScored::class => [],
    ];

    public function boot(): void {}
}
