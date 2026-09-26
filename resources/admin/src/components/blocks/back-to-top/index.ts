import { blockRegistry } from '../registry';
import { backToTopDefinition } from './definition';
import { BackToTopPreview } from './Preview';
import { BackToTopEditor } from './Editor';

blockRegistry.register(backToTopDefinition, BackToTopPreview, BackToTopEditor);
