import { blockRegistry } from '../registry';
import { postContentDefinition } from './definition';
import { PostContentPreview } from './Preview';
import { PostContentEditor } from './Editor';

blockRegistry.register(postContentDefinition, PostContentPreview, PostContentEditor);
