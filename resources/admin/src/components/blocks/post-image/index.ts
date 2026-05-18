import { blockRegistry } from '../registry';
import { postImageDefinition } from './definition';
import { PostImagePreview } from './Preview';
import { PostImageEditor } from './Editor';

blockRegistry.register(postImageDefinition, PostImagePreview, PostImageEditor);
