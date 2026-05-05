import { blockRegistry } from '../registry';
import { dividerDefinition } from './definition';
import { DividerPreview } from './Preview';
import { DividerEditor } from './Editor';

blockRegistry.register(dividerDefinition, DividerPreview, DividerEditor);
