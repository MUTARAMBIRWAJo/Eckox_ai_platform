import { apiClient, APIResponse } from './client';
import { ChatMessage, LeadData, QuoteData, RAGResult, AIResponse } from '@/lib/services/ai/types';

export interface ChatResponse {
  message: string;
  confidence: number;
  sources: string[];
  metadata?: Record<string, any>;
}

export interface LeadScoreResponse {
  score: number;
  breakdown: {
    companyFit: number;
    budgetFit: number;
    timelineFit: number;
    sourceFit: number;
  };
  recommendation: 'hot' | 'warm' | 'cold';
  nextStep: string;
}

export interface QuoteResponse {
  quoteId: string;
  content: string;
  subtotal: number;
  tax: number;
  total: number;
  currency: string;
  validUntil: string;
}

export interface RAGQueryResponse {
  answer: string;
  sources: Array<{
    documentId: string;
    title: string;
    relevance: number;
  }>;
  confidence: number;
}

export class AIAPI {
  // Chat endpoint
  static async chat(messages: ChatMessage[]): Promise<APIResponse<ChatResponse>> {
    return apiClient.post('/ai/chat', { messages });
  }

  // Streaming chat - yields text chunks using native fetch API and abort signals
  static async *streamChat(messages: ChatMessage[], signal?: AbortSignal): AsyncGenerator<string> {
    try {
      const token = apiClient.getAuthToken();
      const response = await fetch(`${apiClient.getBaseURL()}/ai/chat/stream`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({ messages }),
        signal,
      });

      if (!response.ok) {
        throw new Error(`Failed to start chat stream: ${response.statusText}`);
      }

      const reader = response.body?.getReader();
      if (!reader) {
        throw new Error('Response body is not readable');
      }

      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          const trimmed = line.trim();
          if (!trimmed) continue;

          if (trimmed.startsWith('data: ')) {
            const dataContent = trimmed.slice(6).trim();
            if (dataContent === '[DONE]') {
              return;
            }
            yield dataContent;
          } else {
            yield trimmed;
          }
        }
      }
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') {
        console.log('[AI Stream] Stream aborted by user');
        return;
      }
      console.error('[AI Stream Error]', error);
      throw error;
    }
  }

  // Lead scoring
  static async scoreLead(leadData: LeadData): Promise<APIResponse<LeadScoreResponse>> {
    return apiClient.post('/ai/lead-score', { leadData });
  }

  // Quote generation
  static async generateQuote(quoteData: QuoteData): Promise<APIResponse<QuoteResponse>> {
    return apiClient.post('/ai/generate-quote', { quoteData });
  }

  // Text summarization
  static async summarize(text: string): Promise<APIResponse<{ summary: string }>> {
    return apiClient.post('/ai/summarize', { text });
  }

  // Summarize lead notes
  static async summarizeLead(leadId: string): Promise<APIResponse<{ summary: string }>> {
    return apiClient.post(`/ai/summarize-lead/${leadId}`, {});
  }

  // RAG - Retrieval Augmented Generation
  static async ragQuery(query: string, filters?: Record<string, any>): Promise<APIResponse<RAGQueryResponse>> {
    return apiClient.post('/ai/rag-query', { query, filters });
  }

  // Semantic search in knowledge base
  static async searchKnowledge(query: string, limit: number = 5): Promise<APIResponse<RAGQueryResponse['sources']>> {
    return apiClient.get('/ai/knowledge-search', { query, limit });
  }

  // Get AI insights for dashboard
  static async getDashboardInsights(): Promise<
    APIResponse<{
      topLeads: Array<{ id: string; name: string; score: number }>;
      recommendations: string[];
      upcomingActions: string[];
    }>
  > {
    return apiClient.get('/ai/dashboard-insights');
  }

  // Get provider status
  static async getProviderStatus(): Promise<
    APIResponse<{
      primary: string;
      fallback?: string;
      available: boolean;
    }>
  > {
    return apiClient.get('/ai/provider-status');
  }

  // Health check
  static async healthCheck(): Promise<
    APIResponse<{
      status: 'healthy' | 'degraded' | 'unavailable';
      provider: string;
      latency: number;
    }>
  > {
    return apiClient.get('/ai/health');
  }
}
