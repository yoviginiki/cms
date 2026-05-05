import { blockRegistry } from '../registry';
import { customformDefinition } from './definition';
import { CustomformPreview } from './Preview';
import { CustomformEditor } from './Editor';

blockRegistry.register(customformDefinition, CustomformPreview, CustomformEditor);
