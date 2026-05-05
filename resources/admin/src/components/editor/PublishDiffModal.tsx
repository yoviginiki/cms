import { useQuery } from '@tanstack/react-query';
import { X, Plus, Minus, Pencil, ArrowUpDown, Loader2, CheckCircle } from 'lucide-react';
import { pages, posts } from '@/lib/api';

interface DiffBlock {
  id: string;
  type: string;
  status: 'added' | 'removed' | 'modified' | 'moved' | 'unchanged';
  data: Record<string, unknown>;
  old_data?: Record<string, unknown>;
  changes: { field: string; old: unknown; new: unknown }[];
}

interface DiffData {
  is_first_publish: boolean;
  blocks: DiffBlock[];
  seo: { field: string; old: unknown; new: unknown }[];
  summary: { added: number; modified: number; removed: number; moved: number };
}

interface Props {
  open: boolean;
  siteId: string;
  contentType: 'pages' | 'posts';
  contentId: string;
  onConfirm: () => void;
  onClose: () => void;
}

const statusConfig = {
  added: { color: 'border-green-400 bg-green-50', badge: 'bg-green-100 text-green-700', label: 'NEW', icon: Plus },
  removed: { color: 'border-red-400 bg-red-50', badge: 'bg-red-100 text-red-700', label: 'REMOVED', icon: Minus },
  modified: { color: 'border-amber-400 bg-amber-50', badge: 'bg-amber-100 text-amber-700', label: 'MODIFIED', icon: Pencil },
  moved: { color: 'border-blue-400 bg-blue-50', badge: 'bg-blue-100 text-blue-700', label: 'MOVED', icon: ArrowUpDown },
  unchanged: { color: 'border-transparent opacity-50', badge: '', label: '', icon: null },
};

export function PublishDiffModal({ open, siteId, contentType, contentId, onConfirm, onClose }: Props) {
  if (!open) return null;

  const apiMethod = contentType === 'pages' ? pages.diff : posts.diff;

  const { data: diff, isLoading } = useQuery<DiffData>({
    queryKey: ['diff', siteId, contentType, contentId],
    queryFn: () => apiMethod(siteId, contentId).then(r => r.data.data),
    enabled: open,
  });

  if (isLoading) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div className="bg-white rounded-xl p-8 text-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-500 mx-auto mb-2" />
          <p className="text-sm text-gray-500">Computing changes...</p>
        </div>
      </div>
    );
  }

  if (!diff) return null;

  if (diff.is_first_publish) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div className="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
          <div className="text-center mb-4">
            <CheckCircle className="h-12 w-12 text-green-500 mx-auto mb-2" />
            <h3 className="text-lg font-semibold">First Publish</h3>
            <p className="text-sm text-gray-500 mt-1">All content will go live for the first time.</p>
          </div>
          <div className="flex gap-2 justify-end">
            <button onClick={onClose} className="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button onClick={onConfirm} className="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">Confirm Publish</button>
          </div>
        </div>
      </div>
    );
  }

  const { summary } = diff;
  const changedBlocks = diff.blocks.filter(b => b.status !== 'unchanged');

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <div>
            <h3 className="text-lg font-semibold">Review Changes</h3>
            <p className="text-sm text-gray-500 mt-0.5">
              {summary.added > 0 && <span className="text-green-600">{summary.added} added</span>}
              {summary.modified > 0 && <span className="text-amber-600 ml-2">{summary.modified} modified</span>}
              {summary.removed > 0 && <span className="text-red-600 ml-2">{summary.removed} removed</span>}
              {summary.moved > 0 && <span className="text-blue-600 ml-2">{summary.moved} moved</span>}
            </p>
          </div>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6 space-y-3">
          {changedBlocks.map(block => {
            const cfg = statusConfig[block.status];
            const Icon = cfg.icon;
            return (
              <div key={block.id} className={`border-l-4 rounded-lg p-3 ${cfg.color}`}>
                <div className="flex items-center gap-2 mb-1">
                  {Icon && <Icon className="h-3.5 w-3.5" />}
                  <span className="text-sm font-medium capitalize">{block.type}</span>
                  {cfg.label && <span className={`text-xs px-1.5 py-0.5 rounded ${cfg.badge}`}>{cfg.label}</span>}
                </div>
                {block.changes.length > 0 && (
                  <div className="text-xs text-gray-600 mt-1 space-y-0.5">
                    {block.changes.map((c, i) => (
                      <div key={i}>
                        <span className="font-medium">{c.field}:</span>{' '}
                        <span className="line-through text-red-500">{String(c.old ?? '(empty)').slice(0, 60)}</span>
                        {' → '}
                        <span className="text-green-600">{String(c.new ?? '(empty)').slice(0, 60)}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            );
          })}

          {/* SEO changes */}
          {diff.seo.length > 0 && (
            <div className="border-t border-gray-200 pt-3 mt-3">
              <p className="text-sm font-medium text-gray-700 mb-2">SEO Changes</p>
              {diff.seo.map((s, i) => (
                <div key={i} className="text-xs text-gray-600 mb-1">
                  <span className="font-medium">{s.field}:</span>{' '}
                  <span className="line-through text-red-500">{String(s.old ?? '(empty)').slice(0, 80)}</span>
                  {' → '}
                  <span className="text-green-600">{String(s.new ?? '(empty)').slice(0, 80)}</span>
                </div>
              ))}
            </div>
          )}

          {changedBlocks.length === 0 && diff.seo.length === 0 && (
            <p className="text-sm text-gray-500 text-center py-4">No changes detected since last publish.</p>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
          <button onClick={onClose} className="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
          <button onClick={onConfirm} className="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">Confirm Publish</button>
        </div>
      </div>
    </div>
  );
}
