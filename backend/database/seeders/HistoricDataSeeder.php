<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HistoricDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Illuminate\Foundation\Application::getInstance()->make(\Faker\Generator::class);

        // Fetch valid user IDs
        $userIds = DB::table('users')->pluck('id')->toArray();
        if (empty($userIds)) {
            $userIds = [1];
        }

        // Fetch product SKUs
        $skus = DB::table('products')->pluck('sku')->toArray();
        if (empty($skus)) {
            $skus = ['EX-HPLC-700', 'SKU-PROC-X'];
        }

        $now = Carbon::now();
        $startDate = Carbon::now()->subMonths(6);

        $activities = [];
        $decisions = [];
        $actionsLogs = [];

        $activityTypes = ['email', 'call', 'meeting', 'note'];
        $activityDescs = [
            'email' => ['Follow up email sent.', 'Received reply from client.', 'Quotation documents sent via email.'],
            'call' => ['Call completed to discuss pricing.', 'Attempted call, left voicemail.', 'Brief discussion about specs.'],
            'meeting' => ['Product demo meeting completed.', 'Introductory session.', 'Contract negotiation meeting.'],
            'note' => ['Lead is very interested in HPLC.', 'Client requested discount.', 'Verification of compliance standards needed.'],
        ];

        echo "Generating 150 historical leads, activities, and AI logs...\n";

        for ($i = 0; $i < 150; $i++) {
            $createdAt = Carbon::createFromTimestamp(rand($startDate->timestamp, $now->timestamp));
            $updatedAt = (clone $createdAt)->addDays(rand(1, 10));
            if ($updatedAt->gt($now)) {
                $updatedAt = $now;
            }

            // Status weights: new (20%), contacted (30%), qualified (40%), lost (10%)
            $rand = rand(1, 100);
            if ($rand <= 20) {
                $status = 'new';
            } elseif ($rand <= 50) {
                $status = 'contacted';
            } elseif ($rand <= 90) {
                $status = 'qualified';
            } else {
                $status = 'lost';
            }

            $region = rand(1, 100) <= 65 ? 'africa' : 'europe';
            $assignedTo = $userIds[array_rand($userIds)];

            $leadId = DB::table('leads')->insertGetId([
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'status' => $status,
                'assigned_to' => $assignedTo,
                'source_channel' => rand(1, 100) <= 60 ? 'email' : 'whatsapp',
                'region' => $region,
                'language' => rand(1, 100) <= 80 ? 'en' : 'fr',
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            // Generate 1-4 activities per lead
            $numAct = rand(1, 4);
            for ($j = 0; $j < $numAct; $j++) {
                $actTime = (clone $createdAt)->addHours(rand(1, 100));
                if ($actTime->gt($now)) {
                    $actTime = $now;
                }
                $type = $activityTypes[array_rand($activityTypes)];

                $activities[] = [
                    'lead_id' => $leadId,
                    'user_id' => $assignedTo,
                    'type' => $type,
                    'description' => $activityDescs[$type][array_rand($activityDescs[$type])],
                    'created_at' => $actTime,
                    'updated_at' => $actTime,
                ];
            }

            // Generate 2-5 AI decisions and logs per lead
            $numDecisions = rand(2, 5);
            for ($k = 0; $k < $numDecisions; $k++) {
                $decTime = (clone $createdAt)->addHours(rand(1, 120));
                if ($decTime->gt($now)) {
                    $decTime = $now;
                }

                $traceId = (string) Str::uuid();

                // Decision weights: reply (60%), generate_quote (25%), generate_invoice (10%), escalate (5%)
                $randDec = rand(1, 100);
                if ($randDec <= 60) {
                    $decType = 'reply';
                    $replyText = 'Hi, thanks for reaching out. We have registered your request.';
                } elseif ($randDec <= 85) {
                    $decType = 'generate_quote';
                    $replyText = 'Quotation EX-Q-' . rand(1000, 9999) . ' has been generated.';
                } elseif ($randDec <= 95) {
                    $decType = 'generate_invoice';
                    $replyText = 'Invoice EX-I-' . rand(1000, 9999) . ' has been sent.';
                } else {
                    $decType = 'escalate';
                    $replyText = 'Let me confirm this with our team and follow up shortly.';
                }

                $prov = rand(1, 100) <= 70 ? 'groq' : (rand(1, 100) <= 80 ? 'openai' : 'anthropic');
                $model = $prov === 'groq' ? 'llama-3.3-70b-versatile' : ($prov === 'openai' ? 'gpt-4o-mini' : 'claude-3-5-sonnet');
                
                // Latency range: groq (180-350ms), openai (600-1100ms), anthropic (900-1600ms)
                $latency = $prov === 'groq' ? rand(180, 350) : ($prov === 'openai' ? rand(600, 1100) : rand(900, 1600));

                $decisions[] = [
                    'id' => (string) Str::uuid(),
                    'lead_id' => $leadId,
                    'trace_id' => $traceId,
                    'intent' => rand(1, 100) <= 60 ? 'buy_intent' : 'general',
                    'region' => $region,
                    'decision_type' => $decType,
                    'confidence' => rand(70, 99) / 100.0,
                    'prompt' => json_encode(['message' => 'Query content mock']),
                    'response' => json_encode(['reply_text' => $replyText, 'decision' => $decType]),
                    'created_at' => $decTime,
                    'updated_at' => $decTime,
                ];

                // Observable tokens & costs
                $promptTokens = rand(300, 800);
                $completionTokens = rand(50, 250);
                $cost = 0.0;
                if ($prov === 'groq') {
                    $cost = (($promptTokens * 0.59) + ($completionTokens * 0.79)) / 1_000_000.0;
                } elseif ($prov === 'openai') {
                    $cost = (($promptTokens * 0.15) + ($completionTokens * 0.60)) / 1_000_000.0;
                } elseif ($prov === 'anthropic') {
                    $cost = (($promptTokens * 3.0) + ($completionTokens * 15.0)) / 1_000_000.0;
                }

                $actionsLogs[] = [
                    'id' => (string) Str::uuid(),
                    'trace_id' => $traceId,
                    'lead_id' => $leadId,
                    'node_path' => json_encode(['intent_classifier', 'memory_loader', 'rag_retrieval', 'llm_reasoning', 'guardrail_validation']),
                    'latency_ms' => json_encode(['llm_reasoning' => $latency]),
                    'llm_provider' => $prov,
                    'provider' => $prov,
                    'model_name' => $model,
                    'tokens_prompt' => $promptTokens,
                    'tokens_completion' => $completionTokens,
                    'cost_usd' => $cost,
                    'total_latency_ms' => $latency,
                    'intent' => 'general',
                    'decision_type' => $decType,
                    'created_at' => $decTime,
                    'updated_at' => $decTime,
                ];
            }
        }

        // Chunk insert activities, decisions, and action logs
        foreach (array_chunk($activities, 100) as $chunk) {
            DB::table('lead_activities')->insert($chunk);
        }

        foreach (array_chunk($decisions, 100) as $chunk) {
            DB::table('ai_decisions')->insert($chunk);
        }

        foreach (array_chunk($actionsLogs, 100) as $chunk) {
            DB::table('ai_actions_log')->insert($chunk);
        }

        echo "Seeding completed successfully! 150 leads, " . count($activities) . " activities, " . count($decisions) . " AI decisions generated.\n";
    }
}
