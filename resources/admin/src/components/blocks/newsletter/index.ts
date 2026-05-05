import { blockRegistry } from '../registry';
import { newsletterDefinition } from './definition';
import { NewsletterPreview } from './Preview';
import { NewsletterEditor } from './Editor';

blockRegistry.register(newsletterDefinition, NewsletterPreview, NewsletterEditor);
