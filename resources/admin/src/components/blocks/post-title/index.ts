import { blockRegistry } from '../registry';
import { postTitleDefinition } from './definition';
import { PostTitlePreview } from './Preview';
import { PostTitleEditor } from './Editor';

blockRegistry.register(postTitleDefinition, PostTitlePreview, PostTitleEditor);
