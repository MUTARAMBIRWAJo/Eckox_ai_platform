import { AIResponse, ChatMessage, LeadData, QuoteData, RAGResult } from './types';

// Core AI Service Interface - all providers must implement this
export interface IAIService {
  chat(messages: ChatMessage[]): Promise<AIResponse>;
  stream(messages: ChatMessage[]): AsyncGenerator<string>;
  summarize(text: string): Promise<AIResponse>;
  scoreLead(leadData: LeadData): Promise<AIResponse>;
  generateQuote(quoteData: QuoteData): Promise<AIResponse>;
  ragQuery(query: string, filters?: Record<string, any>): Promise<RAGResult>;
  getProviderName(): string;
  isAvailable(): Promise<boolean>;
}

// Base implementation with common utilities
export class BaseAIService implements IAIService {
  protected providerName: string = 'base';
  protected apiKey: string = '';

  async chat(messages: ChatMessage[]): Promise<AIResponse> {
    throw new Error('Not implemented');
  }

  async *stream(messages: ChatMessage[]): AsyncGenerator<string> {
    throw new Error('Not implemented');
  }

  async summarize(text: string): Promise<AIResponse> {
    throw new Error('Not implemented');
  }

  async scoreLead(leadData: LeadData): Promise<AIResponse> {
    throw new Error('Not implemented');
  }

  async generateQuote(quoteData: QuoteData): Promise<AIResponse> {
    throw new Error('Not implemented');
  }

  async ragQuery(query: string, filters?: Record<string, any>): Promise<RAGResult> {
    throw new Error('Not implemented');
  }

  getProviderName(): string {
    return this.providerName;
  }

  async isAvailable(): Promise<boolean> {
    return false;
  }

  // Shared utility methods
  protected createSuccessResponse(
    data: any,
    tokens: number = 0,
    sources: string[] = []
  ): AIResponse {
    return {
      success: true,
      provider: this.providerName,
      data: {
        message: data.message || '',
        confidence: data.confidence || 0.9,
        tokens: tokens,
        sources: sources,
        metadata: data.metadata,
      },
    };
  }

  protected createErrorResponse(error: string): AIResponse {
    return {
      success: false,
      provider: this.providerName,
      data: {
        message: '',
        confidence: 0,
        tokens: 0,
        sources: [],
      },
      error,
    };
  }
}
