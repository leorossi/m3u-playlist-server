import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { api } from '../api/client';
import type { AppUser } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

const inputCls = "px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full";

export default function UsersPage() {
  const { user: me } = useAuth();
  const [users, setUsers] = useState<AppUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isAdmin, setIsAdmin] = useState(false);
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState('');

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editEmail, setEditEmail] = useState('');
  const [editPassword, setEditPassword] = useState('');
  const [editIsAdmin, setEditIsAdmin] = useState(false);
  const [editError, setEditError] = useState('');
  const [editSaving, setEditSaving] = useState(false);

  const load = () =>
    api.get<AppUser[]>('/api/users')
      .then(setUsers)
      .catch(() => setError('Failed to load users'))
      .finally(() => setLoading(false));

  useEffect(() => { load(); }, []);

  const handleCreate = async (e: FormEvent) => {
    e.preventDefault();
    setCreateError('');
    setCreating(true);
    try {
      const roles = isAdmin ? ['ROLE_ADMIN', 'ROLE_USER'] : ['ROLE_USER'];
      const u = await api.post<AppUser>('/api/users', { email, password, roles });
      setUsers(prev => [...prev, u]);
      setEmail('');
      setPassword('');
      setIsAdmin(false);
    } catch (err) {
      setCreateError(err instanceof Error ? err.message : 'Create failed');
    } finally {
      setCreating(false);
    }
  };

  const startEdit = (u: AppUser) => {
    setEditingId(u.id);
    setEditEmail(u.email);
    setEditPassword('');
    setEditIsAdmin(u.roles.includes('ROLE_ADMIN'));
    setEditError('');
  };

  const handleUpdate = async (e: FormEvent) => {
    e.preventDefault();
    if (!editingId) return;
    setEditError('');
    setEditSaving(true);
    try {
      const roles = editIsAdmin ? ['ROLE_ADMIN', 'ROLE_USER'] : ['ROLE_USER'];
      const body: Record<string, unknown> = { email: editEmail, roles };
      if (editPassword) body.password = editPassword;
      const updated = await api.put<AppUser>(`/api/users/${editingId}`, body);
      setUsers(prev => prev.map(u => u.id === editingId ? updated : u));
      setEditingId(null);
    } catch (err) {
      setEditError(err instanceof Error ? err.message : 'Update failed');
    } finally {
      setEditSaving(false);
    }
  };

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this user?')) return;
    try {
      await api.delete(`/api/users/${id}`);
      setUsers(prev => prev.filter(u => u.id !== id));
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Delete failed');
    }
  };

  return (
    <div>
      <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Users</h2>

      <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Create User</h3>
        <form onSubmit={handleCreate}>
          {createError && (
            <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-400">
              {createError}
            </div>
          )}
          <div className="flex flex-wrap gap-3 items-end">
            <div className="flex flex-col gap-1.5 flex-1 min-w-40">
              <label className="text-xs font-medium text-gray-500 dark:text-gray-400">Email</label>
              <input type="email" value={email} onChange={e => setEmail(e.target.value)} required className={inputCls} />
            </div>
            <div className="flex flex-col gap-1.5 flex-1 min-w-40">
              <label className="text-xs font-medium text-gray-500 dark:text-gray-400">Password</label>
              <input type="password" value={password} onChange={e => setPassword(e.target.value)} required className={inputCls} />
            </div>
            <div className="flex items-center gap-2 pb-0.5">
              <input id="create-admin" type="checkbox" checked={isAdmin} onChange={e => setIsAdmin(e.target.checked)} className="w-4 h-4 accent-blue-600" />
              <label htmlFor="create-admin" className="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Admin</label>
            </div>
            <button type="submit" disabled={creating} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer">
              {creating ? 'Creating…' : 'Create'}
            </button>
          </div>
        </form>
      </div>

      {loading && <p className="text-sm text-gray-400">Loading…</p>}
      {error && <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700">{error}</div>}

      <div className="flex flex-col gap-2">
        {users.map(u => (
          <div key={u.id} className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-4">
            {editingId === u.id ? (
              <form onSubmit={handleUpdate}>
                {editError && (
                  <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-400">
                    {editError}
                  </div>
                )}
                <div className="flex flex-wrap gap-3 items-end">
                  <div className="flex flex-col gap-1.5 flex-1 min-w-40">
                    <input type="email" value={editEmail} onChange={e => setEditEmail(e.target.value)} required className={inputCls} />
                  </div>
                  <div className="flex flex-col gap-1.5 flex-1 min-w-40">
                    <input type="password" value={editPassword} onChange={e => setEditPassword(e.target.value)} placeholder="New password (leave blank to keep)" className={inputCls} />
                  </div>
                  <div className="flex items-center gap-2">
                    <input id={`edit-admin-${u.id}`} type="checkbox" checked={editIsAdmin} onChange={e => setEditIsAdmin(e.target.checked)} className="w-4 h-4 accent-blue-600" />
                    <label htmlFor={`edit-admin-${u.id}`} className="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Admin</label>
                  </div>
                  <div className="flex gap-2">
                    <button type="submit" disabled={editSaving} className="px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-md disabled:opacity-60 transition-colors cursor-pointer">
                      Save
                    </button>
                    <button type="button" onClick={() => setEditingId(null)} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                      Cancel
                    </button>
                  </div>
                </div>
              </form>
            ) : (
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-sm text-gray-900 dark:text-gray-100">{u.email}</span>
                  {u.roles.includes('ROLE_ADMIN') && (
                    <span className="text-xs px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                      admin
                    </span>
                  )}
                </div>
                <div className="flex gap-2">
                  <button onClick={() => startEdit(u)} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                    Edit
                  </button>
                  {u.id !== me?.id && (
                    <button onClick={() => handleDelete(u.id)} className="px-3 py-1.5 text-xs font-medium bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors cursor-pointer">
                      Delete
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
