import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, AlertTriangle } from 'lucide-react';
import { useWizardStore } from './store';
import StepRail from './components/StepRail';
import ChatPanel from './components/ChatPanel';
import ArtifactPanel from './components/ArtifactPanel';
import LockBar from './components/LockBar';

export default function WizardPage() {
  const { siteId = '', id = '' } = useParams();
  const navigate = useNavigate();
  const { session, isLoading, error, hydrate, unlock, clearError } = useWizardStore();

  useEffect(() => {
    if (id) hydrate(id);
  }, [id]);

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-base-200" data-theme={adminTheme}>
        <span className="loading loading-spinner loading-sm text-base-content/20" />
      </div>
    );
  }

  if (!session) {
    return (
      <div className="flex items-center justify-center h-screen bg-base-200" data-theme={adminTheme}>
        <div className="text-center">
          <div className="text-sm text-base-content/40 mb-2">Session not found</div>
          <button onClick={() => navigate(`/sites/${siteId}/magazine/wizard`)} className="btn btn-ghost btn-sm text-[14px]">
            Back to sessions
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-screen bg-base-200" data-theme={adminTheme}>
      {/* Header */}
      <div className="flex items-center gap-3 px-4 h-10 bg-base-100 border-b border-base-300/30 shrink-0">
        <button onClick={() => navigate(`/sites/${siteId}/magazine/wizard`)} className="btn btn-ghost btn-sm btn-square">
          <ArrowLeft size={14} />
        </button>
        <h1 className="text-[15px] font-medium text-base-content/80 truncate">
          {session.title || 'Untitled session'}
        </h1>
        <span className="badge badge-sm badge-ghost text-[12px]">{session.status}</span>
      </div>

      {/* Step rail */}
      <StepRail currentStep={session.current_step} onUnlock={unlock} />

      {/* Error banner */}
      {error && (
        <div className="mx-4 mt-2 flex items-center gap-2 p-2 rounded bg-error/10 border border-error/20 text-error text-[15px]">
          <AlertTriangle size={12} />
          <span className="flex-1">{error}</span>
          <button onClick={clearError} className="btn btn-ghost btn-sm">Dismiss</button>
        </div>
      )}

      {/* Main: Chat + Artifact */}
      <div className="flex flex-1 overflow-hidden">
        <div className="flex-[3] min-w-0">
          <ChatPanel />
        </div>
        <div className="flex-[2] min-w-0">
          <ArtifactPanel siteId={siteId} />
        </div>
      </div>

      {/* Lock bar */}
      <LockBar />
    </div>
  );
}
