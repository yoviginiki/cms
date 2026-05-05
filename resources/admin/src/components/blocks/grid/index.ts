import { blockRegistry } from '../registry';
import { gridDefinition } from './definition';
import { GridPreview } from './Preview';
import { GridEditor } from './Editor';

blockRegistry.register(gridDefinition, GridPreview, GridEditor);
