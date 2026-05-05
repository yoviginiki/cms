import { blockRegistry } from '../registry';
import { richTextDefinition } from './definition';
import { RichTextPreview } from './Preview';
import { RichTextEditor } from './Editor';

blockRegistry.register(richTextDefinition, RichTextPreview, RichTextEditor);
