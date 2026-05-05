import { useState } from 'react';
import { Check } from 'lucide-react';
import { STEP_LABELS, STEP_COUNT } from '../types';

interface Props {
  currentStep: number;
  onUnlock: (toStep: number) => void;
}

export default function StepRail({ currentStep, onUnlock }: Props) {
  const [confirmStep, setConfirmStep] = useState<number | null>(null);

  return (
    <>
      <div className="flex items-center gap-0 px-4 py-3 bg-base-100 border-b border-base-300/20 overflow-x-auto">
        {Array.from({ length: STEP_COUNT }, (_, i) => i + 1).map(step => {
          const isCompleted = step < currentStep;
          const isCurrent = step === currentStep;
          const isFuture = step > currentStep;

          return (
            <div key={step} className="flex items-center">
              {step > 1 && (
                <div className={`w-8 h-px ${isCompleted ? 'bg-primary/40' : 'bg-base-300/30'}`} />
              )}
              <button
                onClick={() => {
                  if (isCompleted) setConfirmStep(step);
                }}
                disabled={isFuture}
                className={`flex items-center gap-1.5 px-2 py-1 rounded-md text-[15px] font-medium transition-colors whitespace-nowrap ${
                  isCurrent
                    ? 'bg-primary/10 text-primary border border-primary/20'
                    : isCompleted
                    ? 'text-base-content/60 hover:bg-base-200/50 cursor-pointer'
                    : 'text-base-content/20 cursor-not-allowed'
                }`}
              >
                <div className={`w-5 h-5 rounded-full flex items-center justify-center text-[15px] font-bold shrink-0 ${
                  isCurrent
                    ? 'bg-primary text-primary-content'
                    : isCompleted
                    ? 'bg-success/20 text-success'
                    : 'bg-base-300/20 text-base-content/20'
                }`}>
                  {isCompleted ? <Check size={10} /> : step}
                </div>
                {STEP_LABELS[step]}
              </button>
            </div>
          );
        })}
      </div>

      {/* Confirm unlock modal */}
      {confirmStep !== null && (
        <div className="modal modal-open z-50">
          <div className="modal-box max-w-sm">
            <h3 className="font-medium text-sm">Go back to {STEP_LABELS[confirmStep]}?</h3>
            <p className="text-[14px] text-base-content/50 mt-2">
              Steps {confirmStep}–{currentStep - 1} will be unlocked and their plans cleared. Messages are preserved.
            </p>
            <div className="modal-action">
              <button className="btn btn-ghost btn-sm" onClick={() => setConfirmStep(null)}>Cancel</button>
              <button className="btn btn-warning btn-sm" onClick={() => { onUnlock(confirmStep); setConfirmStep(null); }}>
                Unlock & go back
              </button>
            </div>
          </div>
          <div className="modal-backdrop" onClick={() => setConfirmStep(null)} />
        </div>
      )}
    </>
  );
}
