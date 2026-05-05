import { blockRegistry } from '../registry';
import { textdividerDefinition } from './definition';
import { TextdividerPreview } from './Preview';
import { TextdividerEditor } from './Editor';

blockRegistry.register(textdividerDefinition, TextdividerPreview, TextdividerEditor);
