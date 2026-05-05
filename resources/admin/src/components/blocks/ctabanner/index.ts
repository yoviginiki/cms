import { blockRegistry } from '../registry';
import { ctabannerDefinition } from './definition';
import { CtabannerPreview } from './Preview';
import { CtabannerEditor } from './Editor';

blockRegistry.register(ctabannerDefinition, CtabannerPreview, CtabannerEditor);
