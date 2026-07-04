// Mock API server - simulates Laravel backend responses
// This allows frontend development without needing backend running
// In production, replace with real API calls

const MOCK_DELAY = 300; // Simulate network latency

const mockData = {
  users: {
    'demo@eckoX.com': {
      id: 'user-1',
      email: 'demo@eckoX.com',
      name: 'Demo User',
      role: 'sales' as const,
      token: 'mock-token-123',
    },
  },

  leads: [
    {
      id: 'lead-1',
      name: 'Pharma Solutions Ltd',
      email: 'contact@pharmasol.com',
      company: 'Pharma Solutions',
      country: 'Nigeria',
      industry: 'Pharmaceuticals',
      score: 85,
      status: 'qualified',
      budget: 150000,
      timeline: '3 months',
      source: 'LinkedIn',
      lastInteraction: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
      createdAt: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString(),
      updatedAt: new Date().toISOString(),
    },
    {
      id: 'lead-2',
      name: 'Research Institute',
      email: 'lab@research.com',
      company: 'National Research',
      country: 'Ghana',
      industry: 'Research',
      score: 72,
      status: 'contacted',
      budget: 200000,
      timeline: '6 months',
      source: 'Email',
      lastInteraction: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(),
      createdAt: new Date(Date.now() - 45 * 24 * 60 * 60 * 1000).toISOString(),
      updatedAt: new Date().toISOString(),
    },
    {
      id: 'lead-3',
      name: 'EU Medical Labs',
      email: 'info@eumedlabs.de',
      company: 'EU Medical Labs',
      country: 'Germany',
      industry: 'Medical',
      score: 90,
      status: 'proposal',
      budget: 250000,
      timeline: '2 months',
      source: 'Trade Show',
      lastInteraction: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString(),
      createdAt: new Date(Date.now() - 60 * 24 * 60 * 60 * 1000).toISOString(),
      updatedAt: new Date().toISOString(),
    },
  ],

  products: [
    {
      id: 'prod-1',
      name: 'HPLC System Shimadzu LC-20AD',
      category: 'HPLC',
      price: 45000,
      currency: 'USD',
      description: 'High performance liquid chromatography system',
      compliance: { ce: true, iso9001: true, nafdac: true },
      regionAvailability: ['EU', 'Africa', 'Asia'],
      stock: 12,
    },
    {
      id: 'prod-2',
      name: 'GC-MS Agilent 7890B',
      category: 'GC-MS',
      price: 65000,
      currency: 'USD',
      description: 'Gas chromatography-mass spectrometry',
      compliance: { ce: true, iso9001: true },
      regionAvailability: ['EU', 'Asia'],
      stock: 8,
    },
    {
      id: 'prod-3',
      name: 'Centrifuge Eppendorf',
      category: 'Centrifuges',
      price: 8500,
      currency: 'USD',
      description: 'Lab centrifuge for research',
      compliance: { ce: true },
      regionAvailability: ['EU', 'Africa', 'Asia'],
      stock: 25,
    },
  ],
};

export class MockAPIServer {
  static async delay(ms: number = MOCK_DELAY) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  // Auth endpoints
  static async login(email: string, password: string) {
    await this.delay();
    const user = mockData.users[email as keyof typeof mockData.users];

    if (!user || password !== 'demo') {
      throw new Error('Invalid credentials');
    }

    return {
      success: true,
      data: {
        user: { id: user.id, email: user.email, name: user.name, role: user.role },
        token: user.token,
      },
    };
  }

  static async getMe() {
    await this.delay();
    const user = mockData.users['demo@eckoX.com'];
    return {
      success: true,
      data: { id: user.id, email: user.email, name: user.name, role: user.role },
    };
  }

  // CRM endpoints
  static async getLeads(filter?: any) {
    await this.delay();
    let leads = [...mockData.leads];

    if (filter?.status) {
      leads = leads.filter((l) => l.status === filter.status);
    }
    if (filter?.country) {
      leads = leads.filter((l) => l.country === filter.country);
    }
    if (filter?.search) {
      leads = leads.filter(
        (l) =>
          l.name.toLowerCase().includes(filter.search.toLowerCase()) ||
          l.company.toLowerCase().includes(filter.search.toLowerCase())
      );
    }

    return {
      success: true,
      data: { leads, total: leads.length },
    };
  }

  static async getLead(id: string) {
    await this.delay();
    const lead = mockData.leads.find((l) => l.id === id);

    if (!lead) {
      return { success: false, error: 'Lead not found' };
    }

    return { success: true, data: lead };
  }

  static async updateLeadStatus(id: string, status: string) {
    await this.delay();
    const lead = mockData.leads.find((l) => l.id === id);

    if (!lead) {
      return { success: false, error: 'Lead not found' };
    }

    lead.status = status;
    lead.updatedAt = new Date().toISOString();

    return { success: true, data: lead };
  }

  static async getPipelineStats() {
    await this.delay();

    const byStatus = {
      new: 5,
      contacted: 12,
      qualified: 8,
      proposal: 3,
      won: 2,
      lost: 1,
    };

    const byCountry = {
      Nigeria: 8,
      Ghana: 6,
      Germany: 4,
      'United States': 3,
      'United Kingdom': 2,
    };

    return {
      success: true,
      data: {
        total: Object.values(byStatus).reduce((a, b) => a + b, 0),
        byStatus,
        byCountry,
        revenue: 425000,
        conversionRate: 0.15,
      },
    };
  }

  // Products
  static async getProducts() {
    await this.delay();
    return {
      success: true,
      data: { products: mockData.products, total: mockData.products.length },
    };
  }

  // AI endpoints
  static async aiChat(messages: any[]) {
    await this.delay(800);

    return {
      success: true,
      data: {
        message: 'Based on the conversation, I recommend scheduling a technical consultation to discuss your lab requirements in detail. Our HPLC systems are ideal for pharmaceutical analysis.',
        confidence: 0.92,
        tokens: 124,
        sources: ['product_catalog'],
      },
    };
  }

  static async scoreLeadAI(leadData: any) {
    await this.delay(1200);

    const baseScore = 75;
    const countryBonus = ['Nigeria', 'Ghana', 'Kenya'].includes(leadData.country) ? 5 : 0;
    const budgetBonus = leadData.budget && leadData.budget > 100000 ? 10 : 0;
    const score = Math.min(100, baseScore + countryBonus + budgetBonus);

    return {
      success: true,
      data: {
        score,
        breakdown: {
          companyFit: 80,
          budgetFit: 75,
          timelineFit: 85,
          sourceFit: 80,
        },
        recommendation: score >= 80 ? 'hot' : 'warm',
        nextStep: score >= 80 ? 'Schedule demo' : 'Send technical spec',
      },
    };
  }

  static async generateQuoteAI(quoteData: any) {
    await this.delay(1500);

    const subtotal = 110000;
    const taxRate = quoteData.currency === 'EUR' ? 0.19 : 0.075;
    const tax = subtotal * taxRate;
    const total = subtotal + tax;

    return {
      success: true,
      data: {
        quoteId: `QT-${Date.now()}`,
        content: `Professional Quote from EckoX\n\nSubtotal: ${quoteData.currency} ${subtotal}\nTax: ${quoteData.currency} ${tax}\nTotal: ${quoteData.currency} ${total}`,
        subtotal,
        tax,
        total,
        currency: quoteData.currency,
        validUntil: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      },
    };
  }

  static async ragQuery(query: string) {
    await this.delay(1000);

    return {
      success: true,
      data: {
        answer: 'CE compliance for lab equipment requires adherence to EU directives on in vitro diagnostic devices and laboratory equipment. All EckoX products are CE marked and ISO 9001 certified, ensuring compliance with both European and international standards.',
        sources: [
          {
            documentId: 'doc-001',
            title: 'Product Compliance Manual',
            relevance: 0.98,
          },
          {
            documentId: 'doc-002',
            title: 'Regional Requirements Guide',
            relevance: 0.95,
          },
        ],
        confidence: 0.92,
      },
    };
  }

  // Analytics
  static async getDashboardAnalytics() {
    await this.delay();

    return {
      success: true,
      data: {
        totalLeads: 31,
        leadsThisMonth: 12,
        convertedThisMonth: 2,
        revenue: 425000,
        averageDealSize: 212500,
        conversionRate: 0.065,
        leadsByCountry: {
          Nigeria: 8,
          Ghana: 6,
          Germany: 4,
          'United States': 3,
          'United Kingdom': 2,
        },
        leadsByStatus: {
          new: 5,
          contacted: 12,
          qualified: 8,
          proposal: 3,
          won: 2,
          lost: 1,
        },
        topPerformers: [
          { name: 'John Smith', leads: 12, revenue: 250000 },
          { name: 'Sarah Johnson', leads: 8, revenue: 175000 },
        ],
      },
    };
  }
}

export default MockAPIServer;
