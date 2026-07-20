import { blockRegistry } from '../registry';
import { queryTableDefinition } from './definition';
import { QueryTablePreview } from './Preview';
import { QueryTableEditor } from './Editor';

blockRegistry.register(queryTableDefinition, QueryTablePreview, QueryTableEditor);
