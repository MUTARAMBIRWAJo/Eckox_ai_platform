import { apiClient, APIResponse } from './client';

export interface Lead {
  id: string;
  name: string;
  email: string;
  phone?: string;
  company: string;
  industry?: string;
  country: string;
  score: number;
  status: 'new' | 'contacted' | 'qualified' | 'proposal' | 'won' | 'lost';
  budget?: number;
  timeline?: string;
  source?: string;
  lastInteraction?: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

export interface LeadFilter {
  status?: string;
  country?: string;
  score?: { min: number; max: number };
  search?: string;
  page?: number;
  limit?: number;
}

export interface Customer {
  id: string;
  name: string;
  email: string;
  company: string;
  country: string;
  totalSpent: number;
  contactCount: number;
  lastPurchase?: string;
  createdAt: string;
}

export interface PipelineStats {
  total: number;
  byStatus: Record<string, number>;
  byCountry: Record<string, number>;
  revenue: number;
  conversionRate: number;
}

export class CRMAPI {
  // Leads endpoints
  static async getLeads(filter?: LeadFilter): Promise<APIResponse<{ leads: Lead[]; total: number }>> {
    return apiClient.get('/leads', filter);
  }

  static async getLead(id: string): Promise<APIResponse<Lead>> {
    return apiClient.get(`/leads/${id}`);
  }

  static async createLead(data: Partial<Lead>): Promise<APIResponse<Lead>> {
    return apiClient.post('/leads', data);
  }

  static async updateLead(id: string, data: Partial<Lead>): Promise<APIResponse<Lead>> {
    return apiClient.put(`/leads/${id}`, data);
  }

  static async deleteLead(id: string): Promise<APIResponse<void>> {
    return apiClient.delete(`/leads/${id}`);
  }

  static async updateLeadStatus(
    id: string,
    status: Lead['status']
  ): Promise<APIResponse<Lead>> {
    return apiClient.patch(`/leads/${id}/status`, { status });
  }

  static async updateLeadScore(id: string, score: number): Promise<APIResponse<Lead>> {
    return apiClient.patch(`/leads/${id}/score`, { score });
  }

  // Customers endpoints
  static async getCustomers(): Promise<APIResponse<Customer[]>> {
    return apiClient.get('/customers');
  }

  static async getCustomer(id: string): Promise<APIResponse<Customer>> {
    return apiClient.get(`/customers/${id}`);
  }

  // Pipeline analytics
  static async getPipelineStats(): Promise<APIResponse<PipelineStats>> {
    return apiClient.get('/pipeline/stats');
  }

  static async getPipelineByStatus(
    status: string
  ): Promise<APIResponse<{ leads: Lead[]; count: number }>> {
    return apiClient.get(`/pipeline/${status}`);
  }

  // Bulk operations
  static async bulkUpdateStatus(
    ids: string[],
    status: Lead['status']
  ): Promise<APIResponse<void>> {
    return apiClient.post('/leads/bulk/status', { ids, status });
  }

  static async bulkDelete(ids: string[]): Promise<APIResponse<void>> {
    return apiClient.post('/leads/bulk/delete', { ids });
  }

  // Import/Export
  static async importLeads(file: File): Promise<APIResponse<{ imported: number; errors: string[] }>> {
    const formData = new FormData();
    formData.append('file', file);

    return apiClient.post('/leads/import', formData);
  }

  static async exportLeads(format: 'csv' | 'excel' = 'csv'): Promise<APIResponse<{ url: string }>> {
    return apiClient.get('/leads/export', { format });
  }
}
