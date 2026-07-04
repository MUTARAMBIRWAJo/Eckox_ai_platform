import { BaseAIService } from '../ai-service';
import { AIResponse, ChatMessage, LeadData, QuoteData, RAGResult } from '../types';
import { PromptBuilder } from '../prompt-builder';

export class ClaudeService extends BaseAIService {
  protected providerName = 'claude';
  private apiKey: string;
  private apiUrl = 'https://api.anthropic.com/v1';
  private model = 'claude-3-opus-20240229';

  constructor(apiKey: string) {
    super();
    this.apiKey = apiKey;
  }

  async isAvailable(): Promise<boolean> {
    return !!this.apiKey && this.apiKey.length > 0;
  }

  async chat(messages: ChatMessage[]): Promise<AIResponse> {
    try {
      // Filter out system messages as Claude handles them differently
      const systemMessage = messages.find(m => m.role === 'system');
      const conversationMessages = messages.filter(m => m.role !== 'system');

      const response = await fetch(`${this.apiUrl}/messages`, {
        method: 'POST',
        headers: {
          'x-api-key': this.apiKey,
          'anthropic-version': '2023-06-01',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: this.model,
          max_tokens: 2048,
          system: systemMessage?.content,
          messages: conversationMessages.map(m => ({
            role: m.role,
            content: m.content,
          })),
        }),
      });

      if (!response.ok) {
        throw new Error(`Claude API error: ${response.statusText}`);
      }

      const data = await response.json();
      const content = data.content[0].text;
      const tokens = (data.usage?.input_tokens || 0) + (data.usage?.output_tokens || 0);

      return this.createSuccessResponse(
        { message: content },
        tokens,
        ['claude-opus']
      );
    } catch (error) {
      return this.createErrorResponse(`Claude error: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }

  async *stream(messages: ChatMessage[]): AsyncGenerator<string> {
    try {
      const systemMessage = messages.find(m => m.role === 'system');
      const conversationMessages = messages.filter(m => m.role !== 'system');

      const response = await fetch(`${this.apiUrl}/messages`, {
        method: 'POST',
        headers: {
          'x-api-key': this.apiKey,
          'anthropic-version': '2023-06-01',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: this.model,
          max_tokens: 2048,
          stream: true,
          system: systemMessage?.content,
          messages: conversationMessages.map(m => ({
            role: m.role,
            content: m.content,
          })),
        }),
      });

      if (!response.ok) {
        throw new Error(`Claude API error: ${response.statusText}`);
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
            try {
              const parsed = JSON.parse(data);
              if (parsed.type === 'content_block_delta' && parsed.delta.type === 'text_delta') {
                yield parsed.delta.text;
              }
            } catch {
              // Ignore parse errors
            }
          }
        }
      }
    } catch (error) {
      console.error('Claude stream error:', error);
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
    const mockProducts = [
      { name: 'HPLC System', price: 45000 },
      { name: 'GC-MS', price: 65000 },
    ];

    const prompt = PromptBuilder.createQuoteGenerationPrompt(quoteData, mockProducts);
    return this.chat(prompt);
  }

  async ragQuery(query: string, filters?: Record<string, any>): Promise<RAGResult> {
    const contextPrompt = PromptBuilder.createRAGPrompt(query, 'Retrieved document context');
    const response = await this.chat(contextPrompt);

    return {
      answer: response.data.message,
      sources: [],
      confidence: response.data.confidence,
    };
  }
}
