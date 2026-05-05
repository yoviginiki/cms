import { blockRegistry } from '../registry';
import { paragraphDefinition } from './definition';
import { ParagraphPreview } from './Preview';
import { ParagraphEditor } from './Editor';

blockRegistry.register(paragraphDefinition, ParagraphPreview, ParagraphEditor);
