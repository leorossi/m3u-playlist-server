import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { api, uploadPlaylist } from '../api/client';
import type { Playlist } from '../api/client';

const inputCls = "px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full";

export default function DashboardPage() {
  const [playlists, setPlaylists] = useState<Playlist[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [name, setName] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState('');

  const load = () =>
    api.get<Playlist[]>('/api/lists')
      .then(setPlaylists)
      .catch(() => setError('Failed to load playlists'))
      .finally(() => setLoading(false));

  useEffect(() => { load(); }, []);

  const handleUpload = async (e: FormEvent) => {
    e.preventDefault();
    if (!file) return;
    setUploadError('');
    setUploading(true);
    try {
      const playlist = await uploadPlaylist(name, file);
      setPlaylists(prev => [playlist, ...prev]);
      setName('');
      setFile(null);
      (e.target as HTMLFormElement).reset();
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Upload failed');
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this playlist?')) return;
    try {
      await api.delete(`/api/lists/${id}`);
      setPlaylists(prev => prev.filter(p => p.id !== id));
    } catch {
      alert('Delete failed');
    }
  };

  return (
    <div>
      <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Playlists</h2>

      <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-5 mb-5">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Upload New Playlist</h3>
        <form onSubmit={handleUpload}>
          {uploadError && (
            <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-400">
              {uploadError}
            </div>
          )}
          <div className="flex flex-wrap gap-3 items-end">
            <div className="flex flex-col gap-1.5 flex-1 min-w-40">
              <label className="text-xs font-medium text-gray-500 dark:text-gray-400">Name</label>
              <input type="text" value={name} onChange={e => setName(e.target.value)} required placeholder="My IPTV list" className={inputCls} />
            </div>
            <div className="flex flex-col gap-1.5 flex-1 min-w-40">
              <label className="text-xs font-medium text-gray-500 dark:text-gray-400">M3U8 File</label>
              <input type="file" accept=".m3u,.m3u8" required onChange={e => setFile(e.target.files?.[0] ?? null)} className={inputCls} />
            </div>
            <button type="submit" disabled={uploading} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer">
              {uploading ? 'Uploading…' : 'Upload'}
            </button>
          </div>
        </form>
      </div>

      {loading && <p className="text-sm text-gray-400">Loading…</p>}
      {error && <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700">{error}</div>}

      {!loading && playlists.length === 0 && (
        <p className="text-center text-gray-400 py-10">No playlists yet. Upload one above.</p>
      )}

      <div className="flex flex-col gap-3">
        {playlists.map(p => (
          <div key={p.id} className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-5 flex items-start justify-between gap-4">
            <div className="flex-1 min-w-0">
              <h3 className="text-sm font-semibold mb-1">
                <Link to={`/playlists/${p.id}`} className="text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 no-underline">
                  {p.name}
                </Link>
              </h3>
              <div className="flex items-center gap-2 mb-1">
                <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                  {p.enabledCount} / {p.channelCount} channels
                </span>
                <a
                  href={`/lists/${p.slug}`}
                  target="_blank"
                  rel="noreferrer"
                  className="text-xs font-mono text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 no-underline truncate"
                >
                  {`${window.location.origin}/lists/${p.slug}`}
                </a>
              </div>
              <p className="text-xs text-gray-400">Updated {new Date(p.updatedAt).toLocaleString()}</p>
            </div>
            <div className="flex gap-2 shrink-0">
              <Link to={`/playlists/${p.id}`} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 no-underline transition-colors">
                Edit
              </Link>
              <Link to={`/playlists/${p.id}/logs`} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 no-underline transition-colors">
                Logs
              </Link>
              <button onClick={() => handleDelete(p.id)} className="px-3 py-1.5 text-xs font-medium bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors cursor-pointer">
                Delete
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
