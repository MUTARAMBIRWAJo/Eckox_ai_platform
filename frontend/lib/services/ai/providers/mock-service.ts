import { BaseAIService } from '../ai-service';
import { AIResponse, ChatMessage, LeadData, QuoteData, RAGResult } from '../types';

// Mock responses for testing without real API keys
const MOCK_RESPONSES = {
  chat: 'This is a mock AI response. In production, this would be powered by OpenAI or Claude. The customer inquiry has been processed and recommendations are ready.',
  
  scoreLead: `Lead Score: 82/100
Score Breakdown:
- Company Fit: 90/100 (Pharmaceutical industry, good match)
- Budget Fit: 75/100 (Budget adequate for HPLC system)
- Timeline Fit: 85/100 (Immediate need indicated)
- Source Fit: 80/100 (Inbound inquiry, high quality)

Recommendation: HOT - High priority lead
Next Step: Schedule technical consultation call`,

  quote: `PROFESSIONAL QUOTE - EckoX Lab Equipment
==========================================

1. HPLC System (Shimadzu LC-20AD) x 1 .................... USD 45,000.00
2. GC-MS (Agilent 7890B/5977B) x 1 ........................ USD 65,000.00

Subtotal ................................................ USD 110,000.00
EU VAT (19%) ............................................. USD 20,900.00
Compliance Fee (NAFDAC) ................................... USD 5,500.00

TOTAL .................................................... USD 136,400.00

Payment Terms: Net 30 via wire transfer
Delivery: 4-6 weeks from payment
Certifications: CE marked, ISO 9001 certified, NAFDAC approved`,

  summarize: `Summary:
• Key Point 1: Main finding or conclusion
• Key Point 2: Supporting evidence
• Key Point 3: Recommendation or action

The document discusses important laboratory procedures and compliance requirements essential for pharmaceutical analysis.`,

  ragQuery: `Answer based on retrieved documents:

The system currently maintains several compliance certifications including CE marking for European markets and NAFDAC approval for African distribution. Regional requirements vary by country - EU markets require CE and ISO compliance, while African markets require NAFDAC registration and additional regional certifications.

Sources: Product Compliance Manual (98% relevance), Regional Requirements Guide (95% relevance)`,
};

export class MockAIService extends BaseAIService {
  protected providerName: string;

  constructor(name: string = 'mock') {
    super();
    this.providerName = name;
  }

  async isAvailable(): Promise<boolean> {
    return true;
  }

  async chat(messages: ChatMessage[]): Promise<AIResponse> {
    await this.simulateDelay(800, 1500);

    return this.createSuccessResponse(
      { message: MOCK_RESPONSES.chat, metadata: { simulated: true } },
      Math.floor(Math.random() * 500) + 200,
      ['mock-data']
    );
  }

  async *stream(messages: ChatMessage[]): AsyncGenerator<string> {
    const text = MOCK_RESPONSES.chat;
    const chunkSize = 5;
    
    for (let i = 0; i < text.length; i += chunkSize) {
      yield text.slice(i, i + chunkSize);
      await this.simulateDelay(50, 100);
    }
  }

  async summarize(text: string): Promise<AIResponse> {
    await this.simulateDelay(600, 1200);

    return this.createSuccessResponse(
      { message: MOCK_RESPONSES.summarize, metadata: { simulated: true } },
      150,
      ['mock-data']
    );
  }

  async scoreLead(leadData: LeadData): Promise<AIResponse> {
    await this.simulateDelay(1000, 2000);

    // Generate different scores based on lead data
    const baseScore = 75;
    const countryBonus = leadData.country === 'Nigeria' || leadData.country === 'Ghana' ? 5 : 0;
    const budgetBonus = leadData.budget && leadData.budget > 50000 ? 10 : 0;
    const score = Math.min(100, baseScore + countryBonus + budgetBonus);

    const response = `Lead Score: ${score}/100
Score Breakdown:
- Company Fit: ${75 + countryBonus}/100 (${leadData.company} - Good match for region)
- Budget Fit: ${70 + budgetBonus}/100 (${leadData.budget ? `$${leadData.budget}` : 'Not specified'})
- Timeline Fit: 80/100 (${leadData.timeline || 'Not specified'})
- Source Fit: 85/100 (${leadData.source || 'Unknown'})

Recommendation: ${score >= 80 ? 'HOT' : score >= 60 ? 'WARM' : 'COLD'}
Next Step: ${score >= 80 ? 'Schedule demo' : 'Follow up with technical spec'}`;

    return this.createSuccessResponse(
      { message: response, confidence: score / 100, metadata: { leadId: leadData.id, simulated: true } },
      Math.floor(score * 5),
      ['mock-scoring']
    );
  }

  async generateQuote(quoteData: QuoteData): Promise<AIResponse> {
    await this.simulateDelay(1500, 2500);

    const taxRate = quoteData.currency === 'EUR' ? 0.19 : quoteData.currency === 'NGN' ? 0.125 : 0;
    const subtotal = 110000;
    const tax = subtotal * taxRate;
    const total = subtotal + tax;

    const response = `Professional Quote - EckoX Equipment
================================================

Products for Lead: (ID: ${quoteData.leadId})
- Product A x ${quoteData.products[0]?.quantity || 1}
- Product B x ${quoteData.products[1]?.quantity || 1}

Subtotal: ${quoteData.currency} ${subtotal.toLocaleString()}
${taxRate > 0 ? `Tax (${(taxRate * 100).toFixed(0)}%): ${quoteData.currency} ${tax.toLocaleString()}` : ''}
----------------------------------------
TOTAL: ${quoteData.currency} ${total.toLocaleString()}

Terms: Net 30
Delivery: 4-6 weeks
Compliance: CE, ISO 9001

${quoteData.notes ? `Special Notes: ${quoteData.notes}` : ''}`;

    return this.createSuccessResponse(
      { message: response, metadata: { quoteId: Math.random().toString(36).substr(2, 9), simulated: true } },
      Math.floor(Math.random() * 800) + 400,
      ['mock-quotes']
    );
  }

  async ragQuery(query: string, filters?: Record<string, any>): Promise<RAGResult> {
    await this.simulateDelay(800, 1500);

    return {
      answer: MOCK_RESPONSES.ragQuery,
      sources: [
        {
          documentId: 'doc-001',
          title: 'Product Compliance Manual',
          relevance: 0.98,
          chunk: 'CE marking requirements for European distribution...',
        },
        {
          documentId: 'doc-002',
          title: 'Regional Requirements',
          relevance: 0.95,
          chunk: 'NAFDAC registration process for African markets...',
        },
      ],
      confidence: 0.92,
    };
  }

  private async simulateDelay(min: number, max: number): Promise<void> {
    return new Promise((resolve) => {
      const delay = Math.random() * (max - min) + min;
      setTimeout(resolve, delay);
    });
  }
}
