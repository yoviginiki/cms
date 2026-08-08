import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface EventCardData {
  title?: string;
  start_at?: string;
  end_at?: string;
  city?: string;
  venue?: string;
  short_description?: string;
  ticket_url?: string;
  is_free?: boolean;
  official_url?: string;
}

function fmt(v?: string): string | null {
  if (!v) return null;
  const d = new Date(v);
  return isNaN(d.getTime()) ? v : d.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
}

export const EventCardPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as EventCardData;
  const start = fmt(d.start_at);
  const end = fmt(d.end_at);
  const place = [d.venue, d.city].filter(Boolean).join(', ');

  return (
    <article className="event-card border border-base-300/40 rounded-box p-3">
      <h3 className="text-[14px] font-semibold text-base-content">
        {d.title || <span className="text-base-content/30 italic">Untitled event</span>}
      </h3>
      {(start || place || d.is_free) && (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1 text-[12px] text-base-content/55">
          {start && <span>{start}{end ? ` – ${end}` : ''}</span>}
          {place && <span>· {place}</span>}
          {d.is_free && <span className="badge badge-xs badge-success badge-outline text-[10px]">Free</span>}
        </div>
      )}
      {d.short_description && <p className="text-[12px] text-base-content/60 mt-1.5">{d.short_description}</p>}
      {(d.ticket_url || d.official_url) && (
        <div className="flex gap-3 mt-2 text-[11px]">
          {d.ticket_url && <span className="text-primary">Tickets</span>}
          {d.official_url && <span className="text-primary">Details</span>}
        </div>
      )}
    </article>
  );
};
