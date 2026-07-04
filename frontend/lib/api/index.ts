// Mock API Service Layer
// All async operations are simulated with realistic delays

export interface AuthUser {
  id: string;
  email: string;
  name: string;
  avatar: string;
  role: "admin" | "sales" | "manager";
}

export interface DashboardKPI {
  title: string;
  value: string;
  change: number;
  isPositive: boolean;
  icon: string;
}

// Authentication
export const authAPI = {
  async login(email: string, password: string): Promise<AuthUser> {
    await new Promise((r) => setTimeout(r, 800));
    if (!email || !password) throw new Error("Invalid credentials");
    return {
      id: "user_001",
      email,
      name: email.split("@")[0],
      avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=" + email,
      role: "sales",
    };
  },

  async logout(): Promise<void> {
    await new Promise((r) => setTimeout(r, 300));
  },

  async getCurrentUser(): Promise<AuthUser | null> {
    await new Promise((r) => setTimeout(r, 400));
    return {
      id: "user_001",
      email: "john.doe@eckoX.com",
      name: "John Doe",
      avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=john.doe",
      role: "sales",
    };
  },
};

// Dashboard Metrics
export const dashboardAPI = {
  async getKPIs(): Promise<DashboardKPI[]> {
    await new Promise((r) => setTimeout(r, 600));
    return [
      {
        title: "Total Pipeline",
        value: "$2.4M",
        change: 12.5,
        isPositive: true,
        icon: "TrendingUp",
      },
      {
        title: "Closed This Month",
        value: "$485K",
        change: 8.2,
        isPositive: true,
        icon: "CheckCircle",
      },
      {
        title: "Active Leads",
        value: "48",
        change: -2.1,
        isPositive: false,
        icon: "Users",
      },
      {
        title: "Win Rate",
        value: "34%",
        change: 3.8,
        isPositive: true,
        icon: "Target",
      },
    ];
  },

  async getRevenueChart(): Promise<{ month: string; value: number }[]> {
    await new Promise((r) => setTimeout(r, 500));
    return [
      { month: "Jan", value: 65000 },
      { month: "Feb", value: 72000 },
      { month: "Mar", value: 58000 },
      { month: "Apr", value: 81000 },
      { month: "May", value: 96000 },
      { month: "Jun", value: 115000 },
    ];
  },

  async getFunnelChart(): Promise<{ stage: string; count: number; percentage: number }[]> {
    await new Promise((r) => setTimeout(r, 500));
    return [
      { stage: "Leads", count: 325, percentage: 100 },
      { stage: "Contacted", count: 198, percentage: 61 },
      { stage: "Qualified", count: 84, percentage: 26 },
      { stage: "Proposal", count: 32, percentage: 10 },
      { stage: "Closed", count: 11, percentage: 3 },
    ];
  },

  async getActivityChart(): Promise<{ day: string; count: number }[]> {
    await new Promise((r) => setTimeout(r, 500));
    const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    return days.map((day) => ({
      day,
      count: Math.floor(Math.random() * 25) + 8,
    }));
  },

  async getConversionRate(): Promise<number> {
    await new Promise((r) => setTimeout(r, 300));
    return 0.34;
  },
};

// Leads API
export const leadsAPI = {
  async getLeads(status?: string, limit: number = 50) {
    await new Promise((r) => setTimeout(r, 700));
    // Simulated based on leads data structure
    return {
      total: 325,
      leads: Array.from({ length: limit }, (_, i) => ({
        id: `lead_${i}`,
        company: "Company " + i,
        contact: "Contact " + i,
        status: status || "new",
        value: Math.random() * 100000,
      })),
    };
  },

  async updateLeadStatus(leadId: string, status: string) {
    await new Promise((r) => setTimeout(r, 400));
    return { id: leadId, status, updated: true };
  },

  async createNote(leadId: string, note: string) {
    await new Promise((r) => setTimeout(r, 300));
    return { leadId, note, createdAt: new Date().toISOString() };
  },
};

// Quotes/CPQ API
export const quotesAPI = {
  async createQuote(leadId: string, items: any[]) {
    await new Promise((r) => setTimeout(r, 800));
    return {
      id: "quote_" + Date.now(),
      leadId,
      items,
      total: items.reduce((sum, item) => sum + item.total, 0),
      createdAt: new Date().toISOString(),
    };
  },

  async getQuotes(limit: number = 20) {
    await new Promise((r) => setTimeout(r, 600));
    return {
      total: 127,
      quotes: Array.from({ length: limit }, (_, i) => ({
        id: `quote_${i}`,
        leadId: `lead_${i}`,
        total: Math.random() * 100000,
        createdAt: new Date(Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000).toISOString(),
        status: ["draft", "sent", "accepted", "rejected"][Math.floor(Math.random() * 4)],
      })),
    };
  },
};

// Conversations API
export const conversationsAPI = {
  async getConversations(limit: number = 20) {
    await new Promise((r) => setTimeout(r, 500));
    return Array.from({ length: limit }, (_, i) => ({
      id: `conv_${i}`,
      leadId: `lead_${i}`,
      channel: ["email", "sms", "call", "chat"][Math.floor(Math.random() * 4)],
      lastMessage: "Message preview...",
      lastMessageTime: new Date(Date.now() - Math.random() * 7 * 24 * 60 * 60 * 1000).toISOString(),
      unread: Math.random() > 0.7,
    }));
  },

  async getConversationThread(conversationId: string) {
    await new Promise((r) => setTimeout(r, 600));
    return Array.from({ length: 10 }, (_, i) => ({
      id: `msg_${i}`,
      conversationId,
      sender: i % 2 === 0 ? "user" : "contact",
      message: `Message ${i} content...`,
      timestamp: new Date(Date.now() - (10 - i) * 60 * 60 * 1000).toISOString(),
    }));
  },

  async sendMessage(conversationId: string, message: string) {
    await new Promise((r) => setTimeout(r, 300));
    return {
      id: "msg_" + Date.now(),
      conversationId,
      sender: "user",
      message,
      timestamp: new Date().toISOString(),
    };
  },
};

// AI Chat API (Streaming response)
export const chatAPI = {
  async *streamChatResponse(messages: { role: string; content: string }[]) {
    const responses = [
      "Based on the data I'm seeing, here's what I recommend:",
      "Let me analyze this opportunity for you.",
      "I see you're working with a high-value lead. Here are some insights:",
    ];
    const response = responses[Math.floor(Math.random() * responses.length)];

    // Simulate streaming response
    for (const chunk of response.split(" ")) {
      await new Promise((r) => setTimeout(r, 30 + Math.random() * 50));
      yield chunk + " ";
    }
  },

  async generateQuoteInsight(leadData: any) {
    await new Promise((r) => setTimeout(r, 1200));
    return {
      insight: "This prospect shows high purchase intent. Recommend immediate follow-up.",
      nextSteps: ["Schedule demo", "Send pricing", "Technical validation"],
      riskFactors: ["Budget concern", "Competing vendor"],
    };
  },
};

// Analytics API
export const analyticsAPI = {
  async getTeamPerformance() {
    await new Promise((r) => setTimeout(r, 600));
    return [
      { name: "John Doe", closed: 8, pipeline: 185000, winRate: 0.42 },
      { name: "Sarah Smith", closed: 6, pipeline: 210000, winRate: 0.38 },
      { name: "Mike Johnson", closed: 5, pipeline: 155000, winRate: 0.31 },
    ];
  },

  async getForecast(months: number = 3) {
    await new Promise((r) => setTimeout(r, 500));
    return Array.from({ length: months }, (_, i) => ({
      month: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"][i],
      forecast: 150000 + Math.random() * 200000,
      actual: null,
    }));
  },
};

// Notifications API
export const notificationsAPI = {
  async getNotifications(limit: number = 10) {
    await new Promise((r) => setTimeout(r, 400));
    return Array.from({ length: limit }, (_, i) => ({
      id: `notif_${i}`,
      type: ["lead_update", "quote_sent", "task_due", "message"][Math.floor(Math.random() * 4)],
      title: "Notification " + i,
      message: "Notification message preview...",
      timestamp: new Date(Date.now() - Math.random() * 24 * 60 * 60 * 1000).toISOString(),
      read: Math.random() > 0.3,
    }));
  },

  async markAsRead(notificationId: string) {
    await new Promise((r) => setTimeout(r, 200));
    return { id: notificationId, read: true };
  },
};
