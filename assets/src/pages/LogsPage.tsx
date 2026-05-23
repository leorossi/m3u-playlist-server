import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import type { Playlist, LogsPage as LogsPageData } from '../api/client';
import { formatDateTime } from '../utils/format';

export default function LogsPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [playlist, setPlaylist] = useState<Playlist | null>(null);
  const [logs, setLogs] = useState<LogsPageData | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [lastChecked, setLastChecked] = useState<Date | null>(null);

  useEffect(() => {
    if (!id) return;
    api.get<Playlist>(`/api/lists/${id}`)
      .then(setPlaylist)
      .catch(() => setError('Failed to load playlist'));
  }, [id]);

  useEffect(() => {
    if (!id) return;
    setLoading(true);

    const fetchLogs = () => {
      api.get<LogsPageData>(`/api/lists/${id}/logs?page=${page}&limit=20`)
        .then(data => {
          setLogs(data);
          setLastChecked(new Date());
        })
        .catch(() => setError('Failed to load logs'))
        .finally(() => setLoading(false));
    };

    fetchLogs();
    const timer = setInterval(fetchLogs, 5000);
    return () => clearInterval(timer);
  }, [id, page]);

  const totalPages = logs ? Math.ceil(logs.total / logs.limit) : 0;

  return (
    <div>
      <div className="flex items-center gap-3 mb-5">
        <button onClick={() => navigate(`/playlists/${id}`)} className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">
          ← Back
        </button>
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
          Logs — {playlist?.name}
        </h2>
      </div>

      {error && <div className="text-sm px-3 py-2 mb-3 rounded-md bg-red-50 border border-red-200 text-red-700">{error}</div>}
      {loading && <p className="text-sm text-gray-400">Loading…</p>}

      {logs && (
        <>
          <div className="flex items-center justify-between mb-3">
            <p className="text-xs text-gray-400">{logs.total} total requests</p>
            {lastChecked && (
              <p className="text-xs text-gray-400">Last checked: {formatDateTime(lastChecked)}</p>
            )}
          </div>

          {logs.data.length === 0 ? (
            <p className="text-center text-gray-400 py-10">No requests logged yet.</p>
          ) : (
            <div className="flex flex-col gap-3">
              {logs.data.map(log => (
                <div key={log.id} className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm p-4">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {formatDateTime(log.requestedAt)}
                    </span>
                    <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 font-mono">
                      {log.ipAddress}
                    </span>
                  </div>
                  <p className="text-xs text-gray-400 mb-2">{log.userAgent ?? '(no user agent)'}</p>
                  <details className="text-xs">
                    <summary className="text-gray-400 cursor-pointer hover:text-gray-600 select-none">Headers</summary>
                    <pre className="mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-md text-xs overflow-auto max-h-48 text-gray-700 dark:text-gray-300">
                      {JSON.stringify(log.headers, null, 2)}
                    </pre>
                  </details>
                </div>
              ))}
            </div>
          )}

          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-4 mt-6">
              <button
                onClick={() => setPage(p => p - 1)}
                disabled={page <= 1}
                className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
              >
                Previous
              </button>
              <span className="text-sm text-gray-500">{page} / {totalPages}</span>
              <button
                onClick={() => setPage(p => p + 1)}
                disabled={page >= totalPages}
                className="px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
