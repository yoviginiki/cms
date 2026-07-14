import { blockRegistry } from '../registry';
import { recordImageDefinition } from './definition';
import { RecordImagePreview } from './Preview';
import { RecordImageEditor } from './Editor';

blockRegistry.register(recordImageDefinition, RecordImagePreview, RecordImageEditor);
