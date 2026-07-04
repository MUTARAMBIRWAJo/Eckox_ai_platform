import { apiClient, APIResponse } from './client';

export interface Conversation {
  id: string;
  leadId: string;
  type: 'chat' | 'whatsapp' | 'email' | 'call';
  channel?: string;
  messages: Message[];
  status: 'active' | 'closed' | 'archived';
  lastMessageAt: string;
  createdAt: string;
  updatedAt: string;
}

export interface Message {
  id: string;
  conversationId: string;
  sender: 'user' | 'assistant' | 'lead';
  senderName?: string;
  content: string;
  attachments?: Array<{
    type: string;
    url: string;
    name: string;
  }>;
  aiGenerated?: boolean;
  metadata?: Record<string, any>;
  createdAt: string;
}

export interface Activity {
  id: string;
  leadId: string;
  type: 'call' | 'email' | 'meeting' | 'note' | 'status_change';
  description: string;
  dueDate?: string;
  completed: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface AnalyticsDashboard {
  totalLeads: number;
  leadsThisMonth: number;
  convertedThisMonth: number;
  revenue: number;
  averageDealSize: number;
  conversionRate: number;
  leadsByCountry: Record<string, number>;
  leadsByStatus: Record<string, number>;
  topPerformers: Array<{ name: string; leads: number; revenue: number }>;
  recentActivity: Activity[];
}

export interface ConversionMetrics {
  total: number;
  converted: number;
  rate: number;
  byCountry: Record<string, { total: number; converted: number; rate: number }>;
  byIndustry: Record<string, { total: number; converted: number; rate: number }>;
}

export class ConversationsAPI {
  // Conversations endpoints
  static async getConversations(leadId?: string): Promise<APIResponse<Conversation[]>> {
    return apiClient.get('/conversations', leadId ? { leadId } : undefined);
  }

  static async getConversation(id: string): Promise<APIResponse<Conversation>> {
    return apiClient.get(`/conversations/${id}`);
  }

  static async createConversation(data: {
    leadId: string;
    type: Conversation['type'];
    channel?: string;
  }): Promise<APIResponse<Conversation>> {
    return apiClient.post('/conversations', data);
  }

  // Messages endpoints
  static async getMessages(conversationId: string, limit: number = 50): Promise<APIResponse<Message[]>> {
    return apiClient.get(`/conversations/${conversationId}/messages`, { limit });
  }

  static async sendMessage(conversationId: string, content: string, attachments?: File[]): Promise<APIResponse<Message>> {
    const formData = new FormData();
    formData.append('content', content);

    if (attachments) {
      attachments.forEach((file, index) => {
        formData.append(`attachments[${index}]`, file);
      });
    }

    return apiClient.post(`/conversations/${conversationId}/messages`, formData);
  }

  static async sendAIMessage(conversationId: string, content: string): Promise<APIResponse<Message>> {
    return apiClient.post(`/conversations/${conversationId}/ai-message`, { content });
  }

  // Streaming message (AI assistant responding)
  static async *streamAIResponse(conversationId: string, content: string): AsyncGenerator<string> {
    try {
      for await (const chunk of apiClient['streamPost'](`/conversations/${conversationId}/ai-stream`, {
        content,
      })) {
        yield chunk;
      }
    } catch (error) {
      console.error('[Conversation Stream Error]', error);
      throw error;
    }
  }

  // Conversation actions
  static async closeConversation(id: string): Promise<APIResponse<void>> {
    return apiClient.post(`/conversations/${id}/close`, {});
  }

  static async archiveConversation(id: string): Promise<APIResponse<void>> {
    return apiClient.post(`/conversations/${id}/archive`, {});
  }

  // Activities endpoints
  static async getActivities(leadId: string): Promise<APIResponse<Activity[]>> {
    return apiClient.get(`/leads/${leadId}/activities`);
  }

  static async createActivity(leadId: string, data: Partial<Activity>): Promise<APIResponse<Activity>> {
    return apiClient.post(`/leads/${leadId}/activities`, data);
  }

  static async completeActivity(id: string): Promise<APIResponse<Activity>> {
    return apiClient.patch(`/activities/${id}/complete`, {});
  }
}

export class AnalyticsAPI {
  // Dashboard analytics
  static async getDashboard(): Promise<APIResponse<AnalyticsDashboard>> {
    return apiClient.get('/analytics/dashboard');
  }

  static async getRevenue(period: 'month' | 'quarter' | 'year' = 'month'): Promise<APIResponse<{
    total: number;
    byCountry: Record<string, number>;
    trend: Array<{ date: string; revenue: number }>;
  }>> {
    return apiClient.get('/analytics/revenue', { period });
  }

  static async getConversionMetrics(): Promise<APIResponse<ConversionMetrics>> {
    return apiClient.get('/analytics/conversion-metrics');
  }

  static async getCountryMetrics(): Promise<APIResponse<Record<string, {
    leads: number;
    converted: number;
    revenue: number;
    average: number;
  }>>> {
    return apiClient.get('/analytics/by-country');
  }

  static async getIndustryMetrics(): Promise<APIResponse<Record<string, {
    leads: number;
    converted: number;
    revenue: number;
  }>>> {
    return apiClient.get('/analytics/by-industry');
  }

  static async getTeamPerformance(): Promise<APIResponse<Array<{
    memberId: string;
    name: string;
    leads: number;
    converted: number;
    revenue: number;
    conversionRate: number;
  }>>> {
    return apiClient.get('/analytics/team-performance');
  }

  static async getPipelineMetrics(): Promise<APIResponse<{
    byStatus: Record<string, { count: number; value: number }>;
    timeInPipeline: Record<string, number>;
    conversionRate: number;
  }>> {
    return apiClient.get('/analytics/pipeline-metrics');
  }

  static async generateReport(type: 'sales' | 'leads' | 'performance', format: 'pdf' | 'excel' = 'pdf'): Promise<APIResponse<{ url: string }>> {
    return apiClient.get('/analytics/report', { type, format });
  }
}
