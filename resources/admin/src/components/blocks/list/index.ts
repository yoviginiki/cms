import { blockRegistry } from '../registry';
import { listDefinition } from './definition';
import { ListPreview } from './Preview';
import { ListEditor } from './Editor';

blockRegistry.register(listDefinition, ListPreview, ListEditor);
