import { blockRegistry } from '../registry';
import { facetFilterDefinition } from './definition';
import { FacetFilterPreview } from './Preview';
import { FacetFilterEditor } from './Editor';

blockRegistry.register(facetFilterDefinition, FacetFilterPreview, FacetFilterEditor);
