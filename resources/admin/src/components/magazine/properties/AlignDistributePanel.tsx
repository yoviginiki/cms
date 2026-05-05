

interface AlignDistributePanelProps {
  onAlign: (type: string) => void;
  onDistribute: (type: string) => void;
  alignToPage: boolean;
  onToggleAlignToPage: () => void;
}

const ALIGN_TYPES = [
  { type: 'align-left', label: 'L' },
  { type: 'align-center-h', label: 'CH' },
  { type: 'align-right', label: 'R' },
  { type: 'align-top', label: 'T' },
  { type: 'align-center-v', label: 'CV' },
  { type: 'align-bottom', label: 'B' },
];

const DISTRIBUTE_TYPES = [
  { type: 'distribute-horizontal', label: 'Dist H' },
  { type: 'distribute-vertical', label: 'Dist V' },
];

export default function AlignDistributePanel({ onAlign, onDistribute, alignToPage, onToggleAlignToPage }: AlignDistributePanelProps) {
  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Align</h3>

      <div className="flex gap-1 flex-wrap">
        {ALIGN_TYPES.map((item) => (
          <button
            key={item.type}
            type="button"
            onClick={() => onAlign(item.type)}
            className="btn btn-xs btn-ghost btn-outline"
            title={item.type}
          >
            {item.label}
          </button>
        ))}
      </div>

      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Distribute</h3>

      <div className="flex gap-1">
        {DISTRIBUTE_TYPES.map((item) => (
          <button
            key={item.type}
            type="button"
            onClick={() => onDistribute(item.type)}
            className="btn btn-xs btn-ghost btn-outline flex-1"
            title={item.type}
          >
            {item.label}
          </button>
        ))}
      </div>

      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={alignToPage}
          onChange={onToggleAlignToPage}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Align to page</span>
      </label>
    </div>
  );
}
