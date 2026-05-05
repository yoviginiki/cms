import { blockRegistry } from '../registry';
import { fullbleedDefinition } from './definition';
import { FullbleedPreview } from './Preview';
import { FullbleedEditor } from './Editor';

blockRegistry.register(fullbleedDefinition, FullbleedPreview, FullbleedEditor);
