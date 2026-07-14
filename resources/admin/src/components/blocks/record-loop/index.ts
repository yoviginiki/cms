import { blockRegistry } from '../registry';
import { recordLoopDefinition } from './definition';
import { RecordLoopPreview } from './Preview';
import { RecordLoopEditor } from './Editor';

blockRegistry.register(recordLoopDefinition, RecordLoopPreview, RecordLoopEditor);
