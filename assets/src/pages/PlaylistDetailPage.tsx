import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import type { Playlist, Channel } from '../api/client';

const inputCls = "px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full";

export default function PlaylistDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [playlist, setPlaylist] = useState<Playlist | null>(null);
  const [channels, setChannels] = useState<Channel[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [saveOk, setSaveOk] = useState(false);

  const [pendingToggles, setPendingToggles] = useState<Record<string, boolean>>({});
  const [savingChannels, setSavingChannels] = useState(false);

  useEffect(() => {
    if (!id) return;
    Promise.all([
      api.get<Playlist>(`/api/lists/${id}`),
      api.get<Channel[]>(`/api/lists/${id}/channels`),
    ])
      .then(([p, ch]) => {
        setPlaylist(p);
        setName(p.name);
        setSlug(p.slug);
        setChannels(ch.sort((a, b) => a.position - b.position));
      })
      .catch(() => setError('Failed to load playlist'))
      .finally(() => setLoading(false));
  }, [id]);

  const handleSaveInfo = async (e: FormEvent) => {
    e.preventDefault();
    setSaveError('');
    setSaveOk(false);
    setSaving(true);
    try {
      const updated = await api.put<Playlist>(`/api/lists/${id}`, { name, slug });
      setPlaylist(updated);
      setSaveOk(true);
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const toggleChannel = (channelId: string, enabled: boolean) => {
    setPendingToggles(prev => ({ ...prev, [channelId]: enabled }));
    setChannels(prev => prev.map(c => c.id === channelId ? { ...c, enabled } : c));
  };

  const handleSaveChannels = async () => {
    if (Object.keys(pendingToggles).length === 0) return;
    setSavingChannels(true);
    try {
      const payload = Object.entries(pendingToggles).map(([cid, enabled]) => ({ id: cid, enabled }));
      const updated = await api.patch<Channel[]>(`/api/lists/${id}/channels`, payload);
      setChannels(updated.sort((a, b) => a.position - b.position));
      setPendingToggles({});
    } catch {
      alert('Failed to save channel changes');
    } finally {
      setSavingChannels(false);
    }
  };

  const enabledCount = channels.filter(c => c.enabled).length;
  const hasPendingToggles = Object.keys(pendingToggles).length > 0;

  if (loading) return <p className="text-sm text-gray-400">Loading…</p>;
  if (error) return <div className="text-sm px-3 py-2 rounded-md bg-red-50 border border-red-200 text-red-700">{error}</div>;
  if (!playlist) return null;

  return (
    <div>
      <div className="flex items-center gap-3 mb-5">
        <button onClick={() => navigate('/')} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">
          ← Back
        </button>
        <button onClick={() => navigate(`/playlists/${id}/logs`)} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">
          Logs
        </button>
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{playlist.name}</h2>
      </div>

      <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Playlist Info</h3>
        <form onSubmit={handleSaveInfo}>
          {saveError && (
            <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-400">
              {saveError}
            </div>
          )}
          {saveOk && (
            <div className="text-sm px-3 py-2 mb-3 rounded-md bg-green-50 border border-green-200 text-green-700 dark:bg-green-950 dark:border-green-800 dark:text-green-400">
              Saved!
            </div>
          )}
          <div className="flex flex-wrap gap-3 items-end mb-3">
            <div className="flex flex-col gap-1.5 flex-1 min-w-40">
              <label className="text-xs font-medium text-gray-500 dark:text-gray-400">Name</label>
              <input type="text" value={name} onChange={e => setName(e.target.value)} required className={inputCls} />
            </div>
            <div className="flex flex-col gap-1.5 flex-1 min-w-40">
              <label className="text-xs font-medium text-gray-500 dark:text-gray-400">Slug</label>
              <input type="text" value={slug} onChange={e => setSlug(e.target.value)} required className={inputCls} />
            </div>
            <button type="submit" disabled={saving} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer">
              {saving ? 'Saving…' : 'Save'}
            </button>
          </div>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Public URL:{' '}
            <a
              href={`/lists/${playlist.slug}`}
              target="_blank"
              rel="noreferrer"
              className="font-mono text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 underline underline-offset-2"
            >
              {`${window.location.origin}/lists/${playlist.slug}`}
            </a>
          </p>
        </form>
      </div>

      <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-5">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Channels ({enabledCount} / {channels.length} enabled)
          </h3>
          {hasPendingToggles && (
            <button onClick={handleSaveChannels} disabled={savingChannels} className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition-colors disabled:opacity-60 cursor-pointer">
              {savingChannels ? 'Saving…' : `Save changes (${Object.keys(pendingToggles).length})`}
            </button>
          )}
        </div>

        <div className="divide-y divide-gray-100 dark:divide-gray-800">
          {channels.map(ch => (
            <label
              key={ch.id}
              className={`flex items-center gap-3 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 px-1 rounded ${!ch.enabled ? 'opacity-40' : ''}`}
            >
              <input
                type="checkbox"
                checked={ch.enabled}
                onChange={e => toggleChannel(ch.id, e.target.checked)}
                className="w-4 h-4 accent-blue-600"
              />
              <span className="text-xs text-gray-400 w-8 text-right shrink-0">{ch.position}</span>
              <span className="text-sm text-gray-900 dark:text-gray-100 flex-1">{ch.name}</span>
              {ch.tvgLogo && (
                <img src={ch.tvgLogo} alt="" className="w-8 h-8 object-contain shrink-0" onError={e => (e.currentTarget.style.display = 'none')} />
              )}
            </label>
          ))}
        </div>
      </div>
    </div>
  );
}
