import axios, { AxiosInstance, AxiosError } from 'axios';

// API response wrapper format
export interface APIResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  code?: number;
  message?: string;
}

const TOKEN_KEY = 'eckox_auth_token';

class APIClient {
  private client: AxiosInstance;
  private baseURL: string;

  constructor() {
    // Dynamic loading of NEXT_PUBLIC_API_URL, fallback only used if undefined
    this.baseURL = process.env.NEXT_PUBLIC_API_URL || 'https://eckox-ai-platform.onrender.com/api';

    this.client = axios.create({
      baseURL: this.baseURL,
      timeout: 30000,
      withCredentials: true,
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    // Request interceptor — attach Bearer token on every request
    this.client.interceptors.request.use((config) => {
      const token = this.getToken();
      if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
      }
      return config;
    });

    // Response interceptor - handle errors
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        const status = error.response?.status;

        // Handle 401 - unauthorized
        if (status === 401) {
          if (typeof window !== 'undefined') {
            if (!window.location.pathname.startsWith('/login')) {
              window.location.href = '/login';
            }
          }
        }

        // Handle 403 - forbidden
        if (status === 403) {
          if (typeof window !== 'undefined') {
            window.location.href = '/403';
          }
        }

        return Promise.reject(error);
      }
    );
  }

  // ── Token helpers ──────────────────────────────────────────────────────────
  getToken(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(TOKEN_KEY);
  }

  setToken(token: string | null): void {
    if (typeof window === 'undefined') return;
    if (token) {
      localStorage.setItem(TOKEN_KEY, token);
    } else {
      localStorage.removeItem(TOKEN_KEY);
    }
  }

  // Sanctum CSRF Cookie Initialization
  async initializeCSRF(): Promise<void> {
    try {
      // Sanctum csrf-cookie endpoint is typically outside /api base path (e.g., at domain root)
      const baseDomain = this.baseURL.endsWith('/api')
        ? this.baseURL.substring(0, this.baseURL.length - 4)
        : this.baseURL;
      await this.client.get(`${baseDomain}/sanctum/csrf-cookie`);
    } catch (error) {
      console.error('[CSRF Init Error]', error);
      throw error;
    }
  }

  // Helper to format success responses into APIResponse structure
  private formatResponse<T>(axiosResponse: any): APIResponse<T> {
    const data = axiosResponse.data;

    // Map Spatie roles if user is in response
    if (data && typeof data === 'object') {
      if ('user' in data && data.user && typeof data.user === 'object') {
        const rawUser = data.user;
        const roles = Array.isArray(rawUser.roles)
          ? rawUser.roles.map((r: any) => typeof r === 'string' ? r : r.name)
          : (rawUser.role ? [rawUser.role] : []);

        data.user = {
          ...rawUser,
          id: String(rawUser.id),
          roles,
        };
      }
    }

    return {
      success: true,
      data: data,
      message: data?.message || '',
    };
  }

  // Generic methods
  async get<T = any>(url: string, params?: Record<string, any>): Promise<APIResponse<T>> {
    try {
      const response = await this.client.get(url, { params });
      return this.formatResponse<T>(response);
    } catch (error) {
      return this.handleError<T>(error);
    }
  }

  async post<T = any>(url: string, data?: any): Promise<APIResponse<T>> {
    try {
      const response = await this.client.post(url, data);
      return this.formatResponse<T>(response);
    } catch (error) {
      return this.handleError<T>(error);
    }
  }

  async put<T = any>(url: string, data?: any): Promise<APIResponse<T>> {
    try {
      const response = await this.client.put(url, data);
      return this.formatResponse<T>(response);
    } catch (error) {
      return this.handleError<T>(error);
    }
  }

  async patch<T = any>(url: string, data?: any): Promise<APIResponse<T>> {
    try {
      const response = await this.client.patch(url, data);
      return this.formatResponse<T>(response);
    } catch (error) {
      return this.handleError<T>(error);
    }
  }

  async delete<T = any>(url: string): Promise<APIResponse<T>> {
    try {
      const response = await this.client.delete(url);
      return this.formatResponse<T>(response);
    } catch (error) {
      return this.handleError<T>(error);
    }
  }

  // Streaming support (fetch based SSE fallback used in ai.api.ts)
  getBaseURL(): string {
    return this.baseURL;
  }

  private handleError<T>(error: any): APIResponse<T> {
    if (error.response?.data) {
      const respData = error.response.data;
      return {
        success: false,
        error: respData.message || respData.error || 'Request failed',
        code: error.response.status,
        data: respData,
      };
    }

    return {
      success: false,
      error: error.message || 'Unknown error occurred',
      code: error.response?.status || 500,
    };
  }
}

export const apiClient = new APIClient();
