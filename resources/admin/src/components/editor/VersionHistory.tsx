import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { History, RotateCcw, Loader2, CheckCircle } from 'lucide-react';
import { api } from '@/lib/api';
import { useEditorStore } from '@/stores/editorStore';

interface Version {
  id: string;
  version_number: number;
  published_by: string | null;
  published_at: string;
  created_at: string;
  published_by_user?: { name: string } | null;
}

interface VersionHistoryProps {
  siteId: string;
  pageId: string;
  type: 'pages' | 'posts';
}

export function VersionHistory({ siteId, pageId, type }: VersionHistoryProps) {
  const setBlocks = useEditorStore((s) => s.setBlocks);
  const [restoring, setRestoring] = useState<string | null>(null);
  const [restored, setRestored] = useState<string | null>(null);

  const { data: versions, isLoading } = useQuery<Version[]>({
    queryKey: ['versions', type, siteId, pageId],
    queryFn: () => api.get(`/sites/${siteId}/${type}/${pageId}/versions`).then(r => r.data.data),
  });

  async function handleRestore(version: Version) {
    if (!confirm(`Restore to version ${version.version_number}? Your current page is saved as a new version first, so you can undo this by restoring it back. (Unsaved edits since your last save are not kept.)`)) return;

    setRestoring(version.id);
    try {
      await api.post(`/sites/${siteId}/${type}/${pageId}/versions/${version.id}/restore`);
      // Reload blocks from server
      const blocksRes = await api.get(`/sites/${siteId}/${type}/${pageId}/blocks`);
      setBlocks(blocksRes.data.data);
      setRestored(version.id);
      setTimeout(() => setRestored(null), 2000);
    } catch (err) {
      console.error('Restore failed:', err);
    } finally {
      setRestoring(null);
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 size={16} className="animate-spin text-gray-400" />
      </div>
    );
  }

  if (!versions?.length) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-gray-400">
        <History size={24} className="mb-2 opacity-40" />
        <p className="text-xs">No versions yet</p>
        <p className="text-[10px] mt-1">Versions are created on publish and every 5th auto-save</p>
      </div>
    );
  }

  return (
    <div className="p-2 space-y-1">
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 px-2 mb-2">
        Version History ({versions.length})
      </h3>
      {versions.map((v) => (
        <div
          key={v.id}
          className="flex items-center justify-between px-3 py-2 rounded-lg border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-colors"
        >
          <div>
            <div className="text-xs font-medium text-gray-700">
              v{v.version_number}
            </div>
            <div className="text-[10px] text-gray-400">
              {new Date(v.published_at).toLocaleDateString('en-GB', {
                day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
              })}
              {v.published_by_user && (
                <span className="ml-1">· {v.published_by_user.name}</span>
              )}
            </div>
          </div>
          <button
            onClick={() => handleRestore(v)}
            disabled={restoring === v.id}
            className="p-1.5 rounded-md text-gray-400 hover:text-blue-600 hover:bg-blue-100 transition-colors disabled:opacity-50"
            title="Restore this version"
          >
            {restoring === v.id ? (
              <Loader2 size={14} className="animate-spin" />
            ) : restored === v.id ? (
              <CheckCircle size={14} className="text-green-500" />
            ) : (
              <RotateCcw size={14} />
            )}
          </button>
        </div>
      ))}
    </div>
  );
}
