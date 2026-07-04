import { IAIService, BaseAIService } from './ai-service';
import { AIResponse, ChatMessage, LeadData, QuoteData, RAGResult } from './types';
import { PromptBuilder } from './prompt-builder';
import { OpenAIService } from './providers/openai-service';
import { ClaudeService } from './providers/claude-service';
import { MockAIService } from './providers/mock-service';

export type AIProvider = 'openai' | 'claude' | 'mock' | 'local';

interface AIServiceManagerConfig {
  primaryProvider: AIProvider;
  fallbackProvider?: AIProvider;
  apiKeys?: {
    openai?: string;
    claude?: string;
  };
  enableLogging?: boolean;
}

export class AIServiceManager {
  private primaryService: IAIService;
  private fallbackService?: IAIService;
  private config: AIServiceManagerConfig;
  private requestLog: Array<{
    timestamp: Date;
    provider: string;
    action: string;
    status: 'success' | 'failed';
    tokens?: number;
  }> = [];

  constructor(config: AIServiceManagerConfig) {
    this.config = config;
    this.primaryService = this.initializeService(config.primaryProvider);

    if (config.fallbackProvider) {
      this.fallbackService = this.initializeService(config.fallbackProvider);
    }
  }

  private initializeService(provider: AIProvider): IAIService {
    switch (provider) {
      case 'openai':
        return new OpenAIService(this.config.apiKeys?.openai || process.env.OPENAI_API_KEY || '');
      case 'claude':
        return new ClaudeService(this.config.apiKeys?.claude || process.env.CLAUDE_API_KEY || '');
      case 'local':
        return new MockAIService('local');
      case 'mock':
      default:
        return new MockAIService('mock');
    }
  }

  async chat(messages: ChatMessage[]): Promise<AIResponse> {
    return this.executeWithFallback('chat', async (service) => service.chat(messages), messages);
  }

  async *stream(messages: ChatMessage[]): AsyncGenerator<string> {
    try {
      const service = this.primaryService;
      this.log('stream', 'started', service.getProviderName());

      for await (const chunk of service.stream(messages)) {
        yield chunk;
      }

      this.log('stream', 'completed', service.getProviderName());
    } catch (error) {
      if (this.fallbackService) {
        console.warn('Primary stream failed, switching to fallback');
        for await (const chunk of this.fallbackService.stream(messages)) {
          yield chunk;
        }
      } else {
        throw error;
      }
    }
  }

  async summarize(text: string): Promise<AIResponse> {
    const prompt = PromptBuilder.createSummarizationPrompt(text);
    return this.executeWithFallback('summarize', async (service) => service.summarize(text), text);
  }

  async scoreLead(leadData: LeadData): Promise<AIResponse> {
    return this.executeWithFallback(
      'scoreLead',
      async (service) => service.scoreLead(leadData),
      leadData
    );
  }

  async generateQuote(quoteData: QuoteData): Promise<AIResponse> {
    return this.executeWithFallback(
      'generateQuote',
      async (service) => service.generateQuote(quoteData),
      quoteData
    );
  }

  async ragQuery(query: string, filters?: Record<string, any>): Promise<RAGResult> {
    try {
      const result = await this.primaryService.ragQuery(query, filters);
      this.log('ragQuery', 'success', this.primaryService.getProviderName());
      return result;
    } catch (error) {
      this.log('ragQuery', 'failed', this.primaryService.getProviderName());

      if (this.fallbackService) {
        console.warn('Primary RAG query failed, using fallback');
        return this.fallbackService.ragQuery(query, filters);
      }

      throw error;
    }
  }

  private async executeWithFallback<T>(
    action: string,
    fn: (service: IAIService) => Promise<T>,
    context: any
  ): Promise<T> {
    try {
      const result = await fn(this.primaryService);
      this.log(action, 'success', this.primaryService.getProviderName());
      return result;
    } catch (error) {
      this.log(action, 'failed', this.primaryService.getProviderName());

      if (this.fallbackService) {
        console.warn(`Primary ${action} failed, switching to fallback provider`);
        const result = await fn(this.fallbackService);
        this.log(action, 'success', this.fallbackService.getProviderName());
        return result;
      }

      throw error;
    }
  }

  private log(action: string, status: 'success' | 'failed', provider: string) {
    if (!this.config.enableLogging) return;

    this.requestLog.push({
      timestamp: new Date(),
      provider,
      action,
      status,
    });

    console.log(`[AI Service] ${action} - ${status} (${provider})`);
  }

  getRequestLog() {
    return this.requestLog;
  }

  switchProvider(provider: AIProvider) {
    this.primaryService = this.initializeService(provider);
    console.log(`Switched primary AI provider to: ${provider}`);
  }

  getPrimaryProvider(): string {
    return this.primaryService.getProviderName();
  }

  getFallbackProvider(): string | undefined {
    return this.fallbackService?.getProviderName();
  }
}

// Singleton instance
let manager: AIServiceManager | null = null;

export function getAIServiceManager(): AIServiceManager {
  if (!manager) {
    manager = new AIServiceManager({
      primaryProvider: (process.env.AI_PROVIDER as AIProvider) || 'mock',
      fallbackProvider: 'mock',
      enableLogging: process.env.AI_LOGGING === 'true',
    });
  }
  return manager;
}

export function initializeAIServiceManager(config: AIServiceManagerConfig) {
  manager = new AIServiceManager(config);
  return manager;
}
