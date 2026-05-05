import { blockRegistry } from '../registry';
import { footnoteDefinition } from './definition';
import { FootnotePreview } from './Preview';
import { FootnoteEditor } from './Editor';

blockRegistry.register(footnoteDefinition, FootnotePreview, FootnoteEditor);
