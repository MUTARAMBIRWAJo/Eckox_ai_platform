import { BaseAIService } from '../ai-service';
import { AIResponse, ChatMessage, LeadData, QuoteData, RAGResult } from '../types';
import { PromptBuilder } from '../prompt-builder';

export class OpenAIService extends BaseAIService {
  protected providerName = 'openai';
  private apiKey: string;
  private apiUrl = 'https://api.openai.com/v1';

  constructor(apiKey: string) {
    super();
    this.apiKey = apiKey;
  }

  async isAvailable(): Promise<boolean> {
    return !!this.apiKey && this.apiKey.length > 0;
  }

  async chat(messages: ChatMessage[]): Promise<AIResponse> {
    try {
      const response = await fetch(`${this.apiUrl}/chat/completions`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: 'gpt-4-turbo-preview',
          messages: messages.map(m => ({
            role: m.role,
            content: m.content,
          })),
          temperature: 0.7,
          max_tokens: 2000,
        }),
      });

      if (!response.ok) {
        throw new Error(`OpenAI API error: ${response.statusText}`);
      }

      const data = await response.json();
      const content = data.choices[0].message.content;
      const tokens = data.usage?.total_tokens || 0;

      return this.createSuccessResponse(
        { message: content },
        tokens,
        ['openai-gpt4']
      );
    } catch (error) {
      return this.createErrorResponse(`OpenAI error: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }

  async *stream(messages: ChatMessage[]): AsyncGenerator<string> {
    try {
      const response = await fetch(`${this.apiUrl}/chat/completions`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: 'gpt-4-turbo-preview',
          messages: messages.map(m => ({
            role: m.role,
            content: m.content,
          })),
          temperature: 0.7,
          stream: true,
        }),
      });

      if (!response.ok) {
        throw new Error(`OpenAI API error: ${response.statusText}`);
      }

      const reader = response.body?.getReader();
      if (!reader) return;

      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6);
            if (data === '[DONE]') continue;

            try {
              const parsed = JSON.parse(data);
              const content = parsed.choices[0].delta.content;
              if (content) yield content;
            } catch {
              // Ignore parse errors
            }
          }
        }
      }
    } catch (error) {
      console.error('OpenAI stream error:', error);
    }
  }

  async summarize(text: string): Promise<AIResponse> {
    const prompt = PromptBuilder.createSummarizationPrompt(text);
    return this.chat(prompt);
  }

  async scoreLead(leadData: LeadData): Promise<AIResponse> {
    const prompt = PromptBuilder.createLeadScoringPrompt(leadData);
    return this.chat(prompt);
  }

  async generateQuote(quoteData: QuoteData): Promise<AIResponse> {
    // In production, fetch actual product details from API
    const mockProducts = [
      { name: 'HPLC System', price: 45000 },
      { name: 'GC-MS', price: 65000 },
    ];

    const prompt = PromptBuilder.createQuoteGenerationPrompt(quoteData, mockProducts);
    return this.chat(prompt);
  }

  async ragQuery(query: string, filters?: Record<string, any>): Promise<RAGResult> {
    // This would typically:
    // 1. Convert query to embeddings using OpenAI
    // 2. Search Supabase vector store
    // 3. Retrieve relevant chunks
    // 4. Send to LLM for synthesis
    
    const contextPrompt = PromptBuilder.createRAGPrompt(query, 'Retrieved document context would go here');
    const response = await this.chat(contextPrompt);

    return {
      answer: response.data.message,
      sources: [],
      confidence: response.data.confidence,
    };
  }
}
