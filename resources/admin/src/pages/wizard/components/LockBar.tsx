import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Lock, Rocket, Loader2 } from 'lucide-react';
import { useWizardStore } from '../store';
import { wizardApi } from '../api';
import { STEP_LABELS } from '../types';
import type { Step2Structure, Step5Directions } from '../types';

export default function LockBar() {
  const navigate = useNavigate();
  const { session, currentArtifact, lock, unlock, isStreaming } = useWizardStore();
  const [locking, setLocking] = useState(false);
  const [provisioning, setProvisioning] = useState(false);
  const [provisionError, setProvisionError] = useState<string | null>(null);

  if (!session) return null;

  const step = session.current_step;
  const canGoBack = step > 1;

  // Step-specific validity
  const isValid = (() => {
    if (step === 7) return true; // review step always valid
    if (!currentArtifact) return false;
    const a = currentArtifact as Record<string, unknown>;

    switch (step) {
      case 1: return !!a.feeling;
      case 2: return Array.isArray((a as unknown as Step2Structure).articles) && (a as unknown as Step2Structure).articles.length > 0;
      case 3: return !!a.selected_slug;
      case 4: return Array.isArray(a.beats) && (a.beats as unknown[]).length > 0;
      case 5: return !!(a as unknown as Step5Directions).chosen;
      case 6: return Array.isArray(a.spreads) && (a.spreads as unknown[]).length > 0;
      default: return false;
    }
  })();

  const handleLock = async () => {
    setLocking(true);
    await lock();
    setLocking(false);
  };

  const handleProvision = async () => {
    if (!session) return;
    setProvisioning(true);
    setProvisionError(null);
    try {
      const result = await wizardApi.provision(session.id);
      if (result.redirect_url) {
        navigate(result.redirect_url);
      }
    } catch (e: any) {
      setProvisionError(e.response?.data?.message || 'Provisioning failed. Please try again.');
    } finally {
      setProvisioning(false);
    }
  };

  return (
    <>
      <div className="flex items-center justify-between px-4 py-2 bg-base-100 border-t border-base-300/20 shrink-0">
        <div>
          {canGoBack && (
            <button
              onClick={() => unlock(step - 1)}
              disabled={isStreaming}
              className="btn btn-ghost btn-sm text-[15px] gap-1 text-base-content/40"
            >
              <ArrowLeft size={12} /> Back to {STEP_LABELS[step - 1]}
            </button>
          )}
        </div>

        <div>
          {step === 7 ? (
            <button
              onClick={handleProvision}
              disabled={provisioning}
              className="btn btn-primary btn-sm text-[14px] gap-1.5"
            >
              {provisioning ? <Loader2 size={13} className="animate-spin" /> : <Rocket size={13} />}
              Provision in Magazine Editor
            </button>
          ) : (
            <button
              onClick={handleLock}
              disabled={!isValid || locking || isStreaming}
              className="btn btn-primary btn-sm text-[14px] gap-1.5"
            >
              {locking ? <Loader2 size={13} className="animate-spin" /> : <Lock size={13} />}
              Lock & continue
            </button>
          )}
        </div>
      </div>

      {/* Provision error modal */}
      {provisionError && (
        <div className="modal modal-open z-50">
          <div className="modal-box max-w-sm">
            <h3 className="font-medium text-sm text-error">Provisioning failed</h3>
            <p className="text-[14px] text-base-content/60 mt-2">{provisionError}</p>
            <div className="modal-action">
              <button className="btn btn-ghost btn-sm" onClick={() => setProvisionError(null)}>Back to edit</button>
            </div>
          </div>
          <div className="modal-backdrop" onClick={() => setProvisionError(null)} />
        </div>
      )}
    </>
  );
}
