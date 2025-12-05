import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { authService, type User } from '@/services/auth';

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: any) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const initAuth = async () => {
      try {
        const currentUser = await authService.checkSession();
        setUser(currentUser);
      } catch (error) {
        console.error('Auth initialization failed', error);
      } finally {
        setIsLoading(false);
      }
    };

    initAuth();
    
    // Optional: Setup interval for session check
    const interval = setInterval(async () => {
        const currentUser = await authService.getCurrentUser(); // Just check, don't refresh automatically aggressively?
        // Or checkSession() which tries refresh.
        if (!currentUser) {
             // If we were logged in and now aren't, maybe logout?
             // But maybe session expired.
             // For now, let's keep it simple.
        }
    }, 5 * 60 * 1000);

    return () => clearInterval(interval);
  }, []);

  const login = async (credentials: any) => {
    const user = await authService.login(credentials);
    setUser(user);
  };

  const logout = async () => {
    await authService.logout();
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: !!user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

