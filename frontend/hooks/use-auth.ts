import { create } from 'zustand';
import { AuthAPI, LoginRequest, RegisterRequest, AuthUser } from '@/lib/api/auth.api';

interface AuthState {
  user: AuthUser | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;

  login: (credentials: LoginRequest) => Promise<boolean>;
  register: (data: RegisterRequest) => Promise<boolean>;
  logout: () => Promise<void>;
  updateProfile: (data: Partial<AuthUser> & { password?: string; password_confirmation?: string }) => Promise<boolean>;
  checkAuth: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  isAuthenticated: false,
  isLoading: true,
  error: null,

  clearError: () => set({ error: null }),

  login: async (credentials) => {
    set({ isLoading: true, error: null });
    try {
      const response = await AuthAPI.login(credentials);
      if (response.success && response.data) {
        set({
          user: response.data.user,
          isAuthenticated: true,
          isLoading: false,
        });
        return true;
      } else {
        set({ error: response.error || 'Login failed', isLoading: false });
        return false;
      }
    } catch (err: any) {
      set({ error: err.message || 'Login failed', isLoading: false });
      return false;
    }
  },

  register: async (data) => {
    set({ isLoading: true, error: null });
    try {
      const response = await AuthAPI.register(data);
      if (response.success && response.data) {
        set({
          user: response.data.user,
          isAuthenticated: true,
          isLoading: false,
        });
        return true;
      } else {
        set({ error: response.error || 'Registration failed', isLoading: false });
        return false;
      }
    } catch (err: any) {
      set({ error: err.message || 'Registration failed', isLoading: false });
      return false;
    }
  },

  logout: async () => {
    set({ isLoading: true });
    try {
      await AuthAPI.logout();
    } catch (err) {
      console.error('Logout request failed', err);
    } finally {
      set({
        user: null,
        isAuthenticated: false,
        isLoading: false,
      });
      if (typeof window !== 'undefined') {
        window.location.href = '/login';
      }
    }
  },

  updateProfile: async (data) => {
    set({ isLoading: true, error: null });
    try {
      const response = await AuthAPI.updateProfile(data);
      if (response.success && response.data) {
        set({
          user: response.data.user,
          isLoading: false,
        });
        return true;
      } else {
        set({ error: response.error || 'Failed to update profile', isLoading: false });
        return false;
      }
    } catch (err: any) {
      set({ error: err.message || 'Failed to update profile', isLoading: false });
      return false;
    }
  },

  checkAuth: async () => {
    try {
      const response = await AuthAPI.getMe();
      if (response.success && response.data?.user) {
        set({
          user: response.data.user,
          isAuthenticated: true,
          isLoading: false,
        });
      } else {
        set({
          user: null,
          isAuthenticated: false,
          isLoading: false,
        });
      }
    } catch (err) {
      set({
        user: null,
        isAuthenticated: false,
        isLoading: false,
      });
    }
  },
}));

export function useAuth() {
  const store = useAuthStore();
  return store;
}
