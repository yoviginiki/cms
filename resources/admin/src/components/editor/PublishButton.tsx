import { useState } from 'react';
import { Loader2, CheckCircle, AlertCircle, Upload } from 'lucide-react';
import { api } from '@/lib/api';
import { useDeploymentStatus } from '@/hooks/useDeploymentStatus';

interface PublishButtonProps {
  siteId: string;
}

export function PublishButton({ siteId }: PublishButtonProps) {
  const [deploymentId, setDeploymentId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const { deployment, isPublishing, progress } = useDeploymentStatus(siteId, deploymentId);

  async function handlePublish() {
    setError(null);
    try {
      const res = await api.post(`/sites/${siteId}/publish`, { type: 'full' });
      setDeploymentId(res.data.data.id);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Publish failed';
      setError(msg);
    }
  }

  // Success state
  if (deployment?.status === 'live') {
    return (
      <div className="flex items-center gap-2">
        <button
          onClick={() => setDeploymentId(null)}
          className="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-sm font-medium"
        >
          <CheckCircle size={14} />
          Published!
        </button>
      </div>
    );
  }

  // Failed state
  if (deployment?.status === 'failed' || error) {
    return (
      <div className="flex items-center gap-2">
        <button
          onClick={handlePublish}
          className="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700"
        >
          <AlertCircle size={14} />
          Retry
        </button>
        <span className="text-xs text-red-500">{error ?? 'Build failed'}</span>
      </div>
    );
  }

  // Publishing in progress
  if (isPublishing) {
    const pct = progress.pages_total
      ? Math.round((progress.pages_built! / progress.pages_total) * 100)
      : 0;

    return (
      <button
        disabled
        className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium opacity-80"
      >
        <Loader2 size={14} className="animate-spin" />
        <span>
          {progress.current_step === 'building'
            ? `Building ${progress.pages_built}/${progress.pages_total} (${pct}%)`
            : progress.current_step === 'deploying'
              ? 'Deploying...'
              : 'Queued...'}
        </span>
      </button>
    );
  }

  // Idle
  return (
    <button
      onClick={handlePublish}
      className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700"
    >
      <Upload size={14} />
      Publish
    </button>
  );
}
