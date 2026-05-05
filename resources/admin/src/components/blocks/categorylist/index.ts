import { blockRegistry } from '../registry';
import { categorylistDefinition } from './definition';
import { CategorylistPreview } from './Preview';
import { CategorylistEditor } from './Editor';

blockRegistry.register(categorylistDefinition, CategorylistPreview, CategorylistEditor);
