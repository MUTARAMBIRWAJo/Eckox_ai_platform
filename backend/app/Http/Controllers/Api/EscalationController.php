<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiDecision;
use App\Models\InboundMessage;
use App\Models\OutboundMessage;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EscalationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AiDecision::where('decision_type', 'escalate')
            ->with(['lead']);

        if ($request->filled('reason')) {
            $query->where('intent', $request->input('reason'));
        }

        $decisions = $query->latest()->get();

        $data = $decisions->map(function ($dec) {
            $lead = $dec->lead;
            $history = [];

            if ($lead) {
                $inbound = InboundMessage::where('lead_id', $lead->id)->get()->map(function ($msg) {
                    return [
                        'sender' => 'lead',
                        'content' => $msg->content,
                        'timestamp' => $msg->created_at->toIso8601String(),
                    ];
                });

                $outbound = OutboundMessage::where('lead_id', $lead->id)->get()->map(function ($msg) {
                    return [
                        'sender' => $msg->sender ?? 'assistant',
                        'content' => $msg->content,
                        'timestamp' => $msg->created_at->toIso8601String(),
                    ];
                });

                $history = $inbound->concat($outbound)->sortBy('timestamp')->values()->all();
            }

            return [
                'id' => $dec->id,
                'traceId' => $dec->trace_id,
                'leadId' => $dec->lead_id,
                'leadName' => $lead?->name ?? 'Unknown Client',
                'reason' => $dec->intent ?? 'guardrail_failure',
                'content' => $dec->response['reply_text'] ?? ($dec->response['error'] ?? 'Escalated due to system policy.'),
                'region' => $dec->region ?? ($lead?->region ?? 'europe'),
                'language' => $lead?->language ?? 'en',
                'history' => $history,
                'createdAt' => $dec->created_at->toIso8601String(),
            ];
        });

        return response()->json($data);
    }

    public function takeover(Request $request, string $traceId): JsonResponse
    {
        $request->validate([
            'reply' => 'required|string',
        ]);

        $decision = AiDecision::where('trace_id', $traceId)->firstOrFail();
        
        // Mark decision as taken over
        $decision->update([
            'decision_type' => 'reply',
            'intent' => 'manual_override'
        ]);

        // Send outbound message
        OutboundMessage::create([
            'lead_id' => $decision->lead_id,
            'channel' => 'whatsapp',
            'recipient' => $decision->lead?->phone ?? '+1234567890',
            'content' => $request->input('reply'),
            'status' => 'sent',
            'trace_id' => $traceId
        ]);

        return response()->json(['success' => true]);
    }
}
