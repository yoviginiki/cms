import { blockRegistry } from '../registry';
import { iconDefinition } from './definition';
import { IconPreview } from './Preview';
import { IconEditor } from './Editor';

blockRegistry.register(iconDefinition, IconPreview, IconEditor);
