import { blockRegistry } from '../registry';
import { searchBoxDefinition } from './definition';
import { SearchBoxPreview } from './Preview';
import { SearchBoxEditor } from './Editor';

blockRegistry.register(searchBoxDefinition, SearchBoxPreview, SearchBoxEditor);
