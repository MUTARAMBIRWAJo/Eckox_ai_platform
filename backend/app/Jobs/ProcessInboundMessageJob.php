<?php

namespace App\Jobs;

use App\Events\InboundMessageReceived;
use App\Models\InboundMessage;
use App\Models\Lead;
use App\Services\AI\AIContextBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $channel,
        public readonly string $sender,
        public readonly string $content,
        public readonly array  $metadata = [],
        public readonly string $traceId  = '',
    ) {}

    public function handle(AIContextBuilderService $contextBuilder): void
    {
        $traceId = $this->traceId ?: (string) Str::uuid();

        Log::channel('production')->info('Processing inbound message', [
            'channel'  => $this->channel,
            'sender'   => $this->sender,
            'trace_id' => $traceId,
        ]);

        // 1. Detect country and language from metadata
        $country  = $this->metadata['country'] ?? null;
        $language = $contextBuilder->detectLanguage($this->content);
        $region   = $country ? $contextBuilder->detectRegion($country) : null;

        // 2. Find or create the lead from this sender
        $lead = Lead::firstOrCreate(
            match ($this->channel) {
                'email'    => ['email' => $this->sender],
                default    => ['phone' => $this->sender],
            },
            [
                'name'           => $this->metadata['name'] ?? 'Unknown',
                'status'         => 'new',
                'source_channel' => $this->channel,
                'region'         => $region,
                'language'       => $language,
            ]
        );

        // Update channel tracking
        $lead->update([
            'last_message_at' => now(),
            'source_channel'  => $this->channel,
        ]);

        // 3. Store the raw inbound message (immutable event store)
        $message = InboundMessage::create([
            'id'       => (string) Str::uuid(),
            'lead_id'  => $lead->id,
            'channel'  => $this->channel,
            'sender'   => $this->sender,
            'content'  => $this->content,
            'metadata' => $this->metadata,
            'country'  => $country,
            'language' => $language,
        ]);

        // 4. Emit event to trigger AI pipeline
        event(new InboundMessageReceived($message));

        // 5. Dispatch AI decision job on high-priority queue
        RunAIDecisionJob::dispatch($message->id, $lead->id, $traceId)
            ->onQueue('ai-decision');
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('production')->error('ProcessInboundMessageJob failed', [
            'channel'   => $this->channel,
            'sender'    => $this->sender,
            'trace_id'  => $this->traceId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
