import { blockRegistry } from '../registry';
import { tocDefinition } from './definition';
import { TocPreview } from './Preview';
import { TocEditor } from './Editor';

blockRegistry.register(tocDefinition, TocPreview, TocEditor);
