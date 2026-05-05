import type { BlockDefinition } from '@/types/blocks';

export const customformDefinition: BlockDefinition = {
  type: 'customform',
  category: 'forms',
  label: 'Custom Form',
  icon: 'ClipboardList',
  defaultData: {
    fields: [
      { type: 'text', label: 'Name', required: true, placeholder: 'Your name' },
      { type: 'email', label: 'Email', required: true, placeholder: 'your@email.com' },
      { type: 'textarea', label: 'Message', required: false, placeholder: 'Your message' },
    ],
    submitText: 'Send',
    endpoint: '',
    successMessage: 'Thank you!',
  },
  allowsChildren: false,
};
