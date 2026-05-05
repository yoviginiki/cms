import { useEffect, useState, useRef } from 'react';
import { editor } from '@/lib/api';

interface ActiveEditor {
  id: string;
  name: string;
  email: string;
  since: string;
}

export function useEditorPresence(
  contentType: 'pages' | 'posts',
  contentId: string,
) {
  const [activeEditors, setActiveEditors] = useState<ActiveEditor[]>([]);
  const heartbeatRef = useRef<number | null>(null);
  const presenceRef = useRef<number | null>(null);

  useEffect(() => {
    if (!contentId) return;

    const key = contentType === 'pages' ? 'page_id' : 'post_id';

    // Initial heartbeat
    editor.heartbeat({ [key]: contentId }).catch(() => {});

    // Heartbeat every 30s
    heartbeatRef.current = window.setInterval(() => {
      editor.heartbeat({ [key]: contentId }).catch(() => {});
    }, 30000);

    // Poll presence every 10s
    const fetchPresence = () => {
      editor.presence(contentType, contentId)
        .then(res => setActiveEditors(res.data?.data || []))
        .catch(() => {});
    };
    fetchPresence();
    presenceRef.current = window.setInterval(fetchPresence, 10000);

    // Leave on unmount / beforeunload
    const handleUnload = () => {
      const data = JSON.stringify({ [key]: contentId });
      navigator.sendBeacon?.('/api/v1/editor/heartbeat', new Blob([data], { type: 'application/json' }));
    };
    window.addEventListener('beforeunload', handleUnload);

    return () => {
      if (heartbeatRef.current) window.clearInterval(heartbeatRef.current);
      if (presenceRef.current) window.clearInterval(presenceRef.current);
      window.removeEventListener('beforeunload', handleUnload);
    };
  }, [contentType, contentId]);

  return { activeEditors, isConflict: activeEditors.length > 0 };
}
