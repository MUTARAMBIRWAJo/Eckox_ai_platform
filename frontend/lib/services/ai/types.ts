// Unified AI response format used across all providers
export interface AIResponse {
  success: boolean;
  provider: string;
  data: {
    message: string;
    confidence: number;
    tokens: number;
    sources: string[];
    metadata?: Record<string, any>;
  };
  error?: string;
}

// Chat message format
export interface ChatMessage {
  role: 'user' | 'assistant' | 'system';
  content: string;
  metadata?: Record<string, any>;
}

// Lead data for scoring
export interface LeadData {
  id: string;
  name: string;
  company: string;
  email: string;
  phone?: string;
  country: string;
  industry?: string;
  budget?: number;
  timeline?: string;
  source?: string;
  lastInteraction?: string;
}

// Quote generation data
export interface QuoteData {
  leadId: string;
  products: Array<{
    id: string;
    quantity: number;
  }>;
  currency: 'USD' | 'EUR' | 'NGN';
  notes?: string;
}

// RAG query result
export interface RAGResult {
  answer: string;
  sources: Array<{
    documentId: string;
    title: string;
    relevance: number;
    chunk: string;
  }>;
  confidence: number;
}
