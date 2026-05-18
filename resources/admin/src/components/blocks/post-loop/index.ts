import { blockRegistry } from '../registry';
import { postLoopDefinition } from './definition';
import { PostLoopPreview } from './Preview';
import { PostLoopEditor } from './Editor';

blockRegistry.register(postLoopDefinition, PostLoopPreview, PostLoopEditor);
