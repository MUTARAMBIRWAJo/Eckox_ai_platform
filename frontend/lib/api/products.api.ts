import { apiClient, APIResponse } from './client';

export interface Product {
  id: string;
  name: string;
  category: string;
  price: number;
  currency: string;
  description: string;
  specifications?: Record<string, string>;
  compliance: {
    ce?: boolean;
    iso9001?: boolean;
    nafdac?: boolean;
    other?: string[];
  };
  regionAvailability: string[];
  image?: string;
  stock?: number;
  createdAt: string;
  updatedAt: string;
}

export interface Quote {
  id: string;
  leadId?: string;
  items: Array<{
    productId: string;
    quantity: number;
    price: number;
  }>;
  subtotal: number;
  tax: number;
  total: number;
  currency: string;
  status: 'draft' | 'sent' | 'viewed' | 'accepted' | 'rejected' | 'expired';
  validUntil: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

export interface QuoteItem {
  productId: string;
  quantity: number;
  notes?: string;
}

export class ProductsAPI {
  // Products endpoints
  static async getProducts(filter?: {
    category?: string;
    country?: string;
    search?: string;
    page?: number;
    limit?: number;
  }): Promise<APIResponse<{ products: Product[]; total: number }>> {
    return apiClient.get('/products', filter);
  }

  static async getProduct(id: string): Promise<APIResponse<Product>> {
    return apiClient.get(`/products/${id}`);
  }

  static async createProduct(data: Partial<Product>): Promise<APIResponse<Product>> {
    return apiClient.post('/products', data);
  }

  static async updateProduct(id: string, data: Partial<Product>): Promise<APIResponse<Product>> {
    return apiClient.put(`/products/${id}`, data);
  }

  static async deleteProduct(id: string): Promise<APIResponse<void>> {
    return apiClient.delete(`/products/${id}`);
  }

  static async getProductsByCategory(category: string): Promise<APIResponse<Product[]>> {
    return apiClient.get(`/products/category/${category}`);
  }

  static async searchProducts(query: string): Promise<APIResponse<Product[]>> {
    return apiClient.get('/products/search', { query });
  }
}

export class QuotesAPI {
  // Quotes endpoints
  static async getQuotes(filter?: {
    status?: string;
    leadId?: string;
    page?: number;
    limit?: number;
  }): Promise<APIResponse<{ quotes: Quote[]; total: number }>> {
    return apiClient.get('/quotes', filter);
  }

  static async getQuote(id: string): Promise<APIResponse<Quote>> {
    return apiClient.get(`/quotes/${id}`);
  }

  static async createQuote(data: {
    leadId?: string;
    items: QuoteItem[];
    currency: string;
    notes?: string;
  }): Promise<APIResponse<Quote>> {
    return apiClient.post('/quotes', data);
  }

  static async updateQuote(id: string, data: Partial<Quote>): Promise<APIResponse<Quote>> {
    return apiClient.put(`/quotes/${id}`, data);
  }

  static async deleteQuote(id: string): Promise<APIResponse<void>> {
    return apiClient.delete(`/quotes/${id}`);
  }

  // Quote actions
  static async sendQuote(id: string, email?: string): Promise<APIResponse<void>> {
    return apiClient.post(`/quotes/${id}/send`, { email });
  }

  static async markAsViewed(id: string): Promise<APIResponse<void>> {
    return apiClient.post(`/quotes/${id}/viewed`, {});
  }

  static async acceptQuote(id: string): Promise<APIResponse<{ orderId: string }>> {
    return apiClient.post(`/quotes/${id}/accept`, {});
  }

  static async rejectQuote(id: string, reason?: string): Promise<APIResponse<void>> {
    return apiClient.post(`/quotes/${id}/reject`, { reason });
  }

  // PDF generation
  static async generatePDF(id: string): Promise<APIResponse<{ url: string }>> {
    return apiClient.get(`/quotes/${id}/pdf`);
  }

  static async downloadPDF(id: string): Promise<void> {
    const response = await this.generatePDF(id);
    if (response.success && response.data?.url) {
      window.open(response.data.url, '_blank');
    }
  }

  // Pricing calculation
  static async calculatePrice(items: QuoteItem[], currency: string, country?: string): Promise<APIResponse<{
    subtotal: number;
    tax: number;
    total: number;
  }>> {
    return apiClient.post('/quotes/calculate-price', { items, currency, country });
  }

  // AI-generated quotes
  static async generateAIQuote(leadId: string): Promise<APIResponse<Quote>> {
    return apiClient.post(`/quotes/ai-generate/${leadId}`, {});
  }
}
