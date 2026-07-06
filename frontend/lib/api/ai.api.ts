import { apiClient, APIResponse } from './client';

// ── Shared Types ──────────────────────────────────────────────────────────────

export interface KBEntry {
  id: string;
  region: 'africa' | 'europe';
  doc_type: string;
  product_category?: string;
  content: string;
  effective_date?: string;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface EscalationRecord {
  id: string;
  traceId: string;
  leadId: string;
  leadName: string;
  reason: string;
  content: string;
  region: string;
  language: string;
  history: { sender: string; content: string; timestamp: string }[];
  createdAt: string;
}

export interface TraceLog {
  traceId: string;
  leadName: string;
  llmProvider: string;
  decisionType: string;
  nodePath: string[];
  latencyMs: Record<string, number>;
  toolCalls: { name: string; inputs: any; output: any }[];
  guardrailVerdict?: { success: boolean; errors: string[] };
  actionExecuted?: { channel: string; status: string };
  hasFailover?: boolean;
  hasRetryCycle?: boolean;
}

export interface MarketingApproval {
  id: string;
  campaignName: string;
  channel: string;
  content: string;
  status: 'pending' | 'approved' | 'rejected';
  createdAt: string;
}

export interface ProviderHealth {
  name: string;
  volume: number;
  failovers: number;
  latency: number;
}

// ── AI Chat Streaming ─────────────────────────────────────────────────────────

export class AIAPI {
  /**
   * Stream chat response from the backend AI agent.
   * Returns an async generator of text chunks.
   */
  static async *streamChat(
    message: string,
    conversationHistory: { role: string; content: string }[],
    leadId?: string | null,
    abortSignal?: AbortSignal
  ): AsyncGenerator<string> {
    const baseURL = apiClient.getBaseURL();
    const token = apiClient.getToken();

    const response = await fetch(`${baseURL}/ai/chat/stream`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      },
      body: JSON.stringify({ message, history: conversationHistory, lead_id: leadId }),
      signal: abortSignal,
    });

    if (!response.ok) {
      throw new Error(`Stream failed: ${response.status} ${response.statusText}`);
    }

    const reader = response.body?.getReader();
    if (!reader) throw new Error('No response body');

    const decoder = new TextDecoder();
    let buffer = '';

    try {
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() ?? '';

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6).trim();
            if (data === '[DONE]') return;
            if (data === '[ERROR]') throw new Error('Stream error from server');
            if (data) yield data;
          }
        }
      }
    } finally {
      reader.releaseLock();
    }
  }

  // ── Escalations ─────────────────────────────────────────────────────────────

  static async getEscalations(filters?: { reason?: string }): Promise<APIResponse<EscalationRecord[]>> {
    return apiClient.get('/ai/escalations', filters);
  }

  static async takeoverConversation(traceId: string, reply: string): Promise<APIResponse<{ success: boolean }>> {
    return apiClient.post(`/ai/escalations/${traceId}/takeover`, { reply });
  }

  // ── Trace Viewer ─────────────────────────────────────────────────────────────

  static async getTrace(traceId: string): Promise<APIResponse<TraceLog>> {
    return apiClient.get(`/traces/${traceId}`);
  }

  // ── Knowledge Base ───────────────────────────────────────────────────────────

  static async getKBEntries(): Promise<APIResponse<KBEntry[]>> {
    return apiClient.get('/knowledge-base');
  }

  static async createKBEntry(data: Omit<KBEntry, 'id'>): Promise<APIResponse<KBEntry>> {
    return apiClient.post('/knowledge-base', data);
  }

  static async updateKBEntry(id: string, data: Partial<KBEntry>): Promise<APIResponse<KBEntry>> {
    return apiClient.put(`/knowledge-base/${id}`, data);
  }

  static async deleteKBEntry(id: string): Promise<APIResponse<void>> {
    return apiClient.delete(`/knowledge-base/${id}`);
  }

  static async testKBQuery(query: string): Promise<APIResponse<{ score: number; content: string }[]>> {
    return apiClient.post('/knowledge-base/test', { query });
  }

  // ── Provider Health ──────────────────────────────────────────────────────────

  static async getProviderHealth(): Promise<APIResponse<ProviderHealth[]>> {
    return apiClient.get('/ai/provider-health');
  }

  // ── Marketing Approvals ──────────────────────────────────────────────────────

  static async getMarketingApprovals(filters?: { status?: string; channel?: string }): Promise<APIResponse<MarketingApproval[]>> {
    return apiClient.get('/marketing-approvals', filters);
  }

  static async approveMarketingApproval(id: string): Promise<APIResponse<{ success: boolean; status: string }>> {
    return apiClient.post(`/marketing-approvals/${id}/approve`);
  }

  static async rejectMarketingApproval(id: string, reason?: string): Promise<APIResponse<{ success: boolean; status: string }>> {
    return apiClient.post(`/marketing-approvals/${id}/reject`, { reason });
  }
}
