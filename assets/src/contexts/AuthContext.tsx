import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import type { ReactNode } from 'react';
import { api, UNAUTHORIZED_EVENT } from '../api/client';
import type { AuthUser } from '../api/client';

interface AuthContextValue {
  user: AuthUser | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  isAdmin: boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  const clearUser = useCallback(() => setUser(null), []);

  useEffect(() => {
    api.get<AuthUser>('/api/me')
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    window.addEventListener(UNAUTHORIZED_EVENT, clearUser);
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, clearUser);
  }, [clearUser]);

  const login = async (email: string, password: string) => {
    const u = await api.post<AuthUser>('/api/login', { email, password });
    setUser(u);
  };

  const logout = async () => {
    await api.post('/api/logout');
    setUser(null);
  };

  const isAdmin = user?.roles.includes('ROLE_ADMIN') ?? false;

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, isAdmin }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
