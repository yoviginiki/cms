import { blockRegistry } from '../registry';
import { tableDefinition } from './definition';
import { TablePreview } from './Preview';
import { TableEditor } from './Editor';

blockRegistry.register(tableDefinition, TablePreview, TableEditor);
