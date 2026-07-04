<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewCorsTest extends TestCase
{
    /**
     * Positive Test: Valid Vercel preview deployment matching correct origin layout
     */
    public function test_allows_valid_preview_origin_with_credentials(): void
    {
        $origin = 'https://eckox-abc123xyz-mutarambirwaj1-gmailcoms-projects.vercel.app';

        $response = $this->withHeaders([
            'Origin' => $origin,
        ])->getJson('/api/health');

        $response->assertHeader('Access-Control-Allow-Origin', $origin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Negative Test: Attackers Vercel origin with similar but malicious format
     */
    public function test_denies_invalid_preview_origin_headers(): void
    {
        $origin = 'https://eckox-abc123xyz-attacker.vercel.app';

        $response = $this->withHeaders([
            'Origin' => $origin,
        ])->getJson('/api/health');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }
}
