import { useWizardStore } from '../store';
import { STEP_LABELS } from '../types';
import BriefArtifact from './artifacts/BriefArtifact';
import StructureArtifact from './artifacts/StructureArtifact';
import SelectionArtifact from './artifacts/SelectionArtifact';
import AnalysisArtifact from './artifacts/AnalysisArtifact';
import DirectionsArtifact from './artifacts/DirectionsArtifact';
import ThumbnailsArtifact from './artifacts/ThumbnailsArtifact';
import ReviewArtifact from './artifacts/ReviewArtifact';

interface Props {
  siteId: string;
}

export default function ArtifactPanel({ siteId }: Props) {
  const { session, currentArtifact, setArtifact } = useWizardStore();
  const step = session?.current_step ?? 1;

  return (
    <div className="flex flex-col h-full bg-base-100 border-l border-base-300/20">
      <div className="px-4 py-2 border-b border-base-300/20 shrink-0">
        <div className="text-[15px] font-medium text-base-content/50">
          {STEP_LABELS[step]} plan
        </div>
        <div className="text-[15px] text-base-content/25 mt-0.5">
          Editable — updates merge with AI suggestions
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-3">
        {step === 1 && (
          <BriefArtifact data={currentArtifact as any} onChange={setArtifact} />
        )}
        {step === 2 && (
          <StructureArtifact data={currentArtifact as any} onChange={setArtifact} siteId={siteId} />
        )}
        {step === 3 && (
          <SelectionArtifact
            data={currentArtifact as any}
            structure={session?.step2_structure ?? null}
            onChange={setArtifact}
          />
        )}
        {step === 4 && (
          <AnalysisArtifact data={currentArtifact as any} onChange={setArtifact} />
        )}
        {step === 5 && (
          <DirectionsArtifact data={currentArtifact as any} onChange={setArtifact} />
        )}
        {step === 6 && (
          <ThumbnailsArtifact data={currentArtifact as any} onChange={setArtifact} />
        )}
        {step === 7 && session && (
          <ReviewArtifact session={session} />
        )}
      </div>
    </div>
  );
}
