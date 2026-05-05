import { blockRegistry } from '../registry';
import { overlapDefinition } from './definition';
import { OverlapPreview } from './Preview';
import { OverlapEditor } from './Editor';

blockRegistry.register(overlapDefinition, OverlapPreview, OverlapEditor);
