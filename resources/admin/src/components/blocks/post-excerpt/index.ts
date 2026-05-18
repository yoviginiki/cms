import { blockRegistry } from '../registry';
import { postExcerptDefinition } from './definition';
import { PostExcerptPreview } from './Preview';
import { PostExcerptEditor } from './Editor';

blockRegistry.register(postExcerptDefinition, PostExcerptPreview, PostExcerptEditor);
