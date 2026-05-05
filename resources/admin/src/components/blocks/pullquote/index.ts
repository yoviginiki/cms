import { blockRegistry } from '../registry';
import { pullquoteDefinition } from './definition';
import { PullquotePreview } from './Preview';
import { PullquoteEditor } from './Editor';

blockRegistry.register(pullquoteDefinition, PullquotePreview, PullquoteEditor);
