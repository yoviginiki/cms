import { blockRegistry } from '../registry';
import { categoryHeaderDefinition } from './definition';
import { CategoryHeaderPreview } from './Preview';
import { CategoryHeaderEditor } from './Editor';

blockRegistry.register(categoryHeaderDefinition, CategoryHeaderPreview, CategoryHeaderEditor);
