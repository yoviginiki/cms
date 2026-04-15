import { blockRegistry } from '../registry';
import { textDefinition } from './definition';
import { TextPreview } from './Preview';
import { TextEditor } from './Editor';

blockRegistry.register(textDefinition, TextPreview, TextEditor);
