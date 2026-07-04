import { ChatMessage, LeadData, QuoteData } from './types';

export class PromptBuilder {
  static createSystemPrompt(role: 'sales_assistant' | 'crm_specialist' | 'quote_generator' = 'sales_assistant'): ChatMessage {
    const prompts = {
      sales_assistant: `You are an AI Sales Assistant for EckoX, a B2B SaaS platform specializing in lab equipment sales across Africa and Europe. 
Your role is to:
- Help with sales inquiries about lab equipment
- Provide technical specifications
- Address compliance and certification questions (CE, ISO, NAFDAC)
- Suggest products based on customer needs
- Provide pricing information
- Generate professional quotes

Always maintain a professional, consultative tone.
Be knowledgeable about lab equipment categories: HPLC, GC, Mass Spectrometry, Centrifuges, Incubators.
Consider regional compliance requirements (EU standards vs NAFDAC for Nigeria/Africa).`,

      crm_specialist: `You are a CRM Specialist AI for EckoX Sales Platform.
Your role is to:
- Analyze lead data and provide insights
- Score leads based on engagement and fit
- Suggest next steps in the sales pipeline
- Identify upsell opportunities
- Analyze customer behavior patterns
- Provide sales forecasting insights

Use data-driven analysis. Always reference specific metrics.`,

      quote_generator: `You are a Quote Generation AI for EckoX.
Your role is to:
- Generate professional sales quotes
- Calculate pricing with regional tax rules
- Apply volume discounts
- Include compliance certifications
- Format professional quote documents
- Suggest complementary products

Be precise with pricing. Always include compliance requirements.`,
    };

    return {
      role: 'system',
      content: prompts[role],
    };
  }

  static createLeadScoringPrompt(leadData: LeadData): ChatMessage[] {
    return [
      {
        role: 'system',
        content: `Analyze this lead and provide a confidence score (0-100) based on:
- Company size and industry fit
- Budget availability
- Timeline urgency
- Source quality
- Engagement level
- Regional market opportunity`,
      },
      {
        role: 'user',
        content: `Score this lead:
Name: ${leadData.name}
Company: ${leadData.company}
Email: ${leadData.email}
Country: ${leadData.country}
Industry: ${leadData.industry || 'Unknown'}
Budget: ${leadData.budget ? `$${leadData.budget}` : 'Not specified'}
Timeline: ${leadData.timeline || 'Not specified'}
Source: ${leadData.source || 'Unknown'}
Last Interaction: ${leadData.lastInteraction || 'Never'}

Provide:
1. Lead score (0-100)
2. Score breakdown (company fit, budget fit, timeline fit, source fit)
3. Recommendation (hot, warm, cold)
4. Next recommended action`,
      },
    ];
  }

  static createQuoteGenerationPrompt(quoteData: QuoteData, productDetails: any[]): ChatMessage[] {
    const productList = productDetails
      .map(
        (p, i) =>
          `Product ${i + 1}: ${p.name} x${quoteData.products[i]?.quantity || 1} @ ${quoteData.currency} ${p.price}`
      )
      .join('\n');

    const taxRules = {
      EUR: 'Apply EU VAT (19-23% depending on country)',
      USD: 'No standard tax',
      NGN: 'Apply NAFDAC compliance fee (+5%) and VAT (7.5%)',
    };

    return [
      {
        role: 'system',
        content: `You are generating a professional sales quote. 
Include:
- Item-by-item breakdown
- Subtotal
- Taxes and fees (${taxRules[quoteData.currency]})
- Total price
- Payment terms
- Delivery timeline
- Compliance certifications included`,
      },
      {
        role: 'user',
        content: `Generate quote in ${quoteData.currency}:
${productList}

Notes: ${quoteData.notes || 'None'}

Format as professional quote with:
1. Summary table
2. Tax breakdown
3. Total
4. Terms (Net 30, payment via wire transfer or local method)
5. Compliance certifications included`,
      },
    ];
  }

  static createRAGPrompt(query: string, context: string): ChatMessage[] {
    return [
      {
        role: 'system',
        content: `You are an expert at answering questions using provided document context.
Always cite your sources.
If the answer is not in the context, say so clearly.`,
      },
      {
        role: 'user',
        content: `Based on this document context, answer the question:

CONTEXT:
${context}

QUESTION:
${query}

Provide:
1. Direct answer
2. Supporting evidence from the context
3. Confidence level
4. Source document names`,
      },
    ];
  }

  static createSummarizationPrompt(text: string): ChatMessage[] {
    return [
      {
        role: 'system',
        content: `Summarize the following text concisely.
Extract:
1. Main points (3-5 bullets)
2. Key metrics or data
3. Action items if applicable`,
      },
      {
        role: 'user',
        content: `Summarize this:\n\n${text}`,
      },
    ];
  }

  static createChatCompletionPrompt(userMessage: string, context?: string): ChatMessage[] {
    const messages: ChatMessage[] = [this.createSystemPrompt('sales_assistant')];

    if (context) {
      messages.push({
        role: 'user',
        content: `Context: ${context}`,
      });
    }

    messages.push({
      role: 'user',
      content: userMessage,
    });

    return messages;
  }
}
