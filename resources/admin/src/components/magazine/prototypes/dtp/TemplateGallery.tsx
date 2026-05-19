/**
 * M8 DTP Canvas Prototype — Template Gallery
 *
 * Shows available page/spread templates with apply button.
 * Prototype-only — no persistence, no API.
 */
import { Layout, FileText, ImageIcon, MessageSquareQuote, Grid2X2, Users, BookOpen } from 'lucide-react';
import { MOCK_TEMPLATES, MOCK_MASTER_PAGES, type DtpTemplate } from './mockDocument';

interface Props {
  onApplyTemplate: (template: DtpTemplate, replace: boolean) => void;
  onAssignMaster: (masterPageId: string | null) => void;
  currentMasterPageId?: string;
}

const TEMPLATE_ICONS: Record<string, typeof Layout> = {
  'tpl-cover': ImageIcon, 'tpl-toc': FileText, 'tpl-editorial': BookOpen,
  'tpl-article': FileText, 'tpl-interview': Users, 'tpl-gallery': Grid2X2,
  'tpl-quote': MessageSquareQuote,
};

export function TemplateGallery({ onApplyTemplate, onAssignMaster, currentMasterPageId }: Props) {
  return (
    <div className="p-3 space-y-4">
      {/* Master Page Assignment */}
      <div>
        <h3 className="text-[10px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Master Page</h3>
        <div className="space-y-1">
          <button onClick={() => onAssignMaster(null)}
            className={`w-full text-left px-2 py-1.5 rounded text-[10px] transition-colors ${
              !currentMasterPageId ? 'bg-blue-600/20 text-blue-300 border border-blue-500/30' : 'text-neutral-400 hover:bg-neutral-700/50'
            }`}>
            None
          </button>
          {MOCK_MASTER_PAGES.map(mp => (
            <button key={mp.id} onClick={() => onAssignMaster(mp.id)}
              className={`w-full text-left px-2 py-1.5 rounded text-[10px] transition-colors ${
                currentMasterPageId === mp.id ? 'bg-blue-600/20 text-blue-300 border border-blue-500/30' : 'text-neutral-400 hover:bg-neutral-700/50'
              }`}>
              {mp.name}
              <span className="text-[8px] text-neutral-500 ml-1">({mp.frames.length} objects)</span>
            </button>
          ))}
        </div>
      </div>

      {/* Page Templates */}
      <div>
        <h3 className="text-[10px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Page Templates</h3>
        <div className="space-y-1.5">
          {MOCK_TEMPLATES.map(tpl => {
            const Icon = TEMPLATE_ICONS[tpl.id] || Layout;
            return (
              <div key={tpl.id} className="bg-neutral-700/30 rounded-lg p-2 border border-neutral-600/30 hover:border-neutral-500/50 transition-colors">
                <div className="flex items-start gap-2">
                  <Icon size={14} className="text-neutral-400 shrink-0 mt-0.5" />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5">
                      <span className="text-[11px] text-neutral-200 font-medium">{tpl.name}</span>
                      <span className="text-[8px] px-1 rounded bg-neutral-600 text-neutral-300">{tpl.target}</span>
                    </div>
                    <p className="text-[9px] text-neutral-400 mt-0.5">{tpl.description}</p>
                    <p className="text-[8px] text-neutral-500 mt-0.5">{tpl.frames.length} frames</p>
                  </div>
                </div>
                <div className="flex gap-1 mt-1.5">
                  <button onClick={() => onApplyTemplate(tpl, false)}
                    className="btn btn-xs btn-primary flex-1 text-[9px]">Add to page</button>
                  <button onClick={() => onApplyTemplate(tpl, true)}
                    className="btn btn-xs btn-ghost flex-1 text-[9px] text-amber-400">Replace</button>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
