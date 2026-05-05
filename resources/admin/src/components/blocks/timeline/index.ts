import { blockRegistry } from '../registry';
import { timelineDefinition } from './definition';
import { TimelinePreview } from './Preview';
import { TimelineEditor } from './Editor';

blockRegistry.register(timelineDefinition, TimelinePreview, TimelineEditor);
