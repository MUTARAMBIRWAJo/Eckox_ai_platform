import { apiClient, APIResponse } from './client';

// ── Response Types ────────────────────────────────────────────────────────────

export interface DashboardStats {
  /** Pipeline stages from backend: [{ stage: string, count: number }] */
  pipeline: { stage: string; count: number }[];
  /** Average LLM reasoning latency across all decisions (ms) */
  avgLatencyMs: number;
  /** AI quote conversion rate as a percentage (0-100) */
  conversionRate: number;
  /** Total number of AI decisions logged */
  totalDecisions: number;
}

export interface ProviderHealth {
  name: string;
  volume: number;
  failovers: number;
  latency: number;
}

// ── Dashboard API ─────────────────────────────────────────────────────────────

export class DashboardAPI {
  /**
   * GET /api/dashboard/stats
   * Returns real pipeline stages, avg latency, and conversion rate from the backend.
   */
  static async getStats(): Promise<APIResponse<DashboardStats>> {
    return apiClient.get('/dashboard/stats');
  }

  /**
   * GET /api/ai/provider-health
   * Returns volume, failover count, and latency for each LLM provider.
   */
  static async getProviderHealth(): Promise<APIResponse<ProviderHealth[]>> {
    return apiClient.get('/ai/provider-health');
  }
}
