interface StatusBadgeProps {
  status: string;
}

const statusStyles: Record<string, string> = {
  draft: 'badge-ghost text-base-content/50',
  published: 'badge-success badge-outline',
  archived: 'badge-warning badge-outline',
  active: 'badge-success badge-outline',
  paused: 'badge-warning badge-outline',
  queued: 'badge-info badge-outline',
  building: 'badge-info badge-outline',
  staged: 'badge-warning badge-outline',
  live: 'badge-success badge-outline',
  failed: 'badge-error badge-outline',
  stale: 'badge-warning badge-outline',
};

export function StatusBadge({ status }: StatusBadgeProps) {
  return (
    <span className={`badge badge-sm ${statusStyles[status] ?? 'badge-ghost'} text-[11px] font-medium`}>
      {status}
    </span>
  );
}
