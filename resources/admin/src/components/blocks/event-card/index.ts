import { blockRegistry } from '../registry';
import { eventCardDefinition } from './definition';
import { EventCardPreview } from './Preview';
import { EventCardEditor } from './Editor';

blockRegistry.register(eventCardDefinition, EventCardPreview, EventCardEditor);
