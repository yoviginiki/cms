import { blockRegistry } from '../registry';
import { fieldValueDefinition } from './definition';
import { FieldValuePreview } from './Preview';
import { FieldValueEditor } from './Editor';

blockRegistry.register(fieldValueDefinition, FieldValuePreview, FieldValueEditor);
