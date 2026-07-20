import { blockRegistry } from '../registry';
import { queryStatDefinition } from './definition';
import { QueryStatPreview } from './Preview';
import { QueryStatEditor } from './Editor';

blockRegistry.register(queryStatDefinition, QueryStatPreview, QueryStatEditor);
