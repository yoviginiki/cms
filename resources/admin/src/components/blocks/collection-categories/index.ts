import { blockRegistry } from '../registry';
import { collectionCategoriesDefinition } from './definition';
import { CollectionCategoriesPreview } from './Preview';
import { CollectionCategoriesEditor } from './Editor';

blockRegistry.register(collectionCategoriesDefinition, CollectionCategoriesPreview, CollectionCategoriesEditor);
