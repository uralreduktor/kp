import axios from 'axios';

// Types
export interface User {
  id?: string | number; // ID might not be present in all responses
  email: string;
  full_name?: string;
  // Add other fields as needed
}

export interface LoginCredentials {
  email: string;
  password: string;
  rememberDevice: boolean;
}

// API Client
const api = axios.create({
  baseURL: '/api',
  withCredentials: true, // Important for cookies
  headers: {
    'Content-Type': 'application/json',
  },
});

// Helper to read cookie
function getCookie(name: string): string | null {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop()?.split(';').shift() || null;
  return null;
}

// Interceptor to add CSRF token
api.interceptors.request.use((config) => {
  // Try common CSRF cookie names
  const csrfToken = getCookie('X-CSRF-Token') || getCookie('csrf_token') || getCookie('XSRF-TOKEN');
  if (csrfToken) {
    // Add to header (standard for double-submit cookie)
    config.headers['X-CSRF-Token'] = csrfToken;
  }
  return config;
});

class AuthService {
  /**
   * Generates a device fingerprint based on non-sensitive data
   */
  private async generateFingerprint(): Promise<string> {
    const data = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      Intl.DateTimeFormat().resolvedOptions().timeZone,
      navigator.platform,
    ].join('|');

    const encoder = new TextEncoder();
    const dataBuffer = encoder.encode(data);
    const hashBuffer = await crypto.subtle.digest('SHA-256', dataBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
  }

  /**
   * Login user
   */
  async login(credentials: LoginCredentials): Promise<User> {
    const fingerprint = await this.generateFingerprint();
    
    const response = await api.post('/auth/login', {
      ...credentials,
      fingerprint,
    });

    return response.data;
  }

  /**
   * Refresh session using device token
   */
  async refreshSession(): Promise<User | null> {
    try {
      const fingerprint = await this.generateFingerprint();
      const response = await api.post('/auth/refresh', null, {
        headers: {
          'X-Device-Fingerprint': fingerprint,
        },
      });
      return response.data;
    } catch (error) {
      return null;
    }
  }

  /**
   * Get current user info
   */
  async getCurrentUser(): Promise<User | null> {
    try {
      const response = await api.get('/auth/me');
      return response.data;
    } catch (error) {
      return null;
    }
  }

  /**
   * Logout
   */
  async logout(): Promise<void> {
    await api.post('/auth/logout');
    window.location.reload();
  }

  /**
   * Check session (get user or refresh)
   */
  async checkSession(): Promise<User | null> {
    let user = await this.getCurrentUser();
    if (user) return user;

    user = await this.refreshSession();
    return user;
  }
}

export const authService = new AuthService();


