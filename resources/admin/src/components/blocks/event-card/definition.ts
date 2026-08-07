import type { BlockDefinition } from '@/types/blocks';

export const eventCardDefinition: BlockDefinition = {
  type: 'event-card',
  category: 'content',
  label: 'Event card',
  icon: 'Ticket',
  description: 'A single cultural event within a bulletin.',
  level: 'module',
  defaultData: {
    title: '',
    start_at: '',
    end_at: '',
    city: '',
    venue: '',
    short_description: '',
    ticket_url: '',
    is_free: false,
    official_url: '',
  },
  allowsChildren: false,
};
