import { blockRegistry } from '../registry';
import { buttonDefinition } from './definition';
import { ButtonPreview } from './Preview';
import { ButtonEditor } from './Editor';

blockRegistry.register(buttonDefinition, ButtonPreview, ButtonEditor);
