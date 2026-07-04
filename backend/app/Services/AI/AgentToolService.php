<?php

namespace App\Services\AI;

use App\Models\KnowledgeBase;
use App\Models\Lead;
use App\Models\Product;
use App\Services\Documents\DocumentGenerationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Layer 7 — Tool-Calling Architecture
 *
 * All fact-bearing operations the LLM may request are implemented here as
 * discrete, typed tools. The LLM NEVER generates the values — it only chooses
 * which tool to call and with what validated inputs. Every call is logged
 * against the conversation trace_id for full auditability.
 */
class AgentToolService
{
    /** Registry of available tools returned to the LLM as context. */
    public const TOOL_DEFINITIONS = [
        [
            'name'        => 'get_product_price',
            'description' => 'Returns current price for a product by SKU in region currency. Never guess prices.',
            'parameters'  => ['sku' => 'string', 'region' => 'string (africa|europe)'],
        ],
        [
            'name'        => 'check_stock',
            'description' => 'Returns current stock level for a product SKU.',
            'parameters'  => ['sku' => 'string'],
        ],
        [
            'name'        => 'get_product_spec',
            'description' => 'Returns processor, RAM, and storage specs for a given product SKU.',
            'parameters'  => ['sku' => 'string'],
        ],
        [
            'name'        => 'get_compliance_doc',
            'description' => 'Returns compliance or SLA passage from Knowledge Base for a region/doc_type.',
            'parameters'  => ['region' => 'string', 'doc_type' => 'string (compliance|sla|faq)'],
        ],
        [
            'name'        => 'create_quote_pdf',
            'description' => 'Generates a PDF quotation using tool-sourced price only.',
            'parameters'  => ['lead_id' => 'int', 'sku' => 'string', 'region' => 'string', 'quantity' => 'int'],
        ],
        [
            'name'        => 'generate_invoice',
            'description' => 'Generates a PDF invoice. Price must come from get_product_price, not LLM.',
            'parameters'  => ['lead_id' => 'int', 'sku' => 'string', 'region' => 'string', 'quantity' => 'int'],
        ],
        [
            'name'        => 'escalate_to_human',
            'description' => 'Creates a human escalation record when LLM cannot answer from context.',
            'parameters'  => ['lead_id' => 'int|null', 'reason' => 'string', 'trace_id' => 'string'],
        ],
    ];

    public function __construct(
        private readonly DocumentGenerationEngine $documentEngine,
    ) {}

    /**
     * Dispatch a tool call by name with validated inputs.
     * Returns a structured, typed result array.
     * All calls are logged with trace_id for audit.
     */
    public function dispatch(string $toolName, array $inputs, string $traceId): array
    {
        $startedAt = microtime(true);

        $result = match ($toolName) {
            'get_product_price'  => $this->getProductPrice($inputs),
            'check_stock'        => $this->checkStock($inputs),
            'get_product_spec'   => $this->getProductSpec($inputs),
            'get_compliance_doc' => $this->getComplianceDoc($inputs),
            'create_quote_pdf'   => $this->createQuotePdf($inputs),
            'generate_invoice'   => $this->generateInvoice($inputs),
            'escalate_to_human'  => $this->escalateToHuman($inputs, $traceId),
            default              => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::channel('production')->info('AgentTool dispatched', [
            'trace_id'    => $traceId,
            'tool'        => $toolName,
            'inputs'      => $this->sanitizeForLog($inputs),
            'output_keys' => array_keys($result),
            'latency_ms'  => $latencyMs,
        ]);

        return $result;
    }

    // =========================================================================
    // Tool implementations
    // =========================================================================

    private function getProductPrice(array $inputs): array
    {
        $sku    = (string) ($inputs['sku']    ?? throw new \InvalidArgumentException('get_product_price requires sku'));
        $region = (string) ($inputs['region'] ?? throw new \InvalidArgumentException('get_product_price requires region'));

        $product = Product::where('sku', $sku)->first();
        if (! $product) {
            return ['found' => false, 'sku' => $sku, 'message' => "No product found with SKU {$sku}."];
        }

        $price    = $region === 'europe' ? (float) $product->price_eur : (float) $product->price_usd;
        $currency = $region === 'europe' ? 'EUR' : 'USD';

        return [
            'found'    => true,
            'sku'      => $product->sku,
            'name'     => $product->name,
            'price'    => $price,
            'currency' => $currency,
            'region'   => $region,
            'source'   => "product:{$product->sku}",
        ];
    }

    private function checkStock(array $inputs): array
    {
        $sku     = (string) ($inputs['sku'] ?? throw new \InvalidArgumentException('check_stock requires sku'));
        $product = Product::where('sku', $sku)->first();

        if (! $product) {
            return ['found' => false, 'sku' => $sku, 'in_stock' => false, 'stock_level' => 0,
                    'message' => "No product found with SKU {$sku}."];
        }

        return [
            'found'       => true,
            'sku'         => $product->sku,
            'name'        => $product->name,
            'in_stock'    => $product->stock_level > 0,
            'stock_level' => (int) $product->stock_level,
            'source'      => "product:{$product->sku}",
        ];
    }

    private function getProductSpec(array $inputs): array
    {
        $sku     = (string) ($inputs['sku'] ?? throw new \InvalidArgumentException('get_product_spec requires sku'));
        $product = Product::where('sku', $sku)->first();

        if (! $product) {
            return ['found' => false, 'sku' => $sku, 'message' => "No product found with SKU {$sku}."];
        }

        return [
            'found'          => true,
            'sku'            => $product->sku,
            'name'           => $product->name,
            'spec_processor' => $product->spec_processor,
            'spec_ram'       => $product->spec_ram,
            'spec_storage'   => $product->spec_storage,
            'source'         => "product:{$product->sku}",
        ];
    }

    private function getComplianceDoc(array $inputs): array
    {
        $region  = (string) ($inputs['region']   ?? throw new \InvalidArgumentException('get_compliance_doc requires region'));
        $docType = (string) ($inputs['doc_type'] ?? throw new \InvalidArgumentException('get_compliance_doc requires doc_type'));

        $records = KnowledgeBase::where('region', $region)->where('doc_type', $docType)->active()->get();

        if ($records->isEmpty()) {
            return ['found' => false, 'region' => $region, 'doc_type' => $docType,
                    'message' => "No {$docType} document for region {$region}."];
        }

        return [
            'found'    => true,
            'region'   => $region,
            'doc_type' => $docType,
            'passages' => $records->map(fn ($r) => [
                'id'      => $r->id,
                'content' => $r->content,
                'source'  => "kb:{$r->id}",
            ])->values()->toArray(),
        ];
    }

    private function createQuotePdf(array $inputs): array
    {
        $leadId   = (int)    ($inputs['lead_id']  ?? throw new \InvalidArgumentException('create_quote_pdf requires lead_id'));
        $sku      = (string) ($inputs['sku']      ?? throw new \InvalidArgumentException('create_quote_pdf requires sku'));
        $region   = (string) ($inputs['region']   ?? throw new \InvalidArgumentException('create_quote_pdf requires region'));
        $quantity = (int)    ($inputs['quantity']  ?? 1);

        // Always re-fetch price from DB — never trust a price value from LLM inputs
        $priceResult = $this->getProductPrice(['sku' => $sku, 'region' => $region]);
        if (! $priceResult['found']) {
            return ['success' => false, 'message' => "Cannot generate quote: {$priceResult['message']}"];
        }

        $lead = Lead::find($leadId);
        if (! $lead) {
            return ['success' => false, 'message' => "Lead {$leadId} not found."];
        }

        $document = $this->documentEngine->generateQuote($lead, [
            'sku'      => $sku,
            'price'    => $priceResult['price'],
            'currency' => $priceResult['currency'],
            'quantity' => $quantity,
            'name'     => $priceResult['name'],
        ], $region);

        return [
            'success'  => true,
            'doc_type' => 'quote',
            'path'     => $document->file_url ?? '',
            'price'    => $priceResult['price'],
            'currency' => $priceResult['currency'],
            'source'   => "product:{$sku}",
        ];
    }

    private function generateInvoice(array $inputs): array
    {
        $leadId   = (int)    ($inputs['lead_id']  ?? throw new \InvalidArgumentException('generate_invoice requires lead_id'));
        $sku      = (string) ($inputs['sku']      ?? throw new \InvalidArgumentException('generate_invoice requires sku'));
        $region   = (string) ($inputs['region']   ?? throw new \InvalidArgumentException('generate_invoice requires region'));
        $quantity = (int)    ($inputs['quantity']  ?? 1);

        $priceResult = $this->getProductPrice(['sku' => $sku, 'region' => $region]);
        if (! $priceResult['found']) {
            return ['success' => false, 'message' => "Cannot generate invoice: {$priceResult['message']}"];
        }

        $lead = Lead::find($leadId);
        if (! $lead) {
            return ['success' => false, 'message' => "Lead {$leadId} not found."];
        }

        $document = $this->documentEngine->generateInvoice($lead, [
            'sku'      => $sku,
            'price'    => $priceResult['price'],
            'currency' => $priceResult['currency'],
            'quantity' => $quantity,
            'name'     => $priceResult['name'],
        ], $region);

        return [
            'success'  => true,
            'doc_type' => 'invoice',
            'path'     => $document->file_url ?? '',
            'price'    => $priceResult['price'],
            'currency' => $priceResult['currency'],
            'source'   => "product:{$sku}",
        ];
    }

    private function escalateToHuman(array $inputs, string $traceId): array
    {
        $leadId = isset($inputs['lead_id']) ? (int) $inputs['lead_id'] : null;
        $reason = (string) ($inputs['reason'] ?? 'No reason provided');

        DB::table('ai_decisions')->insert([
            'id'            => (string) Str::uuid(),
            'lead_id'       => $leadId,
            'trace_id'      => $traceId,
            'intent'        => 'complaint_legal',
            'region'        => 'unknown',
            'decision_type' => 'escalate',
            'confidence'    => 1.0,
            'prompt'        => json_encode(['tool_escalation' => true]),
            'response'      => json_encode([
                'reply_text'  => 'Let me confirm this with our team and follow up shortly.',
                'escalate'    => true,
                'reason'      => $reason,
                'cited_facts' => [],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::channel('production')->warning('Human escalation tool invoked', [
            'lead_id'  => $leadId,
            'reason'   => $reason,
            'trace_id' => $traceId,
        ]);

        return [
            'escalated'  => true,
            'reason'     => $reason,
            'reply_text' => 'Let me confirm this with our team and follow up shortly.',
        ];
    }

    /** Strip PII-bearing fields before writing inputs to logs. */
    private function sanitizeForLog(array $inputs): array
    {
        return array_diff_key($inputs, array_flip(['email', 'phone', 'address', 'name']));
    }
}
