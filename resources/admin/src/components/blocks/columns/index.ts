import { blockRegistry } from '../registry';
import { columnsDefinition } from './definition';
import { ColumnsPreview } from './Preview';
import { ColumnsEditor } from './Editor';

blockRegistry.register(columnsDefinition, ColumnsPreview, ColumnsEditor);
