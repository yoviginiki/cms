import { blockRegistry } from '../registry';
import { menuDefinition } from './definition';
import { MenuPreview } from './Preview';
import { MenuEditor } from './Editor';

blockRegistry.register(menuDefinition, MenuPreview, MenuEditor);
