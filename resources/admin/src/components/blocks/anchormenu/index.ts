import { blockRegistry } from '../registry';
import { anchormenuDefinition } from './definition';
import { AnchormenuPreview } from './Preview';
import { AnchormenuEditor } from './Editor';

blockRegistry.register(anchormenuDefinition, AnchormenuPreview, AnchormenuEditor);
