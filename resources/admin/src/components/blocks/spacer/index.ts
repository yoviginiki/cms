import { blockRegistry } from '../registry';
import { spacerDefinition } from './definition';
import { SpacerPreview } from './Preview';
import { SpacerEditor } from './Editor';

blockRegistry.register(spacerDefinition, SpacerPreview, SpacerEditor);
