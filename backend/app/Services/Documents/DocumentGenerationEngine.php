<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Lead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentGenerationEngine
{
    /**
     * Generate a region-aware quote PDF.
     */
    public function generateQuote(
        Lead $lead,
        array $quoteData,
        string $region,
        string $traceId = '',
    ): Document {
        return $this->generate('quote', $lead, $quoteData, $region, $traceId);
    }

    /**
     * Generate a region-aware invoice PDF.
     */
    public function generateInvoice(
        Lead $lead,
        array $invoiceData,
        string $region,
        string $traceId = '',
    ): Document {
        return $this->generate('invoice', $lead, $invoiceData, $region, $traceId);
    }

    /**
     * Generate a compliance certificate (Europe only).
     */
    public function generateCertificate(
        Lead $lead,
        array $certData,
        string $traceId = '',
    ): Document {
        return $this->generate('certificate', $lead, $certData, 'europe', $traceId);
    }

    /**
     * Core generation method.
     */
    private function generate(
        string $type,
        Lead $lead,
        array $data,
        string $region,
        string $traceId,
    ): Document {
        $currency = $region === 'europe' ? 'EUR' : 'USD';
        $paymentMethod = $region === 'europe'
            ? 'Stripe / Bank Transfer (SEPA)'
            : 'Mobile Money (MTN/Orange) / Flutterwave';

        $deliveryDays = $region === 'europe' ? 10 : 15;

        $legalFooter = $region === 'europe'
            ? 'This document is compliant with CE, ISO 17025 and GDPR regulations. All data handled under GDPR Article 6(1)(b).'
            : 'This document is governed by applicable local commercial law. All prices in USD.';

        $viewData = array_merge($data, [
            'lead'           => $lead,
            'currency'       => $currency,
            'payment_method' => $paymentMethod,
            'delivery_days'  => $deliveryDays,
            'legal_footer'   => $legalFooter,
            'region'         => $region,
            'generated_at'   => now()->format('d/m/Y'),
            'reference'      => strtoupper($type) . '-' . strtoupper(Str::random(8)),
            'trace_id'       => $traceId,
        ]);

        $view     = "documents.{$type}.{$region}";
        $fallback = "documents.{$type}.default";

        $html = view()->exists($view)
            ? view($view, $viewData)->render()
            : view($fallback, $viewData)->render();

        $pdf      = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $filename = "documents/{$lead->id}/{$type}_" . now()->format('Ymd_His') . ".pdf";

        Storage::disk('local')->put($filename, $pdf->output());

        $fileUrl = Storage::disk('local')->path($filename);

        Log::channel('production')->info('Document generated', [
            'type'     => $type,
            'region'   => $region,
            'lead_id'  => $lead->id,
            'trace_id' => $traceId,
            'file'     => $filename,
        ]);

        return Document::create([
            'id'       => (string) Str::uuid(),
            'lead_id'  => $lead->id,
            'type'     => $type,
            'file_url' => $fileUrl,
            'currency' => $currency,
            'region'   => $region,
            'metadata' => [
                'trace_id'  => $traceId,
                'reference' => $viewData['reference'],
                'filename'  => $filename,
            ],
        ]);
    }
}
