export const UNAUTHORIZED_EVENT = 'app:unauthorized';

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(path, {
    ...init,
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(init.headers ?? {}),
    },
  });

  if (res.status === 401) {
    window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
    throw new Error('Unauthorized');
  }

  if (res.status === 204) {
    return undefined as T;
  }

  const body = await res.json();

  if (!res.ok) {
    throw new Error(body?.error ?? `HTTP ${res.status}`);
  }

  return body as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, data?: unknown) =>
    request<T>(path, { method: 'POST', body: data !== undefined ? JSON.stringify(data) : undefined }),
  put: <T>(path: string, data?: unknown) =>
    request<T>(path, { method: 'PUT', body: data !== undefined ? JSON.stringify(data) : undefined }),
  patch: <T>(path: string, data?: unknown) =>
    request<T>(path, { method: 'PATCH', body: data !== undefined ? JSON.stringify(data) : undefined }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
};

export async function uploadPlaylist(name: string, file: File): Promise<Playlist> {
  const form = new FormData();
  form.append('name', name);
  form.append('file', file);

  const res = await fetch('/api/lists', {
    method: 'POST',
    credentials: 'include',
    body: form,
  });

  if (res.status === 401) {
    window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
    throw new Error('Unauthorized');
  }

  const body = await res.json();
  if (!res.ok) throw new Error(body?.error ?? `HTTP ${res.status}`);
  return body as Playlist;
}

// ── Types ──────────────────────────────────────────────────────────────────

export interface AuthUser {
  id: string;
  email: string;
  roles: string[];
}

export interface Playlist {
  id: string;
  name: string;
  slug: string;
  channelCount: number;
  enabledCount: number;
  createdAt: string;
  updatedAt: string;
}

export interface Channel {
  id: string;
  position: number;
  name: string;
  url: string;
  tvgId: string | null;
  tvgName: string | null;
  tvgLogo: string | null;
  enabled: boolean;
}

export interface RequestLog {
  id: string;
  requestedAt: string;
  ipAddress: string;
  userAgent: string | null;
  headers: Record<string, string | string[]>;
}

export interface LogsPage {
  data: RequestLog[];
  total: number;
  page: number;
  limit: number;
}

export interface AppUser {
  id: string;
  email: string;
  roles: string[];
  createdAt: string;
}
