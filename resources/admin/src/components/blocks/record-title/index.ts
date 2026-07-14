import { blockRegistry } from '../registry';
import { recordTitleDefinition } from './definition';
import { RecordTitlePreview } from './Preview';
import { RecordTitleEditor } from './Editor';

blockRegistry.register(recordTitleDefinition, RecordTitlePreview, RecordTitleEditor);
