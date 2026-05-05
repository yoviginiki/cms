import { blockRegistry } from '../registry';
import { statsDefinition } from './definition';
import { StatsPreview } from './Preview';
import { StatsEditor } from './Editor';

blockRegistry.register(statsDefinition, StatsPreview, StatsEditor);
