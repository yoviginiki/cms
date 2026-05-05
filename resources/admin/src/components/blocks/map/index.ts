import { blockRegistry } from '../registry';
import { mapDefinition } from './definition';
import { MapPreview } from './Preview';
import { MapEditor } from './Editor';

blockRegistry.register(mapDefinition, MapPreview, MapEditor);
