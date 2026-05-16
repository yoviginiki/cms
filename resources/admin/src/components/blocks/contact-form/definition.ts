import type { BlockDefinition } from '@/types/blocks';

export const contactFormDefinition: BlockDefinition = {
  type: 'contact-form',
  category: 'forms',
  label: 'Contact Form',
  icon: 'Mail',
  defaultData: {
    fields: [
      { label: 'Name', type: 'text', required: true },
      { label: 'Email', type: 'email', required: true },
      { label: 'Message', type: 'textarea', required: true },
    ],
    recipient_email: '',
    success_message: 'Thank you!',
    submit_label: 'Send Message',
  },
  allowsChildren: false,
};
