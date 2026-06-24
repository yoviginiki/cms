import { blockRegistry } from '../registry';
import { catalogDefinition } from './definition';
import { CatalogPreview } from './Preview';
import { CatalogEditor } from './Editor';

blockRegistry.register(catalogDefinition, CatalogPreview, CatalogEditor);
