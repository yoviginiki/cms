import { blockRegistry } from '../registry';
import { scrollPageDefinition } from './definition';
import { ScrollPagePreview } from './Preview';
import { ScrollPageEditor } from './Editor';

blockRegistry.register(scrollPageDefinition, ScrollPagePreview, ScrollPageEditor);
