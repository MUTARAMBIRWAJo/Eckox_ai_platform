import { apiClient, APIResponse } from './client';

export interface Lead {
  id: string;
  name: string;
  email: string;
  phone?: string;
  company?: string;
  industry?: string;
  country?: string;
  score?: number;
  status: 'new' | 'contacted' | 'qualified' | 'lost';
  budget?: number;
  timeline?: string;
  source?: string;
  lastInteraction?: string;
  notes?: string;
  assigned_to?: number | null;
  createdAt?: string;
  updatedAt?: string;
  created_at?: string;
  updated_at?: string;
}

export interface LeadActivity {
  id: string;
  lead_id: string;
  user_id: string;
  type: string;
  description: string;
  created_at: string;
}

export interface LeadFilter {
  status?: string;
  assigned_to?: string;
  per_page?: number;
  page?: number;
}

export interface CreateLeadRequest {
  name: string;
  email: string;
  phone?: string;
  status?: 'new' | 'contacted' | 'qualified' | 'lost';
  assigned_to?: number;
}

export interface UpdateLeadRequest {
  name?: string;
  email?: string;
  phone?: string;
  status?: 'new' | 'contacted' | 'qualified' | 'lost';
  assigned_to?: number | null;
}

export class CRMAPI {
  // ── Leads ──────────────────────────────────────────────────────────────────
  static async getLeads(filter?: LeadFilter): Promise<APIResponse<{ leads: Lead[]; total: number }>> {
    return apiClient.get('/leads', filter);
  }

  static async getLead(id: string): Promise<APIResponse<Lead>> {
    return apiClient.get(`/leads/${id}`);
  }

  static async createLead(data: CreateLeadRequest): Promise<APIResponse<{ lead: Lead; message: string }>> {
    return apiClient.post('/leads', data);
  }

  /**
   * Update a lead. Backend uses PATCH /api/leads/{id}.
   */
  static async updateLead(id: string, data: UpdateLeadRequest): Promise<APIResponse<{ lead: Lead; message: string }>> {
    return apiClient.patch(`/leads/${id}`, data);
  }

  /**
   * Update only the status. Backend uses PATCH /api/leads/{id} (no separate /status route).
   */
  static async updateLeadStatus(id: string, status: Lead['status']): Promise<APIResponse<{ lead: Lead; message: string }>> {
    return apiClient.patch(`/leads/${id}`, { status });
  }

  static async deleteLead(id: string): Promise<APIResponse<{ message: string }>> {
    return apiClient.delete(`/leads/${id}`);
  }

  // ── Activity Log ──────────────────────────────────────────────────────────
  static async logActivity(
    leadId: string,
    type: string,
    description: string
  ): Promise<APIResponse<{ activity: LeadActivity; message: string }>> {
    return apiClient.post(`/leads/${leadId}/activity`, { type, description });
  }
}
