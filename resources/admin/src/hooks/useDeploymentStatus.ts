import { useEffect, useRef, useState } from 'react';
import { api } from '@/lib/api';

interface DeploymentProgress {
  status: string;
  message?: string;
  pages_built?: number;
  pages_total?: number;
  current_step?: string;
}

interface Deployment {
  id: string;
  status: string;
  type: string;
  metadata: Record<string, unknown>;
  started_at: string | null;
  completed_at: string | null;
  error_log: string | null;
}

export function useDeploymentStatus(siteId: string, deploymentId: string | null) {
  const [deployment, setDeployment] = useState<Deployment | null>(null);
  const [isPublishing, setIsPublishing] = useState(false);
  const intervalRef = useRef<ReturnType<typeof setInterval>>(undefined);

  useEffect(() => {
    if (!deploymentId) {
      setDeployment(null);
      setIsPublishing(false);
      return;
    }

    setIsPublishing(true);

    async function poll() {
      try {
        const res = await api.get(`/sites/${siteId}/deployments/${deploymentId}`);
        const data = res.data.data;
        setDeployment(data);

        if (['live', 'failed', 'rolled_back'].includes(data.status)) {
          setIsPublishing(false);
          if (intervalRef.current) clearInterval(intervalRef.current);
        }
      } catch {
        setIsPublishing(false);
        if (intervalRef.current) clearInterval(intervalRef.current);
      }
    }

    poll();
    intervalRef.current = setInterval(poll, 2000);

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, [siteId, deploymentId]);

  const progress: DeploymentProgress = {
    status: deployment?.status ?? 'idle',
    pages_built: (deployment?.metadata?.pages_built as number) ?? 0,
    pages_total: (deployment?.metadata?.pages_total as number) ?? 0,
    current_step: (deployment?.metadata?.current_step as string) ?? '',
  };

  return { deployment, isPublishing, progress };
}
