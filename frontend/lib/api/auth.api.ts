import { apiClient, APIResponse } from './client';

export interface LoginRequest {
  email: string;
  password: string;
  device_name?: string;
}

export interface AuthUser {
  id: string;
  email: string;
  name: string;
  roles: string[];
}

export interface AuthResponse {
  user: AuthUser;
  token: string;
  message?: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
  password_confirmation: string;
  name: string;
  role: 'admin' | 'manager' | 'sales-agent' | 'super-admin';
}

export class AuthAPI {
  static async login(credentials: LoginRequest): Promise<APIResponse<AuthResponse>> {
    // Obtain CSRF cookie before making the request
    await apiClient.initializeCSRF();
    return apiClient.post<AuthResponse>('/auth/login', credentials);
  }

  static async register(data: RegisterRequest): Promise<APIResponse<AuthResponse>> {
    // Obtain CSRF cookie before making the request
    await apiClient.initializeCSRF();
    return apiClient.post<AuthResponse>('/auth/register', data);
  }

  static async logout(): Promise<APIResponse<void>> {
    return apiClient.post<void>('/auth/logout');
  }

  static async getMe(): Promise<APIResponse<{ user: AuthUser }>> {
    return apiClient.get<{ user: AuthUser }>('/auth/me');
  }

  static async updateProfile(data: Partial<AuthUser> & { password?: string; password_confirmation?: string }): Promise<APIResponse<{ user: AuthUser }>> {
    return apiClient.put<{ user: AuthUser }>('/auth/profile', data);
  }
}

