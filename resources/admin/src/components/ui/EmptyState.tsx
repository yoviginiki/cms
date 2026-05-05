import type { LucideIcon } from 'lucide-react';

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
}

export function EmptyState({ icon: Icon, title, description, actionLabel, onAction }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <Icon className="h-10 w-10 text-base-content/15 mb-4" strokeWidth={1.5} />
      <h3 className="text-sm font-medium text-base-content/60 mb-1">{title}</h3>
      {description && <p className="text-[13px] text-base-content/35 mb-6 max-w-xs">{description}</p>}
      {actionLabel && onAction && (
        <button onClick={onAction} className="btn btn-primary btn-sm text-[12px]">
          {actionLabel}
        </button>
      )}
    </div>
  );
}
