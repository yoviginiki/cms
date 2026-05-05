import { blockRegistry } from '../registry';
import { codeDefinition } from './definition';
import { CodePreview } from './Preview';
import { CodeEditor } from './Editor';

blockRegistry.register(codeDefinition, CodePreview, CodeEditor);
