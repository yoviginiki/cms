import { blockRegistry } from '../registry';
import { globalRefDefinition } from './definition';
import { GlobalRefPreview } from './Preview';
import { GlobalRefEditor } from './Editor';

blockRegistry.register(globalRefDefinition, GlobalRefPreview, GlobalRefEditor);
