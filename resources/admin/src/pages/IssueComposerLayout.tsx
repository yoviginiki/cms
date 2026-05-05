import { useParams, useNavigate } from 'react-router-dom';
import { Check, Lock } from 'lucide-react';

const STEPS = [
  { key: 'brief', label: 'Brief', path: '', unlocked: true },
  { key: 'intake', label: 'Intake', path: '/intake', unlocked: true },
  { key: 'curation', label: 'Curation & flow', path: '/curation', unlocked: true },
  { key: 'art', label: 'Art direction', path: '/art', unlocked: false, chip: 'coming soon' },
  { key: 'layout', label: 'Layout', path: '/layout', unlocked: true },
  { key: 'critique', label: 'Critique & refine', path: '/critique', unlocked: false, chip: 'coming soon' },
  { key: 'handoff', label: 'Handoff', path: '/handoff', unlocked: true },
];

interface Props {
  children: React.ReactNode;
  issueId?: string;
  issueStatus?: string;
  currentStep: string;
}

function stepComplete(status: string | undefined, stepKey: string): boolean {
  if (!status) return false;
  const order = ['draft', 'intake', 'curating', 'laid_out', 'ready', 'handed_off'];
  const stepMap: Record<string, number> = { brief: 0, intake: 1, curation: 2, art: 3, layout: 4, critique: 5, handoff: 6 };
  const statusIdx = order.indexOf(status);
  const stepIdx = stepMap[stepKey] ?? 99;
  return statusIdx > stepIdx;
}

export default function IssueComposerLayout({ children, issueId, issueStatus, currentStep }: Props) {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const basePath = issueId
    ? `/sites/${siteId}/issue-composer/${issueId}`
    : `/sites/${siteId}/issue-composer/new`;

  return (
    <div className="flex h-screen bg-base-200" data-theme={localStorage.getItem('admin-theme') || 'cms-admin'}>
      {/* Left rail */}
      <aside className="w-56 bg-base-100 border-r border-base-300/30 flex flex-col shrink-0">
        <div className="h-12 flex items-center px-4 border-b border-base-300/20">
          <button onClick={() => navigate(`/sites/${siteId}/magazines`)}
            className="text-[13px] text-base-content/50 hover:text-base-content/80 transition-colors">
            ← Back to magazines
          </button>
        </div>

        <div className="px-3 pt-4 pb-2">
          <h2 className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider">Issue composer</h2>
        </div>

        <nav className="flex-1 px-2 space-y-0.5">
          {STEPS.map((step, idx) => {
            const isActive = currentStep === step.key;
            const isDone = stepComplete(issueStatus, step.key);
            const canClick = step.unlocked && (step.key === 'brief' || issueId);

            return (
              <button
                key={step.key}
                onClick={() => canClick && navigate(basePath + step.path)}
                disabled={!canClick}
                className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-md text-[13px] text-left transition-colors ${
                  isActive
                    ? 'bg-primary/10 text-primary'
                    : canClick
                    ? 'text-base-content/50 hover:text-base-content/70 hover:bg-base-300/20'
                    : 'text-base-content/20 cursor-not-allowed'
                }`}
              >
                {/* Step number / check / lock */}
                <span className={`w-5 h-5 rounded-full text-[10px] font-medium flex items-center justify-center shrink-0 ${
                  isDone ? 'bg-success/20 text-success' :
                  isActive ? 'bg-primary/20 text-primary' :
                  'bg-base-300/30 text-base-content/30'
                }`}>
                  {isDone ? <Check size={10} /> : !step.unlocked ? <Lock size={9} /> : idx + 1}
                </span>

                <span className="flex-1">{step.label}</span>

                {step.chip && (
                  <span className="text-[8px] px-1.5 py-0.5 rounded-full bg-base-300/30 text-base-content/25">{step.chip}</span>
                )}
              </button>
            );
          })}
        </nav>
      </aside>

      {/* Main content */}
      <main className="flex-1 overflow-y-auto">
        {children}
      </main>
    </div>
  );
}
