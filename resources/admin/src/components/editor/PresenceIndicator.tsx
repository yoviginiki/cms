interface ActiveEditor {
  id: string;
  name: string;
  email: string;
}

interface Props {
  editors: ActiveEditor[];
}

export function PresenceIndicator({ editors }: Props) {
  if (editors.length === 0) return null;

  return (
    <div className="flex items-center gap-2 px-3 py-1 bg-amber-50 border border-amber-200 rounded-lg text-xs">
      <div className="flex -space-x-1.5">
        {editors.slice(0, 3).map((e) => (
          <div
            key={e.id}
            className="w-5 h-5 rounded-full bg-amber-400 text-white flex items-center justify-center text-[10px] font-bold border-2 border-white"
            title={e.name}
          >
            {e.name.charAt(0).toUpperCase()}
          </div>
        ))}
      </div>
      <span className="text-amber-700">
        {editors.map(e => e.name).join(', ')} {editors.length === 1 ? 'is' : 'are'} also editing
      </span>
    </div>
  );
}
