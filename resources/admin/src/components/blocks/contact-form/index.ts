import { blockRegistry } from '../registry';
import { contactFormDefinition } from './definition';
import { ContactFormPreview } from './Preview';
import { ContactFormEditor } from './Editor';

blockRegistry.register(contactFormDefinition, ContactFormPreview, ContactFormEditor);
